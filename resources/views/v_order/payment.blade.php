@extends('v_layouts.app')
@section('content')
<div class="store-order">
    <h3>Pembayaran Sandbox #{{ $order->id }}</h3>
    <p>{{ $order->status_label }}</p>
    @php($summary = $order->summary())
    @include('v_order._totals')
    @if($attempt)
        <p>Referensi: {{ $attempt->gateway_reference }} · Status percobaan: {{ $attempt->status }}</p>
        @if($order->status === 'awaiting_payment')
            <p>Item, penerima, ongkir, dan nominal dibekukan. Menutup popup tidak membatalkan pembayaran.</p>
            @if($attempt->status === 'active' && $clientKey)
                <button id="resume-payment" class="primary-btn" type="button">Lanjutkan Snap Sandbox</button>
            @else
                <p>Hasil pembuatan token belum pasti. Jangan membuat pembayaran baru. Gunakan Periksa Status; 404 provider belum berarti Snap tidak ada. Jika tetap ambigu, minta pengelola memeriksa referensi pada dashboard Sandbox.</p>
            @endif
        @endif
        @if($attempt->status === 'failed')
            <p>Pembayaran terakhir gagal/dibatalkan/kedaluwarsa menurut provider: {{ $attempt->provider_status }}. Reservasi stok telah dilepas. <a href="{{ route('order.cart') }}">Kembali ke keranjang dan hitung ulang ongkir untuk mencoba lagi</a>.</p>
        @elseif($order->status === 'payment_review')
            <p>Pembayaran provider terverifikasi, tetapi data lokal memerlukan rekonsiliasi pengelola. Gunakan referensi di atas; jangan membuat pembayaran baru.</p>
        @endif
        @if(in_array($attempt->status, ['creating', 'unknown', 'review'], true) || $order->status === 'payment_review')
            <div class="alert alert-warning" role="alert">
                <strong>Pembayaran perlu ditelusuri; jangan membayar ulang.</strong>
                <ol>
                    <li>Catat referensi {{ $attempt->gateway_reference }} dan waktu {{ $attempt->created_at->format('d/m/Y H:i') }}.</li>
                    <li>Tekan Periksa Status Midtrans. Timeout atau 404 bukan bukti batal; stok tetap dicadangkan bila belum ada status terminal yang terverifikasi.</li>
                    <li>Minta pengelola mencari referensi yang sama di dashboard Sandbox. Jika belum ditemukan atau hasil bertentangan, hubungi dukungan Midtrans memakai referensi tersebut, bukan Server Key/token.</li>
                    <li>Setelah provider memastikan status, lakukan sinkronisasi kembali. Keranjang hanya dibuka dan stok dikembalikan setelah kegagalan/kedaluwarsa terverifikasi; jangan mengubah SQL atau membuat tagihan baru.</li>
                </ol>
                <p>Jika status tetap tidak pasti atau memerlukan rekonsiliasi lokal, pengelola perlu menindaklanjutinya dengan provider. Tidak tersedia pelepasan stok paksa dari halaman ini.</p>
            </div>
        @endif
        @if($order->payment_attempt_id)
        <button id="sync-payment" type="button" class="main-btn">Periksa Status Midtrans</button>
        @endif
    @else
        <p>Belum ada pembayaran aktif. <a href="{{ route('order.cart') }}">Kembali ke keranjang</a>.</p>
    @endif
    <p id="payment-message" role="status"></p>
    <a href="{{ route('order.history') }}">Riwayat</a> · <a href="{{ route('order.invoice', $order->id) }}">Ringkasan / Invoice</a>
</div>
@if($clientKey && $order->status === 'awaiting_payment')
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ $clientKey }}"></script>
@endif
<script>
(function () {
    const message = document.getElementById('payment-message');
    async function post(url) {
        const response = await fetch(url, {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())}});
        const data = await response.json().catch(function () { return {}; });
        if (!response.ok) throw new Error(data.message || 'Permintaan gagal. Coba periksa status kembali.');
        return data;
    }
    async function sync() {
        message.textContent = 'Memverifikasi status di backend...';
        try { await post(@json(route('payment.sync', $order->id))); window.location.reload(); }
        catch (error) { message.textContent = error.message; }
    }
    const syncButton = document.getElementById('sync-payment');
    if (syncButton) syncButton.addEventListener('click', sync);
    const resume = document.getElementById('resume-payment');
    if (resume) resume.addEventListener('click', async function () {
        resume.disabled = true; message.textContent = 'Membuka Snap Sandbox...';
        try {
            const data = await post(@json(route('payment.token', $order->id)));
            if (!window.snap) throw new Error('Snap belum dimuat. Periksa koneksi dan muat ulang.');
            window.snap.pay(data.token, {onSuccess:sync, onPending:sync, onError:sync, onClose:function () { message.textContent='Popup ditutup. Pembayaran tidak otomatis dibatalkan; periksa status atau lanjutkan.'; resume.disabled=false; }});
        } catch (error) { message.textContent=error.message; resume.disabled=false; }
    });
    // A browser redirect is only a cue to query the backend, never proof of payment.
    if (syncButton && window.location.search) sync();
})();
</script>
@endsection
