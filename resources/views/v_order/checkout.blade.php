@extends('v_layouts.app')
@section('content')
<div class="store-order">
    <div class="section-title"><h3 class="title">Ringkasan Pesanan #{{ $order->id }}</h3></div>
    <p><span class="label label-warning">{{ $order->status_label }}</span></p>
    @if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
    @include('v_order._items')
    @include('v_order._totals')
    @if($order->shipping_quote && $summary['shipping'] === null)
        <p class="text-warning">Kutipan belum dipilih, kedaluwarsa, atau data berubah. Cek tarif kembali sebelum memilih layanan.</p>
    @endif
    <h4>Data penerima</h4>
    <p>Data awal berasal dari profil. Menyimpan penerima tidak mengubah profil dan tidak menyelesaikan transaksi.</p>
    @unless($recipientSchemaReady)
        <div class="alert alert-warning" role="alert">Penyimpanan penerima menunggu pengelola menerapkan migrasi additive data penerima. Data dapat ditinjau, tetapi belum dapat disimpan.</div>
    @endunless
    <form method="POST" action="{{ route('order.recipient', $order->id) }}">
        @csrf
        @method('PUT')
        <fieldset @disabled(!$recipientSchemaReady)>
            <div class="form-group"><label for="nama_penerima">Nama penerima</label><input id="nama_penerima" name="nama_penerima" class="form-control" maxlength="255" value="{{ old('nama_penerima', $recipient['nama_penerima']) }}" autocomplete="shipping name" required></div>
            <div class="form-group"><label for="email_penerima">Email</label><input id="email_penerima" name="email_penerima" type="email" class="form-control" maxlength="255" value="{{ old('email_penerima', $recipient['email_penerima']) }}" autocomplete="shipping email" required></div>
            <div class="form-group"><label for="hp_penerima">Nomor HP</label><input id="hp_penerima" name="hp_penerima" type="tel" class="form-control" inputmode="numeric" pattern="[0-9]{10,13}" maxlength="13" value="{{ old('hp_penerima', $recipient['hp_penerima']) }}" autocomplete="shipping tel" required></div>
            <div class="form-group"><label for="alamat">Alamat penerima</label><textarea id="alamat" name="alamat" class="form-control" maxlength="2000" rows="3" autocomplete="shipping street-address" required>{{ old('alamat', $recipient['alamat']) }}</textarea></div>
            <div class="form-group"><label for="pos">Kode pos</label><input id="pos" name="pos" class="form-control" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" value="{{ old('pos', $recipient['pos']) }}" autocomplete="shipping postal-code" required></div>
            <button class="primary-btn" type="submit">Simpan Penerima</button>
        </fieldset>
    </form>
    <div class="alert alert-info integration-notice" role="status">
        <hr>

<h4>Wilayah Tujuan Pengiriman</h4>
<p>Pilih provinsi, kota/kabupaten, lalu kecamatan tujuan.</p>

<div id="shipping-location-error"
    class="alert alert-danger"
    role="alert"
    hidden>
</div>

<div class="row">
    <div class="col-sm-4">
        <div class="form-group">
            <label for="shipping-province">Provinsi</label>
            <select id="shipping-province" class="form-control" disabled>
                <option value="">Memuat provinsi...</option>
            </select>
        </div>
    </div>

    <div class="col-sm-4">
        <div class="form-group">
            <label for="shipping-city">Kota/Kabupaten</label>
            <select id="shipping-city" class="form-control" disabled>
                <option value="">Pilih provinsi dahulu</option>
            </select>
        </div>
    </div>

    <div class="col-sm-4">
        <div class="form-group">
            <label for="shipping-district">Kecamatan</label>
            <select id="shipping-district" class="form-control" disabled>
                <option value="">Pilih kota dahulu</option>
            </select>
        </div>
    </div>
</div>

