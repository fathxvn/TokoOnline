@extends('backend.v_layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">{{ $judul }}</h5>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>Nama</th>
                                <td>{{ $customer->user->nama ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{ $customer->user->email ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Nomor HP</th>
                                <td>{{ $customer->user->hp ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Alamat</th>
                                <td class="customer-address">{{ $customer->alamat ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Kode Pos</th>
                                <td>{{ $customer->pos ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($customer->user)
                                        {{ $customer->user->status == 1 ? 'Aktif' : 'Nonaktif' }}
                                    @else
                                        User tidak tersedia
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <a href="{{ route('backend.customer.index') }}"
                    class="btn btn-secondary">
                    Kembali
                </a>
            </div>
        </div>
    </div>
</div>
@endsection