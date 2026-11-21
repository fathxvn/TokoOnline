@extends('backend.v_layouts.app')
@section('content')
<div class="card"><div class="card-body">
    <h4 class="card-title">Pesanan</h4>
    <p>Daftar utama berisi transaksi Paid, Kirim, dan Selesai. Keranjang pending hanya tersedia melalui filter dan tidak dapat diproses pengiriman.</p>
    <form method="GET" action="{{ route('backend.pesanan.index') }}" class="mb-3">
        <label for="status">Status pesanan</label>
        <select id="status" name="status" class="form-control mb-2">
            <option value="">Semua transaksi</option>
            @foreach(['pending' => 'Keranjang pending', 'awaiting_payment' => 'Menunggu pembayaran', 'payment_review' => 'Perlu rekonsiliasi', 'Paid' => 'Dibayar', 'Kirim' => 'Dikirim', 'Selesai' => 'Selesai'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <a class="btn btn-outline-secondary" href="{{ route('backend.pesanan.chart') }}">Grafik Pesanan</a>
    </form>
    <div class="table-responsive"><table class="table table-bordered">
        <thead><tr><th>Pesanan</th><th>Tanggal</th><th>Pelanggan</th><th>Status</th><th>Subtotal tersimpan</th><th>Kurir / Resi</th><th>Aksi</th></tr></thead>
        <tbody>
        @forelse($orders as $order)
            <tr><td>#{{ $order->id }}</td><td>{{ $order->created_at->format('d/m/Y H:i') }}</td><td>{{ $order->nama_penerima ?? $order->customer?->user?->nama ?? 'Akun tidak tersedia' }}</td><td>{{ $order->status_label }}</td><td>Rp {{ number_format($order->total_harga, 0, ',', '.') }}</td><td>{{ $order->kurir ?: 'Belum tersedia' }} / {{ $order->noresi ?: 'Belum tersedia' }}</td><td><a href="{{ route('backend.pesanan.show', $order) }}">Detail</a></td></tr>
        @empty
            <tr><td colspan="7">Belum ada pesanan pada pilihan ini.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $orders->links('pagination::bootstrap-4') }}
</div></div>
@endsection
