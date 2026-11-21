@extends('v_layouts.app')

@section('content')
<div class="customer-account">
    <div class="section-title">
        <h3 class="title">{{ $judul }}</h3>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <form
        action="{{ route('customer.updateakun', $edit->user->id) }}"
        method="POST"
        enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">

            <!-- Foto profil -->
            <div class="col-sm-4 col-xs-12">
                <div class="form-group @error('foto') has-error @enderror">
                    <label for="foto">Foto Profil</label>

                    <img
                        id="foto-preview"
                        class="foto-preview"
                        src="{{ $edit->user->foto
                            ? asset('storage/img-customer/' . $edit->user->foto)
                            : asset('storage/img-user/img-default.jpg') }}"
                        alt="Foto profil">

                    <input
                        id="foto"
                        type="file"
                        name="foto"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.gif">

                    <p class="help-block">
                        JPG, JPEG, PNG, atau GIF. Maksimal 1 MB.
                    </p>

                    @error('foto')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <!-- Data akun -->
            <div class="col-sm-8 col-xs-12">
                <div class="form-group @error('nama') has-error @enderror">
                    <label for="nama">Nama</label>
                    <input
                        id="nama"
                        type="text"
                        name="nama"
                        class="form-control"
                        value="{{ old('nama', $edit->user->nama) }}"
                        maxlength="255"
                        autocomplete="name"
                        required>

                    @error('nama')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group @error('email') has-error @enderror">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        class="form-control"
                        value="{{ old('email', $edit->user->email) }}"
                        maxlength="255"
                        autocomplete="email"
                        required>

                    @error('email')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group @error('hp') has-error @enderror">
                    <label for="hp">Nomor HP</label>
                    <input
                        id="hp"
                        type="tel"
                        name="hp"
                        class="form-control"
                        value="{{ old('hp', $edit->user->hp) }}"
                        minlength="10"
                        maxlength="13"
                        autocomplete="tel"
                        placeholder="Masukkan nomor HP"
                        required>

                    @error('hp')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group @error('alamat') has-error @enderror">
                    <label for="alamat">Alamat</label>
                    <textarea
                        id="alamat"
                        name="alamat"
                        class="form-control"
                        rows="4"
                        autocomplete="street-address"
                        required>{{ old('alamat', $edit->alamat) }}</textarea>

                    @error('alamat')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group @error('pos') has-error @enderror">
                    <label for="pos">Kode Pos</label>
                    <input
                        id="pos"
                        type="text"
                        name="pos"
                        class="form-control"
                        value="{{ old('pos', $edit->pos) }}"
                        inputmode="numeric"
                        autocomplete="postal-code"
                        placeholder="Masukkan kode pos"
                        required>

                    @error('pos')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="account-actions">
                    <button type="submit" class="primary-btn">
                        Simpan Perubahan
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
    document.getElementById('foto').addEventListener('change', function () {
        const file = this.files[0];

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            document.getElementById('foto-preview').src = event.target.result;
        };

        reader.readAsDataURL(file);
    });
</script>
@endsection