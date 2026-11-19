@extends('v_layouts.app')

@section('content')
<div class="section-title">
    <h3 class="title">{{ $judul }}</h3>
</div>

<div class="product product-details clearfix">
    <div class="row">

        <!-- Galeri produk -->
        <div class="col-sm-6 col-xs-12">
            <div id="product-main-view">
                <div class="product-view">
                    <img
                        src="{{ asset('storage/img-produk/thumb_lg_' . $row->foto) }}"
                        alt="{{ $row->nama_produk }}">
                </div>

                @foreach ($fotoProdukTambahan as $foto)
                    @if ($foto->produk_id == $row->id)
                        <div class="product-view">
                            <img
                                src="{{ asset('storage/img-produk/' . $foto->foto) }}"
                                alt="Foto tambahan {{ $row->nama_produk }}">
                        </div>
                    @endif
                @endforeach
            </div>

            <div id="product-view">
                <div class="product-view">
                    <img
                        src="{{ asset('storage/img-produk/thumb_sm_' . $row->foto) }}"
                        alt="{{ $row->nama_produk }}">
                </div>

                @foreach ($fotoProdukTambahan as $foto)
                    @if ($foto->produk_id == $row->id)
                        <div class="product-view">
                            <img
                                src="{{ asset('storage/img-produk/' . $foto->foto) }}"
                                alt="Thumbnail {{ $row->nama_produk }}">
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Informasi produk -->
        <div class="col-sm-6 col-xs-12">
            <div class="product-body">
                <div class="product-label">
                    <span>Kategori</span>
                    <span class="sale">
                        {{ $row->kategori->nama_kategori }}
                    </span>
                </div>

                <h2 class="product-name">{{ $row->nama_produk }}</h2>

                <h3 class="product-price">
                    Rp. {{ number_format($row->harga, 0, ',', '.') }}
                </h3>

                <div class="product-description">
                    {!! $row->detail !!}
                </div>

                <table class="table">
                    <tbody>
                        <tr>
                            <th>Berat</th>
                            <td>{{ $row->berat }} gram</td>
                        </tr>
                        <tr>
                            <th>Stok</th>
                            <td>{{ $row->stok }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="product-btns">
                    <form
                        action="{{ route('order.addToCart', $row->id) }}"
                        method="POST">
                        @csrf

                        <button type="submit" class="primary-btn add-to-cart">
                            <i class="fa fa-shopping-cart"></i> Pesan
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
