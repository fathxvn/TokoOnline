<?php

namespace Tests\Feature;

use App\Models\FotoProduk;
use Illuminate\Support\Facades\File;
use Tests\Support\StorefrontTestCase;

class ProductDeletionTest extends StorefrontTestCase
{
    /** @dataProvider references */
    public function test_product_deletion_preserves_referenced_items_and_files(?string $status): void
    {
        $dir = sys_get_temp_dir().'/toko-delete-'.bin2hex(random_bytes(8));
        $original = public_path();
        File::ensureDirectoryExists($dir.'/storage/img-produk');
        $this->app->usePublicPath($dir);
        try {
            $product = $this->makeProduct();
            $photo = FotoProduk::create(['produk_id' => $product->id, 'foto' => 'tambahan.jpg']);
            $files = ['produk-uji.jpg', 'thumb_lg_produk-uji.jpg', 'thumb_md_produk-uji.jpg', 'thumb_sm_produk-uji.jpg', 'tambahan.jpg'];
            foreach ($files as $file) File::put($dir.'/storage/img-produk/'.$file, 'original bytes');
            $order = $status ? $this->makeOrder($this->makeCustomer(), $product, $status) : null;
            $this->actingAs($this->makeUser(['role' => '0']));
            $response = $this->delete(route('backend.produk.destroy', $product));
            $response->assertRedirect(route('backend.produk.index'));
            if ($status) {
                $message = 'Produk sudah digunakan dalam pesanan dan tidak dapat dihapus. Gunakan status Blok.';
                $response->assertSessionHas('msgError', $message)->assertSessionMissing('success');
                $this->assertDatabaseHas('produk', ['id' => $product->id]);
                $this->assertDatabaseHas('foto_produk', ['id' => $photo->id]);
                $this->assertDatabaseHas('order_item', ['order_id' => $order->id, 'produk_id' => $product->id, 'quantity' => 1]);
                foreach ($files as $file) $this->assertSame('original bytes', File::get($dir.'/storage/img-produk/'.$file));
                $this->get(route('backend.produk.index'))->assertOk()->assertSee($message);
            } else {
                $response->assertSessionHas('success')->assertSessionMissing('msgError');
                $this->assertDatabaseMissing('produk', ['id' => $product->id]);
                $this->assertDatabaseMissing('foto_produk', ['id' => $photo->id]);
                foreach ($files as $file) $this->assertFileDoesNotExist($dir.'/storage/img-produk/'.$file);
            }
        } finally {
            $this->app->usePublicPath($original);
            File::deleteDirectory($dir);
        }
    }

    public static function references(): array
    {
        return [['pending'], ['Paid'], [null]];
    }
}
