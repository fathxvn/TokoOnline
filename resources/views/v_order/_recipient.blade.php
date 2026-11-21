<dl class="order-recipient">
    <dt>Nama penerima</dt><dd>{{ $recipient['nama_penerima'] ?: 'Belum tersedia' }}</dd>
    <dt>Email</dt><dd>{{ $recipient['email_penerima'] ?: 'Belum tersedia' }}</dd>
    <dt>Nomor HP</dt><dd>{{ $recipient['hp_penerima'] ?: 'Belum tersedia' }}</dd>
    <dt>Alamat</dt><dd style="white-space: pre-line">{{ $recipient['alamat'] ?: 'Belum tersedia' }}</dd>
    <dt>Kode pos</dt><dd>{{ $recipient['pos'] ?: 'Belum tersedia' }}</dd>
</dl>
@if(!$order->nama_penerima)
    <p class="text-muted">Nama, email, dan HP menggunakan profil saat ini; snapshot penerima belum tersimpan pada pesanan ini.</p>
@endif
