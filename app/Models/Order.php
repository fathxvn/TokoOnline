<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model 
{ 
    public $timestamps = true; 
    protected $table = "order"; 
    protected $fillable = [ 
        'customer_id', 
        'total_harga', 
        'status', 
        'noresi', 
        'kurir', 
        'layanan_ongkir', 
        'biaya_ongkir', 
        'estimasi_ongkir', 
        'total_berat', 
        'alamat', 
        'pos',
        'nama_penerima',
        'email_penerima',
        'hp_penerima',
        'shipping_destination',
        'shipping_quote',
    ]; 
 
    protected $casts = ['shipping_destination' => 'array', 'shipping_quote' => 'array'];

    public function orderItems() 
    { 
        return $this->hasMany(OrderItem::class); 
    } 
 
    public function customer() 
    { 
        return $this->belongsTo(Customer::class); 
    } 

    public function scopeTransactions($query)
    {
        return $query->whereIn(DB::raw('LOWER(status)'), ['paid', 'kirim', 'selesai']);
    }

    public function getStatusLabelAttribute(): string
    {
        return match (strtolower($this->status)) {
            'pending' => 'Keranjang — belum dibayar',
            'awaiting_payment' => 'Menunggu pembayaran Sandbox',
            'payment_review' => 'Pembayaran terverifikasi — perlu rekonsiliasi pengelola',
            'paid' => 'Dibayar — menunggu pengiriman',
            'kirim' => 'Dikirim',
            'selesai' => 'Selesai',
            default => 'Status belum dikenali',
        };
    }

    public function paymentSnapshot(): ?array
    {
        if ($this->status === 'pending' || !$this->payment_attempt_id) return null;
        return PaymentAttempt::where('order_id', $this->id)->find($this->payment_attempt_id)?->snapshot;
    }

    public function recipient(): array
    {
        if ($snapshot = $this->paymentSnapshot()) return $snapshot['recipient'];
        $user = $this->customer?->user;

        return [
            'nama_penerima' => $this->nama_penerima ?? $user?->nama,
            'email_penerima' => $this->email_penerima ?? $user?->email,
            'hp_penerima' => $this->hp_penerima ?? $user?->hp,
            'alamat' => $this->alamat ?? $this->customer?->alamat,
            'pos' => $this->pos ?? $this->customer?->pos,
        ];
    }

    public function shippingFingerprint(bool $lockProducts = false): string
    {
        $items = $this->orderItems()->with('produk')->orderBy('id')->get();
        if ($lockProducts) {
            foreach ($items as $item) {
                $item->setRelation('produk', Produk::whereKey($item->produk_id)->lockForUpdate()->first());
            }
        }
        $data = [
            'origin' => (string) config('services.rajaongkir.origin_district'),
            'couriers' => config('services.rajaongkir.couriers', []),
            'recipient' => [$this->nama_penerima, $this->email_penerima, $this->hp_penerima, $this->alamat, $this->pos],
            'destination' => $this->shipping_destination,
            'items' => $items->map(fn ($item) => [$item->id, $item->produk_id, $item->quantity, $item->harga, $item->produk?->berat, $item->produk?->status])->all(),
        ];
        // MySQL JSON objects may reorder keys; fingerprints compare content, not key order.
        $canonical = function ($value) use (&$canonical) {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value);
            return array_map($canonical, $value);
        };
        return hash('sha256', json_encode($canonical($data), JSON_THROW_ON_ERROR));
    }

    public function summary(): array
    {
        if ($snapshot = $this->paymentSnapshot()) return $snapshot['summary'];
        $this->loadMissing('orderItems.produk');
        $itemSubtotal = round($this->orderItems->sum(fn ($item) => $item->harga * $item->quantity), 2);
        $subtotal = strtolower($this->status) === 'pending' ? $itemSubtotal : (float) $this->total_harga;
        $shipping = filled($this->kurir) && filled($this->layanan_ongkir)
            && is_numeric($this->biaya_ongkir) && $this->biaya_ongkir >= 0
                ? (float) $this->biaya_ongkir : null;

        if (strtolower($this->status) === 'pending') {
            $quote = $this->shipping_quote;
            if (!is_array($quote) || !isset($quote['selected'], $quote['expires_at'], $quote['fingerprint'])
                || $quote['expires_at'] <= now()->timestamp
                || !hash_equals($quote['fingerprint'], $this->shippingFingerprint())) {
                $shipping = null;
            }
        }

        return [
            'quantity' => $this->orderItems->sum('quantity'),
            'weight' => strtolower($this->status) === 'pending'
                ? (int) ceil($this->orderItems->sum(fn ($item) => ($item->produk?->berat ?? 0) * $item->quantity))
                : $this->total_berat,
            'subtotal' => $subtotal,
            'items_match' => abs($subtotal - $itemSubtotal) < 0.01,
            'shipping' => $shipping,
            'total' => $shipping === null ? null : round($subtotal + $shipping, 2),
        ];
    }
} 
