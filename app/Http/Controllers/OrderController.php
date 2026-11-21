<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function addToCart($id)
    {
        DB::transaction(function () use ($id) {
            // Serialize this customer's cart changes, including creation of a pending order.
            $customer = Customer::where('user_id', Auth::id())->lockForUpdate()->firstOrFail();
            abort_if(Order::where('customer_id', $customer->id)->whereIn('status', ['awaiting_payment', 'payment_review'])->exists(), 409, 'Selesaikan atau sinkronkan pembayaran aktif terlebih dahulu.');
            $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->orderBy('id')->lockForUpdate()->first();
            $produk = Produk::where('status', 1)->lockForUpdate()->findOrFail($id);
            $item = $order?->orderItems()->where('produk_id', $produk->id)->first();
            $quantity = ($item?->quantity ?? 0) + 1;
            $this->validateStock($produk, $quantity);
            $order ??= Order::create(['customer_id' => $customer->id, 'status' => 'pending', 'total_harga' => 0]);
            if ($item) {
                $item->update(['quantity' => $quantity]);
            } else {
                $order->orderItems()->create(['produk_id' => $produk->id, 'quantity' => $quantity, 'harga' => $produk->harga]);
            }
            $this->updateTotals($order);
        });

        return redirect()->route('order.cart')->with('success', 'Produk berhasil ditambahkan ke keranjang.');
    }

    public function viewCart()
    {
        $customer = Customer::where('user_id', Auth::id())->firstOrFail();
        $active = Order::where('customer_id', $customer->id)->whereIn('status', ['awaiting_payment', 'payment_review'])->first();
        if ($active) return redirect()->route('payment.show', $active->id);
        $order = Order::with('orderItems.produk')->where('customer_id', $customer->id)
            ->where('status', 'pending')->orderBy('id')->first();

        return view('v_order.cart', compact('order'));
    }

    public function updateQuantity(Request $request, $id)
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        DB::transaction(function () use ($id, $validated) {
            $customer = Customer::where('user_id', Auth::id())->lockForUpdate()->firstOrFail();
            $order = $this->pendingOrderForItem($customer->id, $id);
            $item = $order->orderItems()->findOrFail($id);
            $produk = Produk::where('status', 1)->lockForUpdate()->findOrFail($item->produk_id);
            $this->validateStock($produk, (int) $validated['quantity']);
            $item->update(['quantity' => (int) $validated['quantity']]);
            $this->updateTotals($order);
        });

        return redirect()->route('order.cart')->with('success', 'Jumlah produk berhasil diperbarui.');
    }

    public function removeItem($id)
    {
        DB::transaction(function () use ($id) {
            $customer = Customer::where('user_id', Auth::id())->lockForUpdate()->firstOrFail();
            $order = $this->pendingOrderForItem($customer->id, $id);
            $order->orderItems()->findOrFail($id)->delete();
            $this->updateTotals($order);
        });

        return redirect()->route('order.cart')->with('success', 'Produk berhasil dihapus dari keranjang.');
    }

    public function checkout($id)
    {
        $active = Order::whereHas('customer', fn ($q) => $q->where('user_id', Auth::id()))->whereIn('status', ['awaiting_payment', 'payment_review'])->find($id);
        if ($active) return redirect()->route('payment.show', $active->id);
        $customer = Customer::where('user_id', Auth::id())->firstOrFail();
        $order = Order::with('orderItems.produk', 'customer.user')->where('customer_id', $customer->id)
            ->where('status', 'pending')->findOrFail($id);
        if ($order->orderItems->isEmpty()) {
            return redirect()->route('order.cart')->with('error', 'Keranjang belanja kosong.');
        }

        return view('v_order.checkout', [
            'order' => $order,
            'recipient' => $order->recipient(),
            'summary' => $order->summary(),
            'paymentSchemaReady' => Schema::hasTable('payment_attempts') && Schema::hasTable('payment_reservations') && Schema::hasColumn('order', 'payment_attempt_id'),
            'paymentConfigured' => !(bool) config('services.midtrans.is_production') && str_starts_with((string) config('services.midtrans.server_key'), 'SB-Mid-server-') && str_starts_with((string) config('services.midtrans.client_key'), 'SB-Mid-client-'),
            'shippingSchemaReady' => Schema::hasColumns('order', ['shipping_destination', 'shipping_quote']),
            'shippingConfigured' => filled(config('services.rajaongkir.api_key')) && ctype_digit((string) config('services.rajaongkir.origin_district')) && (int) config('services.rajaongkir.origin_district') > 0 && count(config('services.rajaongkir.couriers', [])) > 0,
            'recipientSchemaReady' => Schema::hasColumns('order', ['nama_penerima', 'email_penerima', 'hp_penerima']),
        ]);
    }

    public function saveRecipient(Request $request, $id)
    {
        $customer = Customer::where('user_id', Auth::id())->firstOrFail();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->findOrFail($id);
        $validated = $request->validate([
            'nama_penerima' => ['required', 'string', 'max:255'],
            'email_penerima' => ['required', 'email', 'max:255'],
            'hp_penerima' => ['required', 'string', 'regex:/^[0-9]{10,13}$/'],
            'alamat' => ['required', 'string', 'max:2000'],
            'pos' => ['required', 'regex:/^[0-9]{5}$/'],
        ]);
        abort_unless(Schema::hasColumns('order', ['nama_penerima', 'email_penerima', 'hp_penerima']), 503,
            'Penyimpanan penerima menunggu penerapan migrasi additive oleh pengelola.');

        DB::transaction(function () use ($customer, $order, $validated) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->where('status', 'pending')->lockForUpdate()->firstOrFail();
            $items = $order->orderItems()->with('produk')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'Keranjang belanja kosong.']);
            }
            foreach ($items as $item) {
                $product = Produk::where('status', 1)->lockForUpdate()->find($item->produk_id);
                if (!$product) {
                    throw ValidationException::withMessages(['order' => 'Produk tidak tersedia. Periksa kembali keranjang.']);
                }
                $this->validateStock($product, $item->quantity);
            }
            $order->fill($validated);
            $this->updateTotals($order);
        });

        return redirect()->route('order.checkout', $order->id)
            ->with('success', 'Data penerima disimpan. Pesanan masih berupa keranjang dan belum dibayar.');
    }

    public function orderHistory()
    {
        $customer = Customer::where('user_id', Auth::id())->firstOrFail();
        $orders = Order::transactions()->where('customer_id', $customer->id)->latest('id')->paginate(10);

        $waitingOrders = Order::where('customer_id', $customer->id)->whereIn('status', ['awaiting_payment', 'payment_review'])->latest('id')->get();
        return view('v_order.history', compact('orders', 'waitingOrders'));
    }

    public function detail($id)
    {
        $customer = Customer::where('user_id', Auth::id())->firstOrFail();
        $order = Order::transactions()->with('orderItems.produk', 'customer.user')
            ->where('customer_id', $customer->id)->findOrFail($id);

        return view('v_order.show', ['order' => $order, 'recipient' => $order->recipient(), 'summary' => $order->summary()]);
    }

    public function invoiceFrontend($id)
    {
        $customer = Customer::where('user_id', Auth::id())->firstOrFail();
        $order = Order::with('orderItems.produk', 'customer.user')->where('customer_id', $customer->id)->findOrFail($id);

        return view('v_order.invoice', [
            'order' => $order, 'recipient' => $order->recipient(), 'summary' => $order->summary(),
            'backUrl' => strtolower($order->status) === 'pending' ? route('order.cart') : (in_array($order->status, ['awaiting_payment', 'payment_review'], true) ? route('payment.show', $order->id) : route('order.show', $order->id)),
        ]);
    }

    private function pendingOrderForItem($customerId, $itemId): Order
    {
        return Order::where('customer_id', $customerId)->where('status', 'pending')
            ->whereHas('orderItems', fn ($query) => $query->whereKey($itemId))->lockForUpdate()->firstOrFail();
    }

    private function validateStock(Produk $produk, int $quantity): void
    {
        if ($quantity > $produk->stok) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah produk melebihi stok yang tersedia.']);
        }
    }

    private function updateTotals(Order $order): void
    {
        $items = $order->orderItems()->with('produk')->get();
        $order->update([
            'total_harga' => $items->sum(fn ($item) => $item->harga * $item->quantity),
            'total_berat' => (int) ceil($items->sum(fn ($item) => $item->produk->berat * $item->quantity)),
            // Every pending-cart/recipient change invalidates any previous shipping quote.
            'kurir' => null,
            'layanan_ongkir' => null,
            'biaya_ongkir' => 0,
            'estimasi_ongkir' => null,
            'noresi' => null,
            ...(Schema::hasColumn('order', 'shipping_quote') ? ['shipping_quote' => null] : []),
        ]);
    }
}
