@extends('v_layouts.app')
@section('content')
<div class="store-order">
    <div class="section-title"><h3 class="title">Pesanan #{{ $order->id }}</h3></div>
    <p>{{ $order->created_at->format('d/m/Y H:i') }} · {{ $order->status_label }}</p>
    <h4>Penerima</h4>
    @include('v_order._recipient')
    <h4>Item pesanan</h4>
    @include('v_order._items')
    @include('v_order._totals')
    <h4>Pengiriman</h4>
    <p>Kurir: {{ $order->kurir ?: 'Belum ditentukan' }} · Layanan: {{ $order->layanan_ongkir ?: 'Belum ditentukan' }}</p>
    <p>Estimasi: {{ $order->estimasi_ongkir ?: 'Belum tersedia' }} · No. resi: {{ $order->noresi ?: 'Belum tersedia' }}</p>
    <div class="order-actions"><a class="main-btn" href="{{ route('order.history') }}">Kembali ke Riwayat</a> <a class="primary-btn" href="{{ route('order.invoice', $order->id) }}">Invoice / Cetak</a></div>
</div>
@endsection
