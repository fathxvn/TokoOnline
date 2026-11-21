# TokoOnline — panduan penyerahan

Aplikasi Laravel 10 dengan katalog, akun pelanggan, keranjang, checkout, ongkir,
pembayaran Midtrans Sandbox, dan pengelolaan pesanan. Frontend memakai E-Shop;
backend memakai Matrix. Atribusi template dan CSS backend manual dipertahankan.

## Setup Windows/XAMPP dari ZIP

Panduan ini memakai **PowerShell** dan server demo Laravel. Docker tidak diperlukan.
XAMPP di Windows **belum diuji langsung oleh pengembang**; lakukan pemeriksaan
platform pada komputer klien sebelum menjalankan aplikasi.

### 1. Siapkan PHP XAMPP dan Composer

- Gunakan PHP **8.1–8.3**; PHP **8.3** paling dekat dengan lingkungan pengembangan.
  Rentang ini mengikuti gabungan constraint `composer.lock`, bukan hanya Laravel.
  Laravel terkunci pada **10.48.22**. Jangan memakai PHP 8.4 dengan lock ini tanpa kajian.
- Pasang Composer 2 dan pilih `C:\xampp\php\php.exe` sebagai PHP saat instalasi
  Composer. Sesuaikan jalur jika XAMPP dipasang di lokasi lain.
- Kebutuhan paket terkunci: ctype, cURL, DOM/libxml, fileinfo, filter, hash, JSON,
  mbstring, OpenSSL, PCRE, Phar, session, tokenizer, XML, dan XMLWriter.
  Aplikasi juga membutuhkan **PDO MySQL dan GD** untuk database/gambar.
  PDO SQLite diperlukan hanya untuk tes. ZIP membantu Composer mengekstrak paket;
  intl disarankan. Sebagian extension sudah bawaan PHP, bukan baris yang perlu ditambah.
- Jika extension belum aktif, edit `C:\xampp\php\php.ini` yang benar dan aktifkan
  extension yang tersedia; jangan mengunduh DLL sembarang. Restart proses PHP setelahnya.

Buka PowerShell dan dahulukan PHP XAMPP **untuk terminal ini**:

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
where.exe php
php -v
php --ini
php -m
composer --version
```

Pastikan PHP pertama yang ditemukan adalah PHP XAMPP. Composer dan Artisan harus
menggunakan PHP yang sama. Jangan mengabaikan error extension/platform Composer.

### 2. Ekstrak ZIP dan buka folder aplikasi

Ekstrak `TokoOnline.zip`, misalnya ke `C:\xampp\htdocs`. Buka folder hasil ekstrak
sampai terlihat file **`artisan`**, `composer.json`, dan `.env.example`.
Jika foldernya berlapis, masuk ke folder paling dalam yang berisi `artisan`.

```powershell
cd C:\xampp\htdocs\TokoOnline
Get-Item artisan
if (!(Test-Path .env)) { Copy-Item .env.example .env }
composer install
composer check-platform-reqs
```

**Jangan menjalankan `composer update` atau `--ignore-platform-reqs`.** Dependency
vendor tidak disertakan dalam ZIP; `install` mengambil versi dari lock dan membutuhkan
internet. Tidak perlu npm/build untuk aset Blade/template statis yang disertakan.
Jangan menimpa `.env` yang sudah tersedia. Buka `.env` dengan editor teks dan simpan
sebagai `.env`, bukan `.env.txt`. Jangan mengirim isinya melalui chat atau Git.

### 3. Atur database dan URL

Mulai **MySQL** dari XAMPP Control Panel. Apache diperlukan jika menggunakan
phpMyAdmin; aplikasi demo sendiri akan memakai server Laravel pada langkah 5.
Buat database baru melalui phpMyAdmin jika memang melakukan instalasi kosong.
Isi `.env` sesuai database dan akun MySQL lokal yang disiapkan pengelola:

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tokoonline_lokal
DB_USERNAME=ISI_USER_MYSQL_LOKAL
DB_PASSWORD=ISI_PASSWORD_MYSQL_LOKAL
MIDTRANS_IS_PRODUCTION=false
```

Nama database di atas adalah contoh; database harus benar-benar tersedia. Ganti
placeholder user/password dengan nilai lokal, bukan teks contoh. Password dengan
spasi atau `#` harus diapit tanda kutip. `DB_HOST=db` dan variabel `TOKO_DB_*` adalah
milik Docker dan **tidak diperlukan pada XAMPP**. Jika MySQL memakai port berbeda,
sesuaikan `DB_PORT`. Jangan menaruh kredensial asli dalam README/source ZIP.

