@extends('backend.v_layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">{{ $judul }}</h5>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    action="{{ route('backend.customer.update', $customer->id) }}"
                    method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="nama">Nama</label>
                        <input
                            id="nama"
                            name="nama"
                            type="text"
                            class="form-control"
                            value="{{ old('nama', $customer->user->nama) }}"
                            maxlength="255"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="form-control"
                            value="{{ old('email', $customer->user->email) }}"
                            maxlength="255"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="hp">Nomor HP</label>
                        <input
                            id="hp"
                            name="hp"
                            type="tel"
                            class="form-control"
                            value="{{ old('hp', $customer->user->hp) }}"
                            minlength="10"
                            maxlength="13">
                    </div>

                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <textarea
                            id="alamat"
                            name="alamat"
                            class="form-control"
                            rows="3">{{ old('alamat', $customer->alamat) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="pos">Kode Pos</label>
                        <input
                            id="pos"
                            name="pos"
                            type="text"
                            class="form-control"
                            value="{{ old('pos', $customer->pos) }}"
                            maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select
                            id="status"
                            name="status"
                            class="form-control"
                            required>
                            <option value="1"
                                {{ old('status', $customer->user->status) == 1 ? 'selected' : '' }}>
                                Aktif
                            </option>
                            <option value="0"
                                {{ old('status', $customer->user->status) == 0 ? 'selected' : '' }}>
                                Nonaktif
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Simpan Perubahan
                    </button>

                    <a href="{{ route('backend.customer.index') }}"
                        class="btn btn-secondary">
                        Batal
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection