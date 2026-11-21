@extends('v_layouts.app')
@section('content')
<div class="store-order">
    <div class="order-summary clearfix">
        <div class="section-title">
            <p>KERANJANG</p>
            <h3 class="title">Keranjang Belanja</h3>
        </div>
        @if(session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        @if($order && $order->orderItems->isNotEmpty())
            @php
                $totalHarga = 0;
                $totalBerat = 0;
            @endphp
            <div class="table-responsive">
            <table class="shopping-cart-table table">
                <thead>
                    <tr>
                        <th colspan="2">Produk</th>
                        <th class="text-center">Harga</th>
                        <th class="text-center">Jumlah</th>
                        <th class="text-center">Subtotal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->orderItems as $item)
                        @php
                            $subtotal = $item->harga * $item->quantity;
                            $totalHarga += $subtotal;
                            $totalBerat += $item->produk->berat * $item->quantity;
                        @endphp
                        <tr>
                            <td class="thumb"><img src="{{ asset('storage/img-produk/thumb_sm_'.$item->produk->foto) }}" alt="{{ $item->produk->nama_produk }}"></td>
                            <td class="details">
                                <a href="{{ route('produk.detail', $item->produk_id) }}">{{ $item->produk->nama_produk }}</a>
                                <ul>
                                    <li>Berat: {{ $item->produk->berat }} Gram</li>
                                    <li>Stok: {{ $item->produk->stok }}</li>
                                </ul>
                            </td>
                            <td class="price text-center">Rp. {{ number_format($item->harga, 0, ',', '.') }}</td>
                            <td class="qty text-center">
                                <form action="{{ route('order.updateQuantity', $item->id) }}" method="post">
                                    @csrf
                                    @method('patch')
                                    <label class="sr-only" for="quantity-{{ $item->id }}">Jumlah {{ $item->produk->nama_produk }}</label>
                                    <input id="quantity-{{ $item->id }}" class="input" type="number" name="quantity" min="1" max="{{ max(1, $item->produk->stok) }}" value="{{ $item->quantity }}" required>
                                    <button type="submit" class="main-btn">Ubah</button>
                                </form>
                            </td>
                            <td class="text-center">Rp. {{ number_format($subtotal, 0, ',', '.') }}</td>
                            <td>
                                <form action="{{ route('order.removeItem', $item->id) }}" method="post">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="main-btn" aria-label="Hapus {{ $item->produk->nama_produk }}">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="4">Total</th><td colspan="2">Rp. {{ number_format($totalHarga, 0, ',', '.') }}</td></tr>
                    <tr><th colspan="4">Total Berat</th><td colspan="2">{{ $totalBerat }} Gram</td></tr>
                </tfoot>
            </table>
            </div>
            <div class="pull-right">
                <p>Pembayaran dan pilihan ongkir menunggu integrasi.</p>
                <a class="primary-btn" href="{{ route('order.checkout', $order->id) }}">Ringkasan &amp; Data Penerima</a>
            </div>
        @else
            <p>Keranjang belanja kosong.</p>
        @endif
    </div>
</div>
@endsection