### 4. Pilih alur database yang tepat

**A. Instalasi baru dengan database kosong**

Pastikan `DB_DATABASE` menunjuk database baru kosong milik instalasi ini. Setelah
konfigurasi disimpan, jalankan:

```powershell
php artisan key:generate
php artisan config:clear
php artisan migrate
php artisan app:bootstrap-admin
```

Jika Laravel meminta konfirmasi lingkungan production pada instalasi baru yang
memang sudah diperiksa, tinjau kembali nama database sebelum menyetujui.
Bootstrap meminta nama, email unik, password minimal 12 karakter dan konfirmasinya.
Password diketik secara tersembunyi. Tidak ada password admin bawaan.
Bootstrap menolak jika admin aktif sudah ada atau email telah digunakan, dan tidak
mengubah password akun existing. Jalankan melalui terminal interaktif biasa.
Tidak perlu seed/dump pribadi. Katalog awal kosong; isi kategori/produk melalui backend.

**B. Database existing / database klien yang sudah berisi data**

Pertahankan `.env` dan **APP_KEY existing** dari lingkungan yang diserahkan melalui
kanal privat. Jangan menjalankan `key:generate` untuk mengganti key existing.
Setelah memastikan koneksi menuju database yang benar:

```powershell
php artisan config:clear
php artisan migrate:status
```

Berhenti dan tinjau hasilnya bersama pengelola. Buat backup melalui prosedur yang
disetujui sebelum perubahan schema. **Jangan reset, `migrate:fresh`, `refresh`, rollback,
seed, atau impor ulang dump pada data existing.** Migrasi penerima, ongkir, dan
pembayaran sudah Ran pada MySQL pengembangan; komputer klien mempunyai statusnya
sendiri. Jika ada migrasi pending, penerapannya memerlukan peninjauan pengelola;
panduan ini tidak menyuruh menjalankan semua migrasi pada database existing.
Gunakan akun admin existing. Bootstrap hanya untuk kebutuhan admin awal yang sah,
tanpa menimpa akun lama.

### 5. Jalankan demo

Di folder yang berisi `artisan`, jalankan:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

**Biarkan terminal tetap terbuka.** Tutup server dengan `Ctrl+C`. MySQL XAMPP juga
harus tetap berjalan. Gunakan host `localhost` secara konsisten saat membuka:

| Halaman | URL |
|---|---|
| Frontend (redirect ke beranda) | http://localhost:8000/ |
| Beranda | http://localhost:8000/beranda |
| Katalog | http://localhost:8000/produk |
| Login backend | http://localhost:8000/backend/login |
| Login pelanggan Google | http://localhost:8000/auth/redirect |
| Riwayat pelanggan setelah login | http://localhost:8000/history |

Jika port 8000 dipakai aplikasi lain, pilih port kosong dan sesuaikan **APP_URL,
URL browser, dan callback Google** bersama-sama. Setelah perubahan `.env`, jalankan
`php artisan config:clear`, lalu hentikan dan jalankan ulang server.

### 6. Konfigurasi integrasi (tanpa kredensial dalam paket)

Isi nilai melalui `.env` lokal yang aman. Integrasi membutuhkan internet. Aplikasi
membatasi Midtrans ke **Sandbox**; jangan mengisi atau memakai key Production.

