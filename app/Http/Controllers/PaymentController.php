<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Produk;
use App\Support\MidtransSandbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function start(Request $request, $id, MidtransSandbox $gateway)
    {
        $gateway->assertConfigured();
        $this->requireSchema();
        [$attempt, $created] = DB::transaction(function () use ($request, $id) {
            $customer = Customer::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $order = Order::where('customer_id', $customer->id)->lockForUpdate()->findOrFail($id);
            if ($order->status === 'awaiting_payment' && $order->payment_attempt_id) {
                return [PaymentAttempt::findOrFail($order->payment_attempt_id), false];
            }
            abort_unless($order->status === 'pending', 409, 'Pesanan tidak dapat memulai pembayaran baru.');
            $summary = $order->summary();
            $quote = $order->shipping_quote;
            if ($summary['total'] === null || !is_array($quote) || empty($quote['selected']) || empty($order->shipping_destination['district']['id'])) {
                throw ValidationException::withMessages(['shipping' => 'Pilih kutipan ongkir yang masih valid terlebih dahulu.']);
            }
            foreach (['nama_penerima','email_penerima','hp_penerima','alamat','pos'] as $field) {
                if (blank($order->$field)) throw ValidationException::withMessages(['recipient' => 'Penerima belum lengkap.']);
            }
            $items = $order->orderItems()->orderBy('produk_id')->get();
            if ($items->isEmpty()) throw ValidationException::withMessages(['order' => 'Keranjang kosong.']);
            $details = []; $snapshotItems = [];
            foreach ($items as $item) {
                $product = Produk::whereKey($item->produk_id)->lockForUpdate()->firstOrFail();
                if ((int) $product->status !== 1 || $item->quantity < 1 || $product->stok < $item->quantity) {
                    throw ValidationException::withMessages(['stock' => 'Produk atau stok tidak tersedia.']);
                }
                $price = $this->rupiah($item->harga);
                $details[] = ['id' => (string) $product->id, 'price' => $price, 'quantity' => (int) $item->quantity, 'name' => mb_substr($product->nama_produk, 0, 50)];
                $snapshotItems[] = ['produk_id' => $product->id, 'nama_produk' => $product->nama_produk, 'harga' => $price, 'quantity' => (int) $item->quantity, 'berat' => $product->berat];
            }
            abort_unless(hash_equals($quote['fingerprint'], $order->shippingFingerprint(true)), 409, 'Produk berubah saat pembayaran disiapkan. Hitung ulang ongkir.');
            $shipping = $this->rupiah($summary['shipping']);
            $amount = $this->rupiah($summary['total']);
            $details[] = ['id' => 'shipping', 'price' => $shipping, 'quantity' => 1, 'name' => 'Ongkos kirim'];
            abort_unless($amount > 0 && collect($details)->sum(fn ($row) => $row['price'] * $row['quantity']) === $amount, 422, 'Nominal pembayaran tidak valid.');
            $reference = 'TOKO-SB-'.Str::uuid();
            $snapshot = ['recipient' => $order->recipient(), 'items' => $snapshotItems, 'summary' => $summary,
                'destination' => $order->shipping_destination, 'quote' => $quote,
                'payload' => ['transaction_details' => ['order_id' => $reference, 'gross_amount' => $amount], 'item_details' => $details,
                    'customer_details' => ['first_name' => $order->nama_penerima, 'email' => $order->email_penerima, 'phone' => $order->hp_penerima],
                    'callbacks' => ['finish' => route('payment.show', $order->id)]]];
            $attempt = PaymentAttempt::create(['order_id' => $order->id, 'gateway_reference' => $reference, 'amount' => $amount, 'snapshot' => $snapshot]);
            foreach ($items as $item) {
                Produk::whereKey($item->produk_id)->decrement('stok', $item->quantity);
                DB::table('payment_reservations')->insert(['payment_attempt_id' => $attempt->id, 'produk_id' => $item->produk_id, 'quantity' => $item->quantity]);
            }
            $order->forceFill(['status' => 'awaiting_payment', 'payment_attempt_id' => $attempt->id, 'total_harga' => $summary['subtotal'], 'total_berat' => $summary['weight']])->save();
            return [$attempt, true];
        });
        // Only the request that created the durable attempt may send Create Snap.
        if ($created) {
            $token = $gateway->create($attempt->snapshot['payload']);
            DB::transaction(function () use ($attempt, $token) {
                $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if (in_array($locked->status, ['creating','unknown'], true)) {
                    $locked->update(['snap_token' => $token, 'status' => $token ? 'active' : 'unknown']);
                }
            });
        }
        $attempt->refresh();
        return response()->json(['payment_url' => route('payment.show', $attempt->order_id), 'status' => $attempt->status]);
    }

    public function show(Request $request, $id)
    {
        $order = $this->ownedOrder($request, $id);
        return view('v_order.payment', ['order' => $order, 'attempt' => $this->attemptFor($order) ?? (Schema::hasTable('payment_attempts') ? PaymentAttempt::where('order_id', $order->id)->latest('id')->first() : null),
            'clientKey' => !(bool) config('services.midtrans.is_production') && str_starts_with((string) config('services.midtrans.client_key'), 'SB-Mid-client-') ? config('services.midtrans.client_key') : '']);
    }

    public function resume(Request $request, $id, MidtransSandbox $gateway)
    {
        $order = $this->ownedOrder($request, $id);
        $this->requireSchema();
        $attempt = $this->attemptFor($order);
        abort_unless($attempt, 404);
        $data = $gateway->status($attempt->gateway_reference);
        if ($data === null) return response()->json(['message' => 'Status provider belum tersedia. Referensi dan stok tetap dipertahankan; 404 tidak membuktikan Snap dibatalkan. Catat referensi pada halaman pembayaran, minta pengelola menelusurinya di dashboard Sandbox/dukungan Midtrans, lalu periksa status kembali. Jangan membayar ulang.'], 409);
        $this->applyStatus($attempt, $data);
        return response()->json(['message' => 'Status diperiksa melalui Midtrans Sandbox.', 'status' => $order->fresh()->status]);
    }

    public function token(Request $request, $id, MidtransSandbox $gateway)
    {
        $gateway->assertConfigured();
        $order = $this->ownedOrder($request, $id);
        $attempt = $this->attemptFor($order);
        abort_unless($order->status === 'awaiting_payment' && $attempt && $attempt->status === 'active' && filled($attempt->snap_token), 409, 'Token belum tersedia; periksa status provider.');
        return response()->json(['token' => $attempt->snap_token]);
    }

    public function webhook(Request $request, MidtransSandbox $gateway)
    {
        $gateway->assertConfigured();
        $this->requireSchema();
        $input = $request->validate(['order_id' => ['required','string','max:64'], 'status_code' => ['required','string','max:3'],
            'gross_amount' => ['required','string','max:32'], 'signature_key' => ['required','string','size:128']]);
        $expected = hash('sha512', $input['order_id'].$input['status_code'].$input['gross_amount'].config('services.midtrans.server_key'));
        abort_unless(hash_equals($expected, $input['signature_key']), 403, 'Signature notifikasi tidak valid.');
        $attempt = PaymentAttempt::where('gateway_reference', $input['order_id'])->firstOrFail();
        abort_unless($this->sameAmount($input['gross_amount'], $attempt->amount), 409, 'Nominal notifikasi tidak sesuai.');
        $data = $gateway->status($attempt->gateway_reference);
        abort_if($data === null, 503, 'Status provider belum dapat diverifikasi. Silakan retry notifikasi.');
        $this->applyStatus($attempt, $data);
        return response()->json(['message' => 'Notifikasi terverifikasi diproses.']);
    }

    private function applyStatus(PaymentAttempt $attempt, array $data): void
    {
        abort_unless(($data['order_id'] ?? null) === $attempt->gateway_reference && $this->sameAmount($data['gross_amount'] ?? null, $attempt->amount)
            && (!isset($data['currency']) || $data['currency'] === 'IDR')
            && (!isset($data['merchant_id']) || !filled(config('services.midtrans.merchant_id')) || $data['merchant_id'] === config('services.midtrans.merchant_id'))
            && isset($data['transaction_id']) && is_string($data['transaction_id']) && strlen($data['transaction_id']) <= 255, 409, 'Identitas atau nominal provider tidak sesuai.');
        DB::transaction(function () use ($attempt, $data) {
            // All mutations acquire customer, order, attempt, then products in the same order.
            $orderInfo = Order::findOrFail($attempt->order_id);
            Customer::whereKey($orderInfo->customer_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $attempt = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_if($attempt->transaction_id && $attempt->transaction_id !== $data['transaction_id'], 409, 'Identitas transaksi berubah.');
            $status = $data['transaction_status'] ?? '';
            $fraud = $data['fraud_status'] ?? null;
            $success = ($status === 'settlement' || ($status === 'capture' && $fraud === 'accept')) && ($fraud === null || $fraud === 'accept');
            if ($attempt->status === 'paid') return; // Never downgrade Paid/Kirim/Selesai or touch stock twice.
            $attempt->transaction_id = $data['transaction_id'];
            $attempt->provider_status = in_array($status, ['settlement','capture','pending','deny','expire','cancel','failure','authorize','refund','partial_refund'], true) ? $status : 'unknown';
            $attempt->fraud_status = in_array($fraud, ['accept','deny','challenge'], true) ? $fraud : null;
            if ($success) {
                if ($attempt->stock_state !== 'reserved' || (int) $order->payment_attempt_id !== $attempt->id) {
                    // Provider success is never reclassified as failure on a local inconsistency.
                    $attempt->status = 'review';
                    $order->status = 'payment_review';
                    if (!$order->payment_attempt_id) $order->payment_attempt_id = $attempt->id;
                } else {
                    $attempt->status = 'paid';
                    $attempt->stock_state = 'consumed';
                    $order->status = 'Paid';
                }
            } elseif (in_array($status, ['deny','expire','cancel','failure'], true) && $attempt->stock_state === 'reserved') {
                $reservations = DB::table('payment_reservations')->where('payment_attempt_id', $attempt->id)->orderBy('produk_id')->get();
                foreach ($reservations as $reservation) {
                    $product = Produk::whereKey($reservation->produk_id)->lockForUpdate()->firstOrFail();
                    $product->increment('stok', $reservation->quantity);
                }
                $attempt->status = 'failed';
                $attempt->stock_state = 'released';
                if ((int) $order->payment_attempt_id === $attempt->id && $order->status === 'awaiting_payment') {
                    $order->forceFill(['status' => 'pending', 'payment_attempt_id' => null, 'shipping_quote' => null,
                        'kurir' => null, 'layanan_ongkir' => null, 'biaya_ongkir' => 0, 'estimasi_ongkir' => null]);
                }
            }
            $attempt->save();
            $order->save();
        });
    }

    private function sameAmount($value, int $amount): bool
    {
        return is_string($value) && preg_match('/^[0-9]+(?:\.0{1,2})?$/D', $value) && (float) $value === (float) $amount;
    }

    private function rupiah($value): int
    {
        if (!is_numeric($value) || !is_finite((float) $value) || $value < 0 || $value > 2000000000 || floor((float) $value) !== (float) $value) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus berupa rupiah bulat yang valid.']);
        }
        return (int) $value;
    }

    private function ownedOrder(Request $request, $id): Order
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();
        return Order::where('customer_id', $customer->id)->findOrFail($id);
    }

    private function attemptFor(Order $order): ?PaymentAttempt
    {
        return $order->payment_attempt_id ? PaymentAttempt::where('order_id', $order->id)->find($order->payment_attempt_id) : null;
    }

    private function requireSchema(): void
    {
        abort_unless(Schema::hasTable('payment_attempts') && Schema::hasTable('payment_reservations') && Schema::hasColumn('order','payment_attempt_id'), 503, 'Pembayaran menunggu penerapan migrasi additive pembayaran oleh pengelola.');
    }
}
