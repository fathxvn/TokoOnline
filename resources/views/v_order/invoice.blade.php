<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice #{{ $order->id }} — {{ config('app.name') }}</title>
    <style>
        body{font:15px/1.5 Arial,sans-serif;color:#222;margin:0;padding:24px;overflow-wrap:anywhere}main{max-width:960px;margin:auto}h1{font-size:25px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:9px;text-align:left;vertical-align:top}.table-responsive{overflow-x:auto}.order-items{min-width:580px}dl{margin:15px 0}dt{font-weight:bold}dd{margin:0 0 10px}.notice{padding:12px;border:1px solid #aaa}.actions{margin-bottom:20px}button,a{font:inherit}button{cursor:pointer}.text-muted{color:#555}@media(max-width:480px){body{padding:12px}}@media print{.actions{display:none}body{padding:0;font-size:11pt}.table-responsive{overflow:visible}.order-items{min-width:0}tr{break-inside:avoid}thead{display:table-header-group}h2{break-after:avoid}a{color:inherit;text-decoration:none}}
    </style>
</head>
<body>
<main>
    <div class="actions"><a href="{{ $backUrl }}">Kembali</a> <button type="button" onclick="window.print()">Cetak Invoice</button></div>
    <h1>{{ config('app.name') }} — {{ !in_array(strtolower($order->status), ['paid', 'kirim', 'selesai'], true) ? 'Draf Pesanan' : 'Invoice' }} #{{ $order->id }}</h1>
    <p>Tanggal pesanan: {{ $order->created_at->format('d/m/Y H:i') }}<br>Status: {{ $order->status_label }}</p>
    <p class="notice">Dokumen ini merangkum pesanan dan bukan bukti pembayaran. {{ !in_array(strtolower($order->status), ['paid', 'kirim', 'selesai'], true) ? 'Pesanan ini belum dibayar.' : 'Status mengikuti catatan pesanan yang tersimpan.' }}</p>
    <h2>Penerima</h2>
    @include('v_order._recipient')
    <h2>Rincian item</h2>
    @include('v_order._items')
    @include('v_order._totals')
    <h2>Pengiriman</h2>
    <p>Kurir: {{ $order->kurir ?: 'Belum ditentukan' }}<br>Layanan: {{ $order->layanan_ongkir ?: 'Belum ditentukan' }}<br>Estimasi: {{ $order->estimasi_ongkir ?: 'Belum tersedia' }}<br>No. resi: {{ $order->noresi ?: 'Belum tersedia' }}</p>
</main>
</body>
</html>
