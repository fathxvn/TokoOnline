<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class StorefrontTestCase extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        DatabaseIsolation::assert($this->app);
    }

    protected function makeUser(array $attributes = []): User
    {
        static $number = 0;

        return User::create(array_replace([
            'nama' => 'Pelanggan Uji',
            'email' => 'test'.(++$number).'@example.test',
            'role' => '2',
            'status' => 1,
            'password' => Hash::make('PasswordUji!123'),
            'hp' => '081234567890',
        ], $attributes));
    }

    protected function makeCustomer(array $attributes = [], ?User $user = null): Customer
    {
        return Customer::create(array_replace(['user_id' => ($user ?? $this->makeUser())->id], $attributes));
    }

    protected function makeProduct(array $attributes = []): Produk
    {
        $kategori = Kategori::create(['nama_kategori' => 'Kategori Uji']);
        $admin = $this->makeUser(['role' => '0']);

        return Produk::create(array_replace([
            'kategori_id' => $kategori->id,
            'user_id' => $admin->id,
            'status' => 1,
            'nama_produk' => 'Produk Uji',
            'detail' => 'Deskripsi produk uji',
            'harga' => 15000,
            'stok' => 10,
            'berat' => 250,
            'foto' => 'produk-uji.jpg',
        ], $attributes));
    }

    protected function makeOrder(Customer $customer, Produk $product, string $status = 'pending'): Order
    {
        $order = Order::create(['customer_id' => $customer->id, 'status' => $status, 'total_harga' => $product->harga]);
        $order->orderItems()->create(['produk_id' => $product->id, 'quantity' => 1, 'harga' => $product->harga]);

        return $order;
    }
}
