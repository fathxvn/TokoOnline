<?php

use App\Http\Controllers\BerandaController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('beranda'));

Route::get('backend/login', [LoginController::class, 'loginBackend'])->name('backend.login');
Route::post('backend/login', [LoginController::class, 'authenticateBackend']);
Route::post('backend/logout', [LoginController::class, 'logoutBackend'])->name('backend.logout')->middleware('auth');

Route::middleware(['auth', 'is.admin'])->group(function () {
    Route::get('backend/beranda', [BerandaController::class, 'berandaBackend'])->name('backend.beranda');
    Route::resource('backend/user', UserController::class, ['as' => 'backend']);
    Route::resource('backend/kategori', KategoriController::class, ['as' => 'backend']);
    Route::resource('backend/produk', ProdukController::class, ['as' => 'backend']);
    Route::resource('backend/customer', CustomerController::class, ['as' => 'backend'])->only('index');

    Route::get('backend/laporan/formuser', [UserController::class, 'formUser'])->name('backend.laporan.formuser');
    Route::post('backend/laporan/cetakuser', [UserController::class, 'cetakUser'])->name('backend.laporan.cetakuser');
    Route::get('backend/laporan/formproduk', [ProdukController::class, 'formProduk'])->name('backend.laporan.formproduk');
    Route::post('backend/laporan/cetakproduk', [ProdukController::class, 'cetakProduk'])->name('backend.laporan.cetakproduk');
    Route::post('foto-produk/store', [ProdukController::class, 'storeFoto'])->name('backend.foto_produk.store');
    Route::delete('foto-produk/{id}', [ProdukController::class, 'destroyFoto'])->name('backend.foto_produk.destroy');
});

Route::get('/beranda', [BerandaController::class, 'index'])->name('beranda');
Route::get('/produk', [ProdukController::class, 'all'])->name('produk.all');
Route::get('/produk/detail/{id}', [ProdukController::class, 'detail'])->name('produk.detail');
Route::get('/produk/kategori/{id}', [ProdukController::class, 'produkKategori'])->name('produk.kategori');

Route::get('/auth/redirect', [CustomerController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/google/callback', [CustomerController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [CustomerController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('is.customer')->group(function () {
    Route::get('/customer/akun/{id}', [CustomerController::class, 'akun'])->name('customer.akun');
    Route::put('/customer/updateakun/{id}', [CustomerController::class, 'updateAkun'])->name('customer.updateakun');
    Route::post('add-to-cart/{id}', [OrderController::class, 'addToCart'])->name('order.addToCart');
    Route::get('cart', [OrderController::class, 'viewCart'])->name('order.cart');
    Route::patch('cart/items/{id}', [OrderController::class, 'updateQuantity'])->name('order.updateQuantity');
    Route::delete('cart/items/{id}', [OrderController::class, 'removeItem'])->name('order.removeItem');
});