| Integrasi | Nama variabel dari kode | Petunjuk |
|---|---|---|
| Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL` | Redirect untuk demo ini: `http://localhost:8000/auth/google/callback` |
| RajaOngkir | `RAJAONGKIR_API_KEY`, `RAJAONGKIR_BASE_URL`, `RAJAONGKIR_ORIGIN_DISTRICT`, `RAJAONGKIR_COURIERS` | Base URL `https://rajaongkir.komerce.id/api/v1`; kurir dipisahkan titik dua sesuai hak akun |
| Midtrans | `MIDTRANS_MERCHANT_ID`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_SERVER_KEY`, `MIDTRANS_IS_PRODUCTION` | Gunakan key dari dashboard Sandbox; production tetap `false` |

Callback Google harus **identik** dengan authorized redirect URI di Google Cloud,
termasuk scheme, host, port, dan path. `localhost` dan `127.0.0.1` jangan ditukar
selama alur login. Daftarkan callback untuk URL klien; callback Docker port 8085
berbeda dengan demo XAMPP port 8000. Akun Google mungkin perlu dimasukkan sebagai
test user bila consent screen masih dalam mode pengujian.

Asal pengiriman **606 adalah konfigurasi pengujian Bekasi Selatan**, Kota Bekasi,
Jawa Barat; bukan alamat toko klien yang sudah disahkan. Isi
`RAJAONGKIR_ORIGIN_DISTRICT=606` hanya bila demo memang menggunakan asal tersebut.
Untuk operasional, pengelola harus mengonfirmasi ID asal dan kurir aktif akunnya.
`jne:pos:tiki` adalah contoh kode kurir hasil pengujian sebelumnya, bukan jaminan
hak akses setiap akun. Tidak perlu membeli paket/top-up atau membuat pengiriman
untuk mengikuti setup ini.

Server Key Midtrans hanya digunakan backend; jangan menaruhnya di Blade/JavaScript,
log, screenshot, atau ZIP. Client Key Sandbox dipakai script Snap oleh aplikasi.
Pada localhost, webhook publik tidak tersedia secara otomatis. Gunakan tombol
**Periksa Status Midtrans** pada halaman pembayaran untuk sinkronisasi backend;
tidak perlu tunnel untuk demo ini. Event sukses JavaScript sendiri bukan bukti Paid.
Timeout/Get Status 404 bukan bukti batal: catat referensi dan waktu percobaan,
minta pengelola menelusuri dashboard Sandbox/dukungan Midtrans, lalu sinkronkan
kembali. Jangan membayar ulang, membuka freeze, mengubah SQL atau melepas stok paksa.

### 7. Gambar dan folder yang dapat ditulis

- Aset tampilan: `public/frontend`, `public/backend`, `public/image`,
  `public/ckeditor`, dan `public/sweetalert`.
- Gambar produk aktual beserta thumbnail berada di **`public/storage/img-produk`**;
  gambar produk yang disertakan dipertahankan. Placeholder `img-default.jpg` berasal
  dari aset bawaan `public/image/img-default.jpg`. Gambar tersebut tidak mengisi record
  katalog pada database kosong secara otomatis.
- Upload akun memakai `public/storage/img-user` dan `public/storage/img-customer`.
  Foto akun pribadi tidak disertakan; hanya placeholder bawaan `img-default.jpg`
  pada `img-user` dipertahankan. Folder upload customer dibuat kosong.
- Pastikan PHP dapat menulis `storage`, `bootstrap/cache`, serta folder upload.
  Jangan memberi izin tulis terbuka ke seluruh komputer.
- **Jangan menjalankan `storage:link` yang menimpa direktori gambar existing.** Proyek
  ini menyimpan gambar langsung di `public/storage`; bukan keharusan membuat symlink.

### 8. Troubleshooting singkat

| Gejala | Pemeriksaan/tindakan |
|---|---|
| `php`/Composer tidak ditemukan atau PHP salah | Ulang pengaturan PATH terminal; cek `where.exe php`, `php -v`, `php --ini` |
| Composer menolak platform/extension | Aktifkan extension di PHP XAMPP yang terbaca; cek platform lagi, jangan update/abaikan persyaratan |
| `Could not open input file: artisan` | Masuk ke folder hasil ekstrak yang benar-benar berisi `artisan` |
| `Connection refused` | Pastikan MySQL XAMPP berjalan, host `127.0.0.1`, port benar; jangan gunakan host `db` |
| `Access denied` / `Unknown database` | Cocokkan akun/izin/nama database lokal; jangan mereset database |
| Tabel/kolom belum ada | Periksa `migrate:status`; alur database kosong dan existing berbeda |
| `No application encryption key` | Instalasi baru: `key:generate`; existing: minta APP_KEY existing melalui kanal privat |
| HTTP 500 | Cek lokal `storage/logs/laravel.log`; jangan bagikan log mentah/kredensial atau mengaktifkan debug untuk penyerahan |
| Gambar hilang | Periksa file produk/thumbnail dan jalur `public/storage`; jangan menimpa folder dengan symlink |
| Google `redirect_uri_mismatch` | Samakan callback persis dengan Google Cloud dan URL host/port browser |
| Login berulang / 419 | Gunakan host yang sama, muat ulang form, pastikan folder session dapat ditulis dan waktu sistem benar |
| Tarif/Snap tidak tersedia | Periksa konfigurasi, internet, hak akun dan mode Sandbox; jangan membuat tagihan ulang jika status lama ambigu |

## Verifikasi dan batas penyerahan

- **Pengguna melaporkan demo pembayaran Sandbox berhasil.** Ini dicatat sebagai
  verifikasi manual pengguna; pengembang tidak mengulang transaksi pada pekerjaan
  penyerahan ini. Keberhasilan tersebut tidak diklaim sebagai pengujian XAMPP,
  webhook publik, atau bukti pengiriman/cetak yang belum dirinci pengguna.
- Regresi terakhir: **139 tes / 858 assertion** pada PDO SQLite `:memory:` dengan
  pengaman aktif sebelum koneksi/migrasi tes, termasuk environment MySQL dari Compose.
  Schema kosong, bootstrap admin/login, penolakan hapus akun beserta keutuhan data/foto,
  pembayaran ambigu, kepemilikan, nominal, freeze, reservasi stok dan callback diuji.
- SQLite dan mock provider tidak membuktikan konkurensi InnoDB atau pembayaran
  Sandbox nyata. XAMPP/Windows dan browser/cetak belum diuji langsung oleh pengembang.
- Ketiga migrasi additive pada MySQL pengembangan sudah Ran; Server/Client Key
  Sandbox telah diperbaiki sebelumnya. Pemeriksaan penyerahan tidak mengubah database,
  akun, `.env`, key, Docker atau dependency dan tidak memanggil API nyata.
- Dependency masih mempunyai **62 advisory pada 17 package** pada audit sebelumnya;
  jangan menyebut aplikasi bebas advisory. Usulan dry-run di bagian akhir belum diterapkan.

## Catatan data dan keamanan

User yang terkait Customer/pesanan serta Customer/produk yang dipakai pesanan
(termasuk keranjang pending) ditolak penghapusannya. Gunakan status Nonaktif/Blok.
Transaksi/locking aplikasi mempertahankan data; SQL bulk di luar aplikasi tidak
melalui event model. Jangan menghapus riwayat lewat SQL/Tinker. Pembatalan/expiry
provider yang terverifikasi membuka keranjang dan mengembalikan stok sekali;
menutup popup, timeout, atau 404 tidak melakukan hal tersebut.

Paket source bersih tidak menyertakan `.git`, `.env`, dump/seeder pribadi, vendor,
node_modules, foto akun, cache/log, data QA, arsip template bersarang, atau PDF klien
yang belum diizinkan distribusinya. Database existing dan kredensial diserahkan
terpisah hanya dengan izin pemilik. Jangan menganggap paket tanpa `.git` sudah
membersihkan repository: histori awal `71a32f3` masih memuat dump/seeder pribadi.
Tidak ada rewrite histori/rotasi credential dalam pekerjaan ini. ZIP lama yang
sudah diterima klien memuat materi privat; penggantian ZIP tidak menarik salinannya.
Pemilik perlu meninjau penggunaan kredensial lama dan penanganan salinan tersebut.

## Opsi Apache dan catatan pengembang

Untuk Apache XAMPP, gunakan virtual host dengan DocumentRoot **`TokoOnline/public`**,
`mod_rewrite` aktif, dan `AllowOverride All`. Jangan mengekspos root proyek atau
`.env`. Sesuaikan APP_URL/callback dengan URL virtual host. Cara ini opsional;
server `artisan serve` di atas cukup untuk demo lokal, bukan hosting publik.

Lingkungan pengembangan memakai `compose.local.yaml`, app/db, PHP 8.3 dan port8085.
Konfigurasi tersebut tidak diperlukan klien Windows. Jangan menyalin DB_HOST Docker
ke XAMPP. Regresi pengembang dijalankan melalui container dan `tests/bootstrap.php`
memaksa SQLite `:memory:`; test menolak config cache atau database file/MySQL.
PDF modul menjadi acuan pengembangan, tetapi tidak disertakan dalam paket source.
Grafik menggunakan implementasi Flot yang sudah ada; judul/penomoran PDF tidak
selalu konsisten dan tidak menjadi alasan menambah fitur baru.

## Prosedur memperbarui paket source

Bangun dari **working tree terbaru**, termasuk file baru/uncommitted; jangan memakai
`git archive HEAD` bila perubahan belum masuk HEAD. Salin source `app`, `config`,
`resources`, `routes`, migrasi/factory, tes source, `bootstrap/app.php`, file root
Artisan/Composer/konfigurasi tes/build, `.env.example`, README, serta aset statis dan
gambar produk yang diizinkan. Jangan membawa symlink keluar paket. Buat folder
runtime kosong untuk cache/session/view/log dan upload akun. Jangan menyalin isi
cache/session/log development, `.env` lain, `.git`, vendor/node_modules, dump,
seeder pribadi, data/artefak QA atau PDF klien. Tinjau manifest, scan secret tanpa
mencetak nilainya, uji integritas ZIP, lalu ekstrak ke folder QA baru dan cocokkan
hash semua file dengan working tree yang dipilih. Jangan mencoba instalasi pada
database klien. Setelah diterima, klien menjalankan `composer install` sendiri.

## Lampiran pengembang: usulan dependency minimum — belum diterapkan

Bagian ini adalah catatan audit terdahulu untuk peninjauan pengelola, bukan langkah
setup klien Windows. Contoh multiline di bawah memakai sintaks Bash. Jangan
menjalankannya sebagai pengganti `composer install`.

`composer audit --format=json` kembali menemukan 62 advisory pada 17 package.
Dry-run biasa Laravel 10.50.4 ditolak security blocking Composer karena beberapa
advisory masih mencakup seluruh Laravel 10. Mengabaikan blocker untuk instalasi
bukan solusi penutupan audit. Untuk **menghitung rencana saja**, dry-run kedua
memakai flag sementara `--no-security-blocking`, tanpa mengubah konfigurasi Composer:

```bash
composer update laravel/framework:10.50.4 guzzlehttp/guzzle guzzlehttp/psr7 \
  league/commonmark league/flysystem nesbot/carbon phpseclib/phpseclib \
  phpunit/phpunit psy/psysh symfony/http-foundation symfony/http-kernel \
  symfony/mailer symfony/mime symfony/polyfill-intl-idn symfony/process \
  symfony/routing 'symfony/yaml:^6.4' --with-all-dependencies --minimal-changes \
  --dry-run --no-scripts --no-interaction --no-security-blocking
