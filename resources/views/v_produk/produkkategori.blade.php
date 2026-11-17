@extends('v_layouts.app')
@section('content')
<div id="store">
    <div class="section-title"><h3 class="title">{{ $judul }}</h3></div>
    <div class="row">
        @forelse ($produk as $row)
            <div class="col-md-4 col-sm-6 col-xs-6">
                <div class="product product-single">
                    <div class="product-thumb">
                        <div class="product-label">
                            <span>Kategori</span>
                            <span class="sale">{{ $row->kategori->nama_kategori }}</span>
                        </div>
                        <a href="{{ route('produk.detail', $row->id) }}">
                            <button type="button" class="main-btn quick-view"><i class="fa fa-search-plus"></i> Detail Produk</button>
                        </a>
                        <img src="{{ asset('storage/img-produk/thumb_md_'.$row->foto) }}" alt="{{ $row->nama_produk }}">
                    </div>
                    <div class="product-body">
                        <h3 class="product-price">Rp. {{ number_format($row->harga, 0, ',', '.') }}</h3>
                        <h2 class="product-name"><a href="{{ route('produk.detail', $row->id) }}">{{ $row->nama_produk }}</a></h2>
                        <div class="product-btns">
                            <a href="{{ route('produk.detail', $row->id) }}" title="Detail Produk">
                                <button type="button" class="main-btn icon-btn"><i class="fa fa-search-plus"></i></button>
                            </a>
                            <form action="{{ route('order.addToCart', $row->id) }}" method="post" style="display: inline-block;">
                                @csrf
                                <button type="submit" class="primary-btn add-to-cart"><i class="fa fa-shopping-cart"></i> Pesan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-md-12"><p>Produk belum tersedia.</p></div>
        @endforelse
    </div>
    {{ $produk->links('pagination::bootstrap-4') }}
</div>
@endsection
