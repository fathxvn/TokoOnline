<div class="table-responsive">
    <table class="table table-bordered order-items">
        <thead><tr><th>Produk</th><th>Jumlah</th><th>Berat per item</th><th>Harga item</th><th>Subtotal</th></tr></thead>
        <tbody>
            @php($paymentItems = $order->paymentSnapshot()['items'] ?? null)
            @forelse($paymentItems ?? $order->orderItems as $item)
                <tr>
                    <td>{{ is_array($item) ? $item['nama_produk'] : ($item->produk->nama_produk ?? 'Produk tidak tersedia') }}</td>
                    <td>{{ is_array($item) ? $item['quantity'] : $item->quantity }}</td>
                    <td>{{ is_array($item) ? $item['berat'] : ($item->produk->berat ?? '—') }} gram</td>
                    <td>Rp {{ number_format(is_array($item) ? $item['harga'] : $item->harga, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format(is_array($item) ? $item['harga'] * $item['quantity'] : $item->harga * $item->quantity, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Item pesanan tidak tersedia.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if(!$summary['items_match'])
    <p class="text-danger">Rincian item yang tersedia berbeda dari subtotal transaksi tersimpan. Subtotal mengikuti catatan transaksi; hubungi pengelola untuk pemeriksaan arsip.</p>
@endif
