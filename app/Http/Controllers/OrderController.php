<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function addToCart($id)
    {
        DB::transaction(function () use ($id) {
            // Serialize this customer's cart changes, including creation of a pending order.
            $customer = Customer::where('user_id', Auth::id())->lockForUpdate()->firstOrFail();
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
        ]);
    }
}
