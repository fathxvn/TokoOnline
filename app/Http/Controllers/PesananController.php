<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PesananController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['status' => ['nullable', Rule::in(['pending', 'awaiting_payment', 'payment_review', 'Paid', 'Kirim', 'Selesai'])]]);
        $status = $validated['status'] ?? null;
        $query = Order::with('customer.user');
        if ($status) {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        } else {
            $query->transactions();
        }

        return view('backend.v_pesanan.index', [
            'judul' => 'Pesanan', 'orders' => $query->latest('id')->paginate(10)->withQueryString(), 'status' => $status,
        ]);
    }

    public function show(Order $order)
    {
        $order->load('orderItems.produk', 'customer.user');

        return view('backend.v_pesanan.show', [
            'judul' => 'Detail Pesanan', 'order' => $order, 'recipient' => $order->recipient(), 'summary' => $order->summary(),
            'paymentAttempts' => Schema::hasTable('payment_attempts') ? PaymentAttempt::where('order_id', $order->id)->latest('id')->get(['gateway_reference', 'status', 'stock_state', 'provider_status']) : collect(),
        ]);
    }

    public function updateShipping(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Kirim', 'Selesai'])],
            'noresi' => ['required_if:status,Kirim', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._\/-]*$/'],
        ]);
        DB::transaction(function () use ($order, $validated) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = strtolower($order->status);
            $target = $validated['status'];
            $validTransition = ($current === 'paid' && $target === 'Kirim') || ($current === 'kirim' && $target === 'Selesai');
            $recipient = $order->recipient();
            if (!$validTransition || $order->orderItems()->doesntExist()
                || collect($recipient)->contains(fn ($value) => blank($value))
                || !filled($order->kurir) || !filled($order->layanan_ongkir)
                || !is_numeric($order->biaya_ongkir) || $order->biaya_ongkir < 0
                || ($target === 'Selesai' && blank($order->noresi))) {
                throw ValidationException::withMessages(['status' => 'Pesanan belum memenuhi syarat atau transisi status tidak diizinkan.']);
            }
            $order->status = $target;
            if ($target === 'Kirim') {
                $order->noresi = $validated['noresi'];
            }
            $order->save();
        });

        return redirect()->route('backend.pesanan.show', $order)->with('success', 'Status pengiriman berhasil diperbarui.');
    }

    public function invoice(Order $order)
    {
        $order->load('orderItems.produk', 'customer.user');

        return view('v_order.invoice', [
            'order' => $order, 'recipient' => $order->recipient(), 'summary' => $order->summary(),
            'backUrl' => route('backend.pesanan.show', $order),
        ]);
    }

    public function chart(Request $request)
    {
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($validated['year'] ?? now()->year);
        $orders = Order::transactions()->whereYear('created_at', $year)->get(['created_at']);
        $counts = array_fill(1, 12, 0);
        foreach ($orders as $order) {
            $counts[(int) $order->created_at->format('n')]++;
        }

        return view('backend.v_pesanan.chart', ['judul' => 'Grafik Pesanan', 'year' => $year, 'counts' => $counts]);
    }
}
