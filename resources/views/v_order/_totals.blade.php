<dl class="order-totals">
    <dt>Jumlah item</dt><dd>{{ $summary['quantity'] }}</dd>
    <dt>Total berat</dt><dd>{{ $summary['weight'] }} gram</dd>
    <dt>Subtotal produk</dt><dd>Rp {{ number_format($summary['subtotal'], 0, ',', '.') }}</dd>
    <dt>Ongkos kirim</dt><dd>{{ $summary['shipping'] === null ? 'Belum ditentukan' : 'Rp '.number_format($summary['shipping'], 0, ',', '.') }}</dd>
    <dt>Total termasuk ongkir</dt><dd>{{ $summary['total'] === null ? 'Menunggu pilihan pengiriman' : 'Rp '.number_format($summary['total'], 0, ',', '.') }}</dd>
</dl>