<p class="help-block">Wilayah disimpan bersama kutipan ongkir pada pesanan, tanpa mengubah profil.</p>
@if(!$shippingSchemaReady)
    <p>Pengiriman menunggu penerapan migrasi kutipan ongkir oleh pengelola.</p>
@elseif(!$shippingConfigured)
    <p>Konfigurasi RajaOngkir belum lengkap: key, asal kecamatan, dan kurir akun harus dikonfirmasi pengelola. Asal pengujian Bekasi Selatan bukan alamat toko produksi.</p>
@endif
<button id="calculate-shipping" type="button" class="primary-btn" @disabled(!$shippingSchemaReady || !$shippingConfigured)>Cek Tarif</button>
<div id="shipping-options" class="order-actions" aria-live="polite"></div>
<p id="shipping-result" role="status"></p>
<p>Pembayaran memakai Midtrans Sandbox. Status dibayar diverifikasi backend.</p>
    </div>
    <div class="order-actions">
        <a class="main-btn" href="{{ route('order.cart') }}">Kembali ke Keranjang</a>

        <button id="start-payment" type="button" class="primary-btn" @disabled($summary['total'] === null || !$paymentSchemaReady || !$paymentConfigured)>Bayar Sekarang (Sandbox)</button>
        @if(!$paymentSchemaReady)<p>Pembayaran menunggu penerapan migrasi additive pembayaran oleh pengelola.</p>@elseif(!$paymentConfigured)<p>Konfigurasi Midtrans Sandbox belum lengkap.</p>@endif
        <p id="payment-start-message" role="status"></p>
    </div>
</div>

