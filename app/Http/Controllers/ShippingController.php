<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingController extends Controller
{
    public function quote(Request $request, $id)
    {
        $order = $this->ownedOrder($request, $id);
        $this->requireSchema();
        abort_unless(ctype_digit((string) config('services.rajaongkir.origin_district')) && (int) config('services.rajaongkir.origin_district') > 0
            && count(config('services.rajaongkir.couriers', [])) > 0, 503, 'Konfigurasi asal kecamatan atau kurir RajaOngkir belum lengkap.');
        $input = $request->validate([
            'province_id' => ['required', 'integer', 'min:1'],
            'city_id' => ['required', 'integer', 'min:1'],
            'district_id' => ['required', 'integer', 'min:1'],
        ]);
        $api = app(RajaOngkirController::class);
        $province = collect($api->locations('destination/province'))->firstWhere('id', (string) $input['province_id']);
        $city = $province ? collect($api->locations('destination/city/'.$province['id']))->firstWhere('id', (string) $input['city_id']) : null;
        $district = $city ? collect($api->locations('destination/district/'.$city['id']))->firstWhere('id', (string) $input['district_id']) : null;
        if (!$province || !$city || !$district) throw ValidationException::withMessages(['district_id' => 'Hubungan provinsi, kota, dan kecamatan tidak sesuai.']);
        $destination = compact('province', 'city', 'district');
        // Persist a new destination and invalidate the old selection before requesting a replacement.
        $snapshot = DB::transaction(function () use ($request, $id, $destination) {
            $order = $this->lockedOrder($request, $id);
            $this->validateCart($order);
            $order->forceFill(['shipping_destination' => $destination, 'shipping_quote' => null,
                'kurir' => null, 'layanan_ongkir' => null, 'biaya_ongkir' => 0, 'estimasi_ongkir' => null])->save();
            return ['fingerprint' => $order->shippingFingerprint(), 'weight' => $order->summary()['weight']];
        });
        $rates = $api->rates([
            'origin' => (int) config('services.rajaongkir.origin_district'), 'destination' => (int) $district['id'],
            'weight' => $snapshot['weight'], 'courier' => implode(':', config('services.rajaongkir.couriers')), 'price' => 'lowest',
        ]);
        $quote = DB::transaction(function () use ($request, $id, $snapshot, $rates) {
            $order = $this->lockedOrder($request, $id);
            $this->validateCart($order);
            abort_unless(hash_equals($snapshot['fingerprint'], $order->shippingFingerprint()), 409, 'Keranjang atau penerima berubah. Hitung ulang ongkir.');
            $quote = [
                'fingerprint' => $snapshot['fingerprint'], 'origin' => (string) config('services.rajaongkir.origin_district'),
                'weight' => $snapshot['weight'], 'destination' => $order->shipping_destination,
                'expires_at' => now()->addMinutes(10)->timestamp,
                'options' => array_map(fn ($rate) => ['id' => (string) Str::uuid()] + $rate, $rates),
            ];
            $order->update(['shipping_quote' => $quote]);
            return $quote;
        });
        return response()->json(['options' => $quote['options'], 'expires_at' => $quote['expires_at']]);
    }

    public function select(Request $request, $id)
    {
        $this->ownedOrder($request, $id);
        $this->requireSchema();
        $input = $request->validate(['option_id' => ['required', 'uuid']]);
        $summary = DB::transaction(function () use ($request, $id, $input) {
            $order = $this->lockedOrder($request, $id);
            $this->validateCart($order);
            $quote = $order->shipping_quote;
            abort_unless(is_array($quote) && isset($quote['expires_at'], $quote['fingerprint'])
                && $quote['expires_at'] > now()->timestamp && hash_equals($quote['fingerprint'], $order->shippingFingerprint()), 409, 'Kutipan ongkir kedaluwarsa atau data berubah. Hitung ulang ongkir.');
            $option = collect($quote['options'])->firstWhere('id', $input['option_id']);
            if (!$option) throw ValidationException::withMessages(['option_id' => 'Layanan tidak termasuk kutipan ongkir saat ini.']);
            $quote['selected'] = $option['id'];
            $order->update(['shipping_quote' => $quote, 'kurir' => $option['code'], 'layanan_ongkir' => $option['service'],
                'biaya_ongkir' => $option['cost'], 'estimasi_ongkir' => $option['etd'] ?: null,
                'total_harga' => $order->summary()['subtotal'], 'total_berat' => $quote['weight']]);
            return $order->fresh()->summary();
        });
        return response()->json(['message' => 'Layanan pengiriman disimpan. Pesanan belum dibayar.', 'summary' => $summary]);
    }

    private function ownedOrder(Request $request, $id): Order
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();
        return Order::where('customer_id', $customer->id)->where('status', 'pending')->findOrFail($id);
    }

    private function lockedOrder(Request $request, $id): Order
    {
        $customer = Customer::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
        return Order::where('customer_id', $customer->id)->where('status', 'pending')->lockForUpdate()->findOrFail($id);
    }

    private function requireSchema(): void
    {
        abort_unless(Schema::hasColumns('order', ['shipping_destination', 'shipping_quote']), 503, 'Pengiriman menunggu penerapan migrasi additive kutipan ongkir.');
    }

    private function validateCart(Order $order): void
    {
        if (collect([$order->nama_penerima, $order->email_penerima, $order->hp_penerima, $order->alamat, $order->pos])->contains(fn ($value) => blank($value))) {
            throw ValidationException::withMessages(['recipient' => 'Simpan data penerima lengkap terlebih dahulu.']);
        }
        $items = $order->orderItems()->get();
        if ($items->isEmpty()) throw ValidationException::withMessages(['order' => 'Keranjang belanja kosong.']);
        foreach ($items as $item) {
            $product = Produk::whereKey($item->produk_id)->lockForUpdate()->first();
            if (!$product || (int) $product->status !== 1 || $item->quantity < 1 || $item->quantity > $product->stok || $product->berat <= 0) {
                throw ValidationException::withMessages(['order' => 'Produk, stok, jumlah, atau berat tidak valid. Periksa keranjang.']);
            }
        }
    }
}
