<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\DatabaseIsolation;
use Tests\Support\StorefrontTestCase;

class AccessAndCatalogTest extends StorefrontTestCase
{
    public function test_database_is_really_in_memory_and_guard_rejects_mysql(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('', DB::selectOne('PRAGMA database_list')->file);
        config(['database.default' => 'mysql']);
        try {
            DatabaseIsolation::assert($this->app);
            $this->fail('MySQL must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Tests refused: only SQLite :memory: is allowed.', $e->getMessage());
        } finally {
            config(['database.default' => 'sqlite']);
        }
    }

    public function test_guests_are_redirected_to_the_appropriate_login(): void
    {
        $this->get('/backend/beranda')->assertRedirect(route('backend.login'));
        $this->get('/cart')->assertRedirect(route('auth.redirect'));
    }

    public function test_customer_cannot_access_any_backend_or_product_photo_endpoint(): void
    {
        $this->actingAs($this->makeUser());
        foreach ($this->app['router']->getRoutes() as $route) {
            $uri = $route->uri();
            if ((!str_starts_with($uri, 'backend/') && !str_starts_with($uri, 'foto-produk/'))
                || in_array($uri, ['backend/login', 'backend/logout'], true)) {
                continue;
            }
            $url = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);
            $this->call($route->methods()[0], $url)->assertForbidden();
        }
    }

    /** @dataProvider backendLoginCases */
    public function test_backend_login_requires_active_admin_role(string $role, int $status, bool $allowed): void
    {
        $user = $this->makeUser(['role' => $role, 'status' => $status]);
        $response = $this->from('/backend/login')->post('/backend/login', [
            'email' => $user->email, 'password' => 'PasswordUji!123',
        ]);
        if ($allowed) {
            $response->assertRedirect(route('backend.beranda'));
            $this->assertAuthenticatedAs($user);
            foreach (['beranda', 'user', 'kategori', 'produk', 'customer', 'laporan/formuser', 'laporan/formproduk'] as $page) {
                $this->get('/backend/'.$page)->assertOk();
            }
        } else {
            $response->assertRedirect('/backend/login')->assertSessionHas('error');
            $this->assertGuest();
        }
    }

    public static function backendLoginCases(): array
    {
        return [
            'admin' => ['0', 1, true],
            'super admin' => ['1', 1, true],
            'customer' => ['2', 1, false],
            'inactive admin' => ['0', 0, false],
            'inactive super admin' => ['1', 0, false],
        ];
    }

    public function test_existing_inactive_sessions_and_wrong_roles_are_forbidden(): void
    {
        $this->actingAs($this->makeUser(['role' => '0', 'status' => 0]))->get('/backend/beranda')->assertForbidden();
        $this->actingAs($this->makeUser(['status' => 0]))->get('/cart')->assertForbidden();
        $this->actingAs($this->makeUser(['role' => '1']))->get('/cart')->assertForbidden();
    }

    public function test_backend_customer_exposes_only_the_existing_index_action(): void
    {
        $this->makeCustomer();
        $this->actingAs($this->makeUser(['role' => '0']));
        $this->get('/backend/customer')->assertOk()->assertDontSee('<th>Aksi</th>', false)->assertDontSee('action="#"', false);
        $this->get('/backend/customer/create')->assertNotFound();
        $this->get('/backend/customer/1')->assertNotFound();
        $this->get('/backend/customer/1/edit')->assertNotFound();
        $this->post('/backend/customer')->assertStatus(405);
        $this->put('/backend/customer/1')->assertNotFound();
        $this->delete('/backend/customer/1')->assertNotFound();
    }

    public function test_catalog_reuses_existing_view_with_working_pagination_and_active_products_only(): void
    {
        $this->makeProduct(['nama_produk' => 'Produk tersembunyi', 'status' => 0]);
        for ($i = 1; $i <= 7; $i++) {
            $this->makeProduct(['nama_produk' => 'Produk aktif '.$i]);
        }
        $this->get('/produk')->assertOk()->assertViewIs('v_produk.produkkategori')
            ->assertViewHas('produk', fn ($products) => $products->total() === 7 && $products->count() === 6)
            ->assertDontSee('Produk tersembunyi')->assertSee('page=2', false)->assertDontSee('action="3"', false);
        $this->get('/produk?page=2')->assertOk()
            ->assertViewHas('produk', fn ($products) => $products->currentPage() === 2 && $products->count() === 1);
    }

    public function test_product_buttons_target_the_cart_once_per_product_and_cart_is_linked(): void
    {
        $product = $this->makeProduct();
        foreach (['/beranda', '/produk', '/produk/kategori/'.$product->kategori_id, '/produk/detail/'.$product->id] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('href="'.route('order.cart').'"', false)
                ->assertDontSee('action="3"', false)->assertDontSee('action="#"', false);
            $this->assertSame(1, substr_count($response->getContent(), 'action="'.route('order.addToCart', $product->id).'"'));
        }
    }

    public function test_empty_catalog_can_render(): void
    {
        $this->get('/produk')->assertOk()->assertSee('Produk belum tersedia.');
    }
}
