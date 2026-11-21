<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\StorefrontTestCase;

class ShippingTest extends StorefrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.rajaongkir.api_key' => 'test-only-not-a-live-key', 'services.rajaongkir.base_url' => 'https://shipping.example.test',
            'services.rajaongkir.origin_district' => '11', 'services.rajaongkir.couriers' => ['jne']]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function order()
    {
        $order = $this->makeOrder($this->makeCustomer(), $this->makeProduct());
        $order->update(['nama_penerima' => 'Penerima Uji', 'email_penerima' => 'qa@example.test', 'hp_penerima' => '081234567890', 'alamat' => 'Alamat QA', 'pos' => '12345']);
        $this->actingAs($order->customer->user);
        return $order;
    }

    private function fakeApi($cost = null): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*/destination/province' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 1, 'name' => 'Provinsi Uji']]]),
            '*/destination/city/1' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 2, 'name' => 'Kota Uji']]]),
            '*/destination/district/2' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 3, 'name' => 'Kecamatan Uji']]]),
            '*/calculate/district/domestic-cost' => $cost ?? Http::response(['meta' => ['code' => 200], 'data' => [['code' => 'jne', 'service' => 'REG', 'cost' => 9000, 'etd' => '2-3 day']]]),
        ]);
    }

    private function quote($order)
    {
        return $this->postJson(route('shipping.quote', $order), ['province_id' => 1, 'city_id' => 2, 'district_id' => 3]);
    }

    private function choose($order)
    {
        $response = $this->quote($order)->assertOk();
        return $this->postJson(route('shipping.select', $order), ['option_id' => $response->json('options.0.id'), 'cost' => 1, 'kurir' => 'fake', 'status' => 'Paid']);
    }

    public function test_quote_uses_district_endpoint_and_server_weight_then_selects_exact_server_rate(): void
    {
        $order = $this->order();
        $this->fakeApi();
        $this->choose($order)->assertOk()->assertJsonPath('summary.subtotal', 15000)->assertJsonPath('summary.shipping', 9000)->assertJsonPath('summary.total', 24000);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/calculate/district/domestic-cost') && $request['weight'] === 250 && $request['origin'] === 11 && $request['destination'] === 3 && $request['courier'] === 'jne');
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'pending', 'kurir' => 'jne', 'layanan_ongkir' => 'REG', 'biaya_ongkir' => 9000, 'total_harga' => 15000]);
        $this->assertSame('3', $order->fresh()->shipping_destination['district']['id']);
        $this->assertSame(10, $order->orderItems->first()->produk->fresh()->stok);
        $this->get(route('order.checkout', $order))->assertOk()->assertViewHas('summary', fn ($s) => $s['total'] === 24000.0);
    }

    public function test_mysql_json_key_order_does_not_invalidate_identical_destination(): void
    {
        $order = $this->order(); $this->fakeApi();
        $id = $this->quote($order)->assertOk()->json('options.0.id');
        $destination = $order->fresh()->shipping_destination;
        ksort($destination);
        foreach ($destination as &$region) ksort($region);
        unset($region);
        $order->update(['shipping_destination' => $destination]);
        $this->postJson(route('shipping.select', $order), ['option_id' => $id])->assertOk();
    }

    public function test_location_relationship_mismatch_and_unknown_service_are_rejected(): void
    {
        $order = $this->order(); $this->fakeApi();
        $this->postJson(route('shipping.quote', $order), ['province_id' => 1, 'city_id' => 99, 'district_id' => 3])->assertUnprocessable();
        $this->quote($order)->assertOk();
        $this->postJson(route('shipping.select', $order), ['option_id' => (string) \Illuminate\Support\Str::uuid(), 'cost' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'kurir' => null]);
    }

    public function test_owner_and_pending_are_required_for_both_actions(): void
    {
        $order = $this->order(); $this->fakeApi();
        $owner = $order->customer->user;
        $this->actingAs($this->makeUser());
        $this->quote($order)->assertNotFound();
        $this->postJson(route('shipping.select', $order), [])->assertNotFound();
        $this->actingAs($owner); $order->update(['status' => 'Paid']);
        $this->quote($order)->assertNotFound();
        $this->postJson(route('shipping.select', $order), [])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_quote_expires_and_is_not_counted_in_total_after_expiry(): void
    {
        $order = $this->order(); $this->fakeApi();
        $this->choose($order)->assertOk();
        $id = $order->fresh()->shipping_quote['selected'];
        $this->travel(11)->minutes();
        $this->postJson(route('shipping.select', $order), ['option_id' => $id])->assertStatus(409);
        $this->assertNull($order->fresh()->summary()['total']);
    }

    /** @dataProvider mutations */
    public function test_changed_cart_recipient_or_product_cannot_use_old_quote(string $mutation): void
    {
        $order = $this->order(); $this->fakeApi();
        $id = $this->quote($order)->assertOk()->json('options.0.id');
        if ($mutation === 'cart') {
            $this->patch(route('order.updateQuantity', $order->orderItems->first()), ['quantity' => 2])->assertRedirect();
            $this->assertNull($order->fresh()->shipping_quote);
        } elseif ($mutation === 'recipient') {
            $this->put(route('order.recipient', $order), ['nama_penerima' => 'Nama baru', 'email_penerima' => 'new@example.test', 'hp_penerima' => '081234567890', 'alamat' => 'Alamat baru', 'pos' => '12345'])->assertRedirect();
            $this->assertNull($order->fresh()->shipping_quote);
        } else {
            $order->orderItems->first()->produk->update(['berat' => 500]);
        }
        $this->postJson(route('shipping.select', $order), ['option_id' => $id])->assertStatus(409);
    }

    public static function mutations(): array { return [['cart'], ['recipient'], ['product']]; }

    /** @dataProvider providerErrors */
    public function test_provider_errors_are_sanitized_and_old_selection_is_invalidated(int $status, int $expected): void
    {
        $order = $this->order();
        $order->update(['kurir' => 'old', 'layanan_ongkir' => 'old', 'biaya_ongkir' => 1000]);
        $this->fakeApi(Http::response(['meta' => ['code' => $status, 'message' => 'sensitive-provider-detail'], 'data' => null], $status));
        $response = $this->quote($order)->assertStatus($expected);
        $this->assertStringNotContainsString('sensitive-provider-detail', $response->getContent());
        $this->assertDatabaseHas('order', ['id' => $order->id, 'kurir' => null, 'biaya_ongkir' => 0]);
    }

    public static function providerErrors(): array { return [[401,503],[403,503],[429,503],[500,502]]; }

    public function test_timeout_empty_results_and_invalid_cost_are_handled(): void
    {
        $order=$this->order();
        $this->fakeApi(fn () => throw new ConnectionException('sensitive-detail'));
        $this->quote($order)->assertStatus(503)->assertDontSee('sensitive-detail');
        $this->fakeApi(Http::response(['meta'=>['code'=>200],'data'=>[]]));
        $this->quote($order)->assertUnprocessable();
        $this->fakeApi(Http::response(['meta'=>['code'=>200],'data'=>[['code'=>'jne','service'=>'REG','cost'=>-1]]]));
        $this->quote($order)->assertStatus(502);
    }

    public function test_missing_origin_schema_or_recipient_blocks_calculation(): void
    {
        $order=$this->order(); $this->fakeApi();
        config(['services.rajaongkir.origin_district'=>null]);
        $this->quote($order)->assertStatus(503);
        config(['services.rajaongkir.origin_district'=>'11']);
        $order->update(['alamat'=>'']);
        $this->quote($order)->assertUnprocessable();
        Schema::table('order', fn ($table) => $table->dropColumn(['shipping_destination','shipping_quote']));
        $this->quote($order)->assertStatus(503);
    }

    public function test_location_cache_reduces_calls_and_requires_customer(): void
    {
        $this->fakeApi(); $this->actingAs($this->makeUser());
        $this->getJson(route('shipping.provinces'))->assertOk();
        $this->getJson(route('shipping.provinces'))->assertOk();
        Http::assertSentCount(1);
        $this->actingAs($this->makeUser(['role'=>'0']))->getJson(route('shipping.provinces'))->assertForbidden();
    }

    public function test_stock_active_product_and_empty_cart_are_checked(): void
    {
        $order=$this->order(); $this->fakeApi();
        $product=$order->orderItems->first()->produk;
        $product->update(['stok'=>0]); $this->quote($order)->assertUnprocessable();
        $product->update(['stok'=>10,'status'=>0]); $this->quote($order)->assertUnprocessable();
        $order->orderItems()->delete(); $this->quote($order)->assertUnprocessable();
    }
}
