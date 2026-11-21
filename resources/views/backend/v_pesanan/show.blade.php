@extends('backend.v_layouts.app')
@section('content')
<div class="card"><div class="card-body">
    <h4 class="card-title">Pesanan #{{ $order->id }}</h4>
    <p>{{ $order->created_at->format('d/m/Y H:i') }} · {{ $order->status_label }}</p>
    @if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    @if($paymentAttempts->isNotEmpty())
        <h5>Percobaan pembayaran Sandbox</h5>
        <ul>@foreach($paymentAttempts as $payment)<li>{{ $payment->gateway_reference }} — {{ $payment->status }} / {{ $payment->provider_status ?: 'belum terverifikasi' }} · stok {{ $payment->stock_state }}</li>@endforeach</ul>
        @if($order->status === 'payment_review')<p class="text-danger">Pembayaran provider terverifikasi tetapi data lokal perlu rekonsiliasi. Jangan memaksa Paid atau mengubah stok lewat SQL.</p>@endif
    @endif
    <h5>Penerima</h5>
    @include('v_order._recipient')
    <h5>Item</h5>
    @include('v_order._items')
    @include('v_order._totals')
    <h5>Pengiriman</h5>
    <p>Kurir: {{ $order->kurir ?: 'Belum ditentukan' }}<br>Layanan: {{ $order->layanan_ongkir ?: 'Belum ditentukan' }}<br>Estimasi: {{ $order->estimasi_ongkir ?: 'Belum tersedia' }}<br>Resi: {{ $order->noresi ?: 'Belum tersedia' }}</p>
    @if(in_array(strtolower($order->status), ['paid', 'kirim'], true))
        <p>Kurir, layanan, dan biaya mengikuti pilihan pengiriman yang tersimpan. Halaman ini tidak mengubah pembayaran atau menentukan tarif ongkir.</p>
        <form action="{{ route('backend.pesanan.shipping', $order) }}" method="POST" class="mb-3">
            @csrf
            @method('PATCH')
            @if(strtolower($order->status) === 'paid')
                <input type="hidden" name="status" value="Kirim">
                <div class="form-group"><label for="noresi">Nomor resi</label><input id="noresi" name="noresi" class="form-control" maxlength="255" value="{{ old('noresi') }}" required></div>
                <button class="btn btn-primary" type="submit" @disabled($summary['shipping'] === null || collect($recipient)->contains(fn ($value) => blank($value)) || $order->orderItems->isEmpty())>Tandai Dikirim</button>
            @else
                <input type="hidden" name="status" value="Selesai">
                <button class="btn btn-primary" type="submit" @disabled(blank($order->noresi))>Tandai Selesai</button>
            @endif
        </form>
        @if($summary['shipping'] === null)<div class="alert alert-warning">Pengiriman belum lengkap. Pilihan ongkir harus berasal dari integrasi yang tervalidasi.</div>@endif
    @else
        <div class="alert alert-info">Tidak ada perubahan pengiriman yang tersedia untuk status ini.</div>
    @endif
    <a class="btn btn-secondary" href="{{ route('backend.pesanan.index') }}">Kembali</a>
    <a class="btn btn-outline-primary" href="{{ route('backend.pesanan.invoice', $order) }}">Invoice / Cetak</a>
</div></div>
@endsection
