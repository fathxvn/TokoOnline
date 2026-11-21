<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- The above 3 meta tags *must* come first in the head;
         any other head content must come *after* these tags -->

    <link rel="icon" type="image/png" sizes="16x16"
        href="{{ asset('image/icon_univ_bsi.png') }}">

    <title>tokoonline</title>

    <!-- Google font -->
    <link href="https://fonts.googleapis.com/css?family=Hind:400,700" rel="stylesheet">

    <!-- Bootstrap -->
    <link type="text/css" rel="stylesheet"
        href="{{ asset('frontend/css/bootstrap.min.css') }}">

    <!-- Slick -->
    <link type="text/css" rel="stylesheet"
        href="{{ asset('frontend/css/slick.css') }}">

    <link type="text/css" rel="stylesheet"
        href="{{ asset('frontend/css/slick-theme.css') }}">

    <!-- nouislider -->
    <link type="text/css" rel="stylesheet"
        href="{{ asset('frontend/css/nouislider.min.css') }}">

    <!-- Font Awesome Icon -->
    <link rel="stylesheet"
        href="{{ asset('frontend/css/font-awesome.min.css') }}">

    <!-- Custom stylesheet -->
    <link type="text/css" rel="stylesheet"
        href="{{ asset('frontend/css/style.css') }}">
    <link type="text/css" rel="stylesheet" href="{{ asset('frontend/css/custom.css') }}">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->

    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>

    <!-- HEADER -->
    <header>

        <!-- top Header -->
        <div id="top-header">
            <div class="container">
                <div class="pull-left">
                    <span>Selamat datang di toko online</span>
                </div>
            </div>
        </div>
        <!-- /top Header -->


        <!-- header -->
        <div id="header">
            <div class="container">

                <div class="pull-left">

                    <!-- Logo -->
                    <div class="header-logo">
                        <a class="logo" href="#">
                            <img src="{{ asset('image/logo.png') }}" alt="">
                        </a>
                    </div>
                    <!-- /Logo -->


                    <!-- Search -->
                    <!-- /Search -->

                </div>


                <div class="pull-right">

                    <ul class="header-btns">

                        <!-- Cart -->
                        <li class="header-cart dropdown default-dropdown">

                            <a href="{{ route('order.cart') }}">

                                <div class="header-btns-icon">
                                    <i class="fa fa-shopping-cart"></i>
                                </div>

                                <strong class="text-uppercase">
                                    Keranjang
                                </strong>

                            </a>

                        </li>
                        <!-- /Cart -->

@if (Auth::check())
<!-- Account -->
 <li class="header-account dropdown default-dropdown">
    <div class="dropdown-toggle" role="button" data-toggle="dropdown" aria-expanded="true">
        <div class="header-btns-icon"> <i class="fa fa-user-o"></i>
    </div> <strong class="text-uppercase">{{ Auth::user()->nama }}<i class="fa fa-caret-down"></i></strong>
</div>
<ul class="custom-menu">
    <li><a href="{{ route('customer.akun', ['id' => Auth::user()->id]) }}"><i class="fa fa-user-o"></i> Akun Saya</a>
</li>
<li><a href="{{ route('order.history') }}"><i class="fa fa-check"></i> History</a></li> <li> <a href="#" onclick="event.preventDefault(); document.getElementById('keluar-app').submit();"><i class="fa fa-power-off"></i> Keluar </a> <!-- form keluar app --> <form id="keluar-app" action="{{ route('logout') }}" method="POST" class="d-none"> @csrf </form> <!-- form keluar app end --> </li> </ul> </li> @else <li class="header-account dropdown default-dropdown"> <div class="dropdown-toggle" role="button" data-toggle="dropdown" aria-expanded="true"> <div class="header-btns-icon"> <i class="fa fa-user-o"></i> </div> <strong class="text-uppercase">Akun Saya<i class="fa fa-caret-down"></i></strong> </div> <a href="{{ route('auth.redirect') }}" class="text-uppercase">Login</a> </li> <!-- /Account --> @endif

                        <!-- Mobile nav toggle -->
                        <li class="nav-toggle">

                            <button class="nav-toggle-btn main-btn icon-btn">
                                <i class="fa fa-bars"></i>
                            </button>

                        </li>
                        <!-- /Mobile nav toggle -->

                    </ul>

                </div>

            </div>
        </div>
        <!-- /header -->

    </header>
    <!-- /HEADER -->
<!-- NAVIGATION -->
@php
    $isBeranda = request()->segment(1) == ''
        || request()->segment(1) == 'beranda';

    $kategori = DB::table('kategori')
        ->orderBy('nama_kategori', 'asc')
        ->get();
@endphp