```

Perintah dijalankan melalui container app. Hasil: **22 update, 0 instalasi,
0 penghapusan**; Composer JSON/lock dan vendor tidak diperbarui oleh tahap ini.
Pembatasan YAML 6.4 menghindari perpindahan major 7 pada hasil solver awal.
Rincian kandidat yang benar-benar dihasilkan solver:

- guzzlehttp/guzzle (7.9.2 => 7.16.5)
- guzzlehttp/promises (2.0.3 => 2.6.1)
- guzzlehttp/psr7 (2.7.0 => 2.13.5)
- laravel/framework (v10.48.22 => v10.50.4)
- league/commonmark (2.5.3 => 2.10.7)
- league/flysystem (3.28.0 => 3.36.0)
- myclabs/deep-copy (1.12.0 => 1.14.0)
- nesbot/carbon (2.72.5 => 2.73.0)
- phpseclib/phpseclib (4.0.2 => 4.0.4)
- phpunit/phpunit (10.5.35 => 10.5.67)
- psy/psysh (v0.12.4 => v0.12.24)
- sebastian/comparator (5.0.2 => 5.0.5)
- sebastian/exporter (5.1.2 => 5.1.4)
- sebastian/recursion-context (5.0.0 => 5.0.2)
- symfony/http-foundation (v6.4.12 => v6.4.50)
- symfony/http-kernel (v6.4.12 => v6.4.50)
- symfony/mailer (v6.4.12 => v6.4.49)
- symfony/mime (v6.4.12 => v6.4.49)
- symfony/polyfill-intl-idn (v1.31.0 => v1.43.0)
- symfony/process (v6.4.12 => v6.4.49)
- symfony/routing (v6.4.12 => v6.4.50)
- symfony/yaml (v6.4.12 => v6.4.50)

Risiko: parsing request/redirect/upload dan session Symfony/Laravel, validasi
email, HTTP provider Guzzle, Markdown, filesystem, tanggal Carbon, serta tooling
PHPUnit/PsySH. Setelah persetujuan, uji ulang platform PHP XAMPP, regresi, Google,
RajaOngkir, dan pembayaran Sandbox nyata pada QA. Versi hasil solver dapat berubah;
ulang dry-run dan tinjau lock sebelum penerapan. Rencana ini mengurangi perubahan
transitif dibanding dry-run `-W` tanpa minimal-changes (56 update/1 penghapusan).

Audit dry-run masih membaca lock yang **belum berubah**; angka 62 di akhirnya bukan
jumlah advisory yang tersisa pada kandidat. Jangan mengklaim hasil audit bersih.
Database advisory Composer mencatat backport TokenGuard pada 10.50.4, sedangkan
[advisory upstream TokenGuard](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6)
menyebut versi perbaikan 12/13; kesesuaian backport perlu ditinjau sebelum klaim
penutupan critical. Aplikasi memakai guard web session dan belum ditemukan jalur
TokenGuard aktif; ini bukan penutupan advisory.
[Advisory email](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq)
serta advisory signed URL/debug/concurrency masih mencakup Laravel 10 dalam audit.
Update patch 10.x saja **tidak cukup** untuk menyatakan semua masalah selesai.
Upgrade major atau backport terpilih memerlukan kajian dan persetujuan terpisah;
belum dilakukan. Jangan menjalankan command di atas tanpa `--dry-run` sebagai
jalan pintas melewati security blocking.