<script>
    const startPayment = document.getElementById('start-payment');
    startPayment.addEventListener('click', async function () {
        startPayment.disabled = true;
        const message = document.getElementById('payment-start-message');
        message.textContent = 'Menyiapkan pembayaran Sandbox...';
        try {
            const response = await fetch(@json(route('payment.start', $order->id)), {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())}});
            const data = await response.json().catch(function () { return {}; });
            if (!response.ok) throw new Error(data.message || 'Pembayaran belum dapat dimulai.');
            window.location.assign(data.payment_url);
        } catch (error) { message.textContent=error.message; startPayment.disabled=false; }
    });
    const provinceSelect = document.getElementById('shipping-province');
    const citySelect = document.getElementById('shipping-city');
    const districtSelect = document.getElementById('shipping-district');
    const locationError = document.getElementById('shipping-location-error');

    const provinceUrl = @json(route('shipping.provinces'));
    const cityUrl = @json(route('shipping.cities', ['province' => '__ID__']));
    const districtUrl = @json(route('shipping.districts', ['city' => '__ID__']));

    let cityRequest;
    let districtRequest;

    function resetSelect(select, message) {
        select.replaceChildren(new Option(message, ''));
        select.disabled = true;
    }

    function clearLocationError() {
        locationError.hidden = true;
        locationError.textContent = '';
    }

    async function loadLocations(url, select, placeholder, signal) {
        resetSelect(select, 'Memuat...');

        try {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' },
                signal: signal
            });

            if (!response.ok) {
                throw new Error('Gagal mengambil wilayah.');
            }

            const locations = await response.json();

            if (!Array.isArray(locations)) {
                throw new Error('Format wilayah tidak sesuai.');
            }

            if (signal && signal.aborted) {
                return;
            }

            select.replaceChildren(new Option(placeholder, ''));

            locations.forEach(function (location) {
                select.add(new Option(location.name, location.id));
            });

            select.disabled = locations.length === 0;

            if (locations.length === 0) {
                resetSelect(select, 'Wilayah tidak tersedia');
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            resetSelect(select, 'Gagal memuat wilayah');
            locationError.textContent =
                'Data wilayah gagal dimuat. Pastikan masih login, lalu muat ulang halaman.';
            locationError.hidden = false;
        }
    }

    provinceSelect.addEventListener('change', function () {
        clearLocationError();

        if (cityRequest) cityRequest.abort();
        if (districtRequest) districtRequest.abort();

        resetSelect(citySelect, 'Pilih provinsi dahulu');
        resetSelect(districtSelect, 'Pilih kota dahulu');

        if (!this.value) return;

        cityRequest = new AbortController();

        loadLocations(
            cityUrl.replace('__ID__', encodeURIComponent(this.value)),
            citySelect,
            'Pilih kota/kabupaten',
            cityRequest.signal
        );
    });

    citySelect.addEventListener('change', function () {
        clearLocationError();

        if (districtRequest) districtRequest.abort();

        resetSelect(districtSelect, 'Pilih kota dahulu');

        if (!this.value) return;

        districtRequest = new AbortController();

        loadLocations(
            districtUrl.replace('__ID__', encodeURIComponent(this.value)),
            districtSelect,
            'Pilih kecamatan',
            districtRequest.signal
        );
    });

    const calculateButton = document.getElementById('calculate-shipping');
    const optionsBox = document.getElementById('shipping-options');
    const shippingResult = document.getElementById('shipping-result');
    let quoteVersion = 0;
    function clearQuoteDisplay() {
        quoteVersion++;
        optionsBox.replaceChildren();
        shippingResult.textContent = 'Wilayah berubah. Cek tarif untuk menyimpan tujuan dan mendapatkan kutipan baru.';
    }
    [provinceSelect, citySelect, districtSelect].forEach(function (select) {
        select.addEventListener('change', clearQuoteDisplay);
    });
    async function shippingPost(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
            body: JSON.stringify(data)
        });
        const body = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            const errors = body.errors ? Object.values(body.errors).flat().join(' ') : body.message;
            throw new Error(errors || 'Permintaan ongkir gagal. Muat ulang halaman dan coba kembali.');
        }
        return body;
    }
    calculateButton.addEventListener('click', async function () {
        const version = ++quoteVersion;
        optionsBox.replaceChildren();
        shippingResult.textContent = 'Mengambil tarif...';
        calculateButton.disabled = true;
        try {
            const quote = await shippingPost(@json(route('shipping.quote', $order->id)), {
                province_id: provinceSelect.value, city_id: citySelect.value, district_id: districtSelect.value
            });
            if (version !== quoteVersion) return;
            shippingResult.textContent = 'Pilih layanan. Kutipan berlaku sampai ' + new Date(quote.expires_at * 1000).toLocaleTimeString('id-ID') + '.';
            quote.options.forEach(function (option) {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'main-btn';
                button.textContent = option.code.toUpperCase() + ' ' + option.service + ' — Rp ' + Number(option.cost).toLocaleString('id-ID') + ' — estimasi ' + (option.etd || 'belum tersedia');
                button.addEventListener('click', async function () {
                    if (version !== quoteVersion) return;
                    button.disabled = true;
                    try {
                        await shippingPost(@json(route('shipping.select', $order->id)), {option_id: option.id});
                        window.location.reload();
                    } catch (error) { shippingResult.textContent = error.message; button.disabled = false; }
                });
                optionsBox.appendChild(button);
            });
        } catch (error) { shippingResult.textContent = error.message; }
        finally { calculateButton.disabled = false; }
    });
    (async function () {
        await loadLocations(provinceUrl, provinceSelect, 'Pilih provinsi');
        const destination = @json($order->shipping_destination);
        if (!destination) return;
        provinceSelect.value = destination.province.id;
        await loadLocations(cityUrl.replace('__ID__', encodeURIComponent(destination.province.id)), citySelect, 'Pilih kota/kabupaten');
        citySelect.value = destination.city.id;
        await loadLocations(districtUrl.replace('__ID__', encodeURIComponent(destination.city.id)), districtSelect, 'Pilih kecamatan');
        districtSelect.value = destination.district.id;
    })();
</script>
@endsection