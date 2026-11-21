@extends('v_layouts.app')
@section('content')
<div class="store-order">
    <div class="section-title"><h3 class="title">Riwayat Pesanan</h3></div>
    <p>Keranjang pending tidak termasuk riwayat transaksi. <a href="{{ route('order.cart') }}">Lihat keranjang</a>.</p>
    @if($waitingOrders->isNotEmpty())
        <h4>Pembayaran belum selesai (bukan penjualan sukses)</h4>
        <ul>@foreach($waitingOrders as $waiting)<li>#{{ $waiting->id }} — {{ $waiting->status_label }} <a href="{{ route('payment.show', $waiting->id) }}">Lanjutkan / Periksa</a></li>@endforeach</ul>
    @endif
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>Pesanan</th><th>Tanggal</th><th>Status</th><th>Subtotal tersimpan</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td><td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $order->status_label }}</td><td>Rp {{ number_format($order->total_harga, 0, ',', '.') }}</td>
                        <td><a href="{{ route('order.show', $order->id) }}">Detail</a> · <a href="{{ route('order.invoice', $order->id) }}">Invoice</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">Anda belum memiliki riwayat pesanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links('vendor.pagination.custom') }}
</div>
@endsection