<div id="navigation">
    <div class="container">
        <div id="responsive-nav">

            <!-- Kategori -->
            <div class="category-nav {{ $isBeranda ? '' : 'show-on-click' }}">
                <span class="category-header">
                    Kategori <i class="fa fa-list"></i>
                </span>

                <ul class="category-list">
                    @foreach ($kategori as $navKategori)
                        <li>
                            <a href="{{ route('produk.kategori', $navKategori->id) }}">
                                {{ $navKategori->nama_kategori }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Menu -->
            <div class="menu-nav">
                <span class="menu-header">
                    Menu <i class="fa fa-bars"></i>
                </span>

                <ul class="menu-list">
                    <li>
                        <a href="{{ route('beranda') }}">Beranda</a>
                    </li>
                    <li>
                        <a href="{{ route('produk.all') }}">Produk</a>
                    </li>
                    <li>
                        <a href="#">Lokasi</a>
                    </li>
                    <li>
                        <a href="#">Hubungi Kami</a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</div>
<!-- /NAVIGATION -->

    @if (request()->segment(1) == '' || request()->segment(1) == 'beranda')

        <!-- HOME -->
        <div id="home">

            <!-- container -->
            <div class="container">

                <!-- home wrap -->
                <div class="home-wrap">

                    <!-- home slick -->
                    <div id="home-slick">

                        <!-- banner -->
                        <div class="banner banner-1">

                            <img src="{{ asset('frontend/banner/banner01.jpg') }}" alt="">

                            <div class="banner-caption text-center">

                                <h1>Jajanan Tradisional</h1>

                                <h3 class="font-weak" style="color: #30323a;">
                                    Khas Makanan Indonesia
                                </h3>

                                <button class="primary-btn">
                                    Pesan Sekarang
                                </button>

                            </div>

                        </div>
                        <!-- /banner -->


                        <!-- banner -->
                        <div class="banner banner-1">

                            <img src="{{ asset('frontend/banner/banner02.jpg') }}" alt="">

                            <div class="banner-caption">

                                <h1 class="primary-color">
                                    Khas Makanan Indonesia
                                    <br>
                                    <span class="white-color font-weak">
                                        Jajanan Tradisional
                                    </span>
                                </h1>

                                <button class="primary-btn">
                                    Pesan Sekarang
                                </button>

                            </div>

                        </div>
                        <!-- /banner -->


                        <!-- banner -->
                        <div class="banner banner-1">

                            <img src="{{ asset('frontend/banner/banner03.jpg') }}" alt="">

                            <div class="banner-caption">

                                <h1 style="color: #f8694a;">
                                    Khas Makanan
                                    <span>Indonesia</span>
                                </h1>

                                <button class="primary-btn">
                                    Pesan Sekarang
                                </button>

                            </div>

                        </div>
                        <!-- /banner -->

                    </div>
                    <!-- /home slick -->

                </div>
                <!-- /home wrap -->

            </div>
            <!-- /container -->

        </div>
        <!-- /HOME -->
    @endif


    <!-- SECTION -->
    <div class="section">

        <!-- container -->
        <div class="container">

            <!-- row -->
            <div class="row">

                <!-- ASIDE -->
<div id="aside" class="col-md-3">
    <div class="aside">
        <h3 class="aside-title">Filter Kategori</h3>

        <ul class="list-links">
            <li>
                <a href="{{ route('produk.all') }}">Semua Produk</a>
            </li>

            @foreach ($kategori as $item)
                <li>
                    <a href="{{ route('produk.kategori', $item->id) }}">
                        {{ $item->nama_kategori }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
<!-- /ASIDE -->


                <!-- MAIN -->
                <div id="main" class="col-md-9">

                    <!-- store top filter -->
                    <!-- /store top filter -->


                    <!-- @yieldAwal -->
                    @if(session('error'))
                        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                    @endif
                    @if(session('msgError'))
                        <div class="alert alert-danger" role="alert">{{ session('msgError') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @yield('content')
                    <!-- @yieldAkhir -->


                    <!-- store bottom filter -->
                    <!-- /store bottom filter -->

                </div>
                <!-- /MAIN -->

            </div>
            <!-- /row -->

        </div>
        <!-- /container -->

    </div>
    <!-- /SECTION -->


    <!-- FOOTER -->
<footer id="footer" class="section section-grey">
    <div class="container">
        <div class="row">

            <!-- Informasi toko -->
            <div class="col-sm-4 col-xs-12">
                <div class="footer">
                    <div class="footer-logo">
                        <a class="logo" href="{{ route('beranda') }}">
                            <img
                                src="{{ asset('image/logo.png') }}"
                                alt="Logo Toko Online">
                        </a>
                    </div>

                    <p>
                        Lihat pilihan produk dan detailnya,
                        lalu tambahkan produk ke keranjang belanja.
                    </p>
                </div>
            </div>

            <!-- Navigasi -->
            <div class="col-sm-4 col-xs-12">
                <div class="footer">
                    <h3 class="footer-header">Navigasi</h3>

                    <ul class="list-links">
                        <li>
                            <a href="{{ route('beranda') }}">Beranda</a>
                        </li>
                        <li>
                            <a href="{{ route('produk.all') }}">Semua Produk</a>
                        </li>
                        <li>
                            <a href="{{ route('order.cart') }}">Keranjang</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Kategori -->
            <div class="col-sm-4 col-xs-12">
                <div class="footer">
                    <h3 class="footer-header">Kategori Produk</h3>

                    <ul class="list-links">
                        @foreach ($kategori as $item)
                            <li>
                                <a href="{{ route('produk.kategori', $item->id) }}">
                                    {{ $item->nama_kategori }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

        </div>

        <hr>

        <div class="footer-copyright text-center">
            Copyright &copy; {{ date('Y') }} Toko Online.
            All rights reserved |
            This template is made with
            <i class="fa fa-heart-o" aria-hidden="true"></i>
            by
            <a href="https://colorlib.com"
                target="_blank" rel="noopener noreferrer">
                Colorlib
            </a>
        </div>
    </div>
</footer>
<!-- /FOOTER -->


    <!-- jQuery Plugins -->
    <script src="{{ asset('frontend/js/jquery.min.js') }}"></script>
    <script src="{{ asset('frontend/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('frontend/js/slick.min.js') }}"></script>
    <script src="{{ asset('frontend/js/nouislider.min.js') }}"></script>
    <script src="{{ asset('frontend/js/jquery.zoom.min.js') }}"></script>
    <script src="{{ asset('frontend/js/main.js') }}"></script>

</body>
