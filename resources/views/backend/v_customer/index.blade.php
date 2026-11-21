@extends('backend.v_layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">{{ $judul }}</h5>

                @if (session('msgError'))
                    <div class="alert alert-danger" role="alert">
                        {{ session('msgError') }}
                    </div>
                @endif

                <div class="table-responsive">
                    <table id="zero_config"
                        class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($index as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $row->user->nama ?? '-' }}</td>
                                    <td>{{ $row->user->email ?? '-' }}</td>

                                    <td>
                                        <a
                                            href="{{ route('backend.customer.show', $row->id) }}"
                                            class="btn btn-info btn-sm">
                                            Detail
                                        </a>

                                        <a
                                            href="{{ route('backend.customer.edit', $row->id) }}"
                                            class="btn btn-warning btn-sm">
                                            Ubah
                                        </a>

                                        <form
                                            action="{{ route('backend.customer.destroy', $row->id) }}"
                                            method="POST"
                                            style="display: inline-block;"
                                            onsubmit="return confirm('Hapus customer beserta akun terkait? Tindakan ini tidak dapat dibatalkan.');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                class="btn btn-danger btn-sm">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection