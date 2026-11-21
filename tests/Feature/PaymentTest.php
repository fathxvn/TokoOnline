<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\StorefrontTestCase;

class PaymentTest extends StorefrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.is_production'=>false,'services.midtrans.server_key'=>'SB-Mid-server-test-only',
            'services.midtrans.client_key'=>'SB-Mid-client-test-only','services.midtrans.merchant_id'=>'merchant-test',
            'services.rajaongkir.origin_district'=>'11','services.rajaongkir.couriers'=>['jne']]);
        Http::preventStrayRequests();
    }

    private function readyOrder($product = null, $customer = null): Order
    {
        $order=$this->makeOrder($customer ?? $this->makeCustomer(),$product ?? $this->makeProduct());
        $order->update(['nama_penerima'=>'Penerima QA','email_penerima'=>'payment@example.test','hp_penerima'=>'081234567890',
            'alamat'=>'Alamat QA','pos'=>'12345','kurir'=>'jne','layanan_ongkir'=>'REG','biaya_ongkir'=>9000,'estimasi_ongkir'=>'2 hari',
            'shipping_destination'=>['province'=>['id'=>'1','name'=>'Provinsi QA'],'city'=>['id'=>'2','name'=>'Kota QA'],'district'=>['id'=>'3','name'=>'Kecamatan QA']]]);
        $order->update(['shipping_quote'=>['selected'=>'option-qa','expires_at'=>now()->addMinutes(10)->timestamp,'fingerprint'=>$order->shippingFingerprint()]]);
        $this->actingAs($order->customer->user);
        return $order;
    }

    private function fakeGateway($snap = null, $status = null): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions'=>$snap ?? Http::response(['token'=>'qa-snap-token']),
            'https://api.sandbox.midtrans.com/v2/*/status'=>$status ?? Http::response(['status_code'=>'404'],404),
        ]);
    }

    private function start(Order $order)
    {
        return $this->postJson(route('payment.start',$order),['gross_amount'=>1,'total_harga'=>1,'status'=>'Paid']);
    }

    private function provider(PaymentAttempt $attempt, string $status='settlement', array $extra=[]): array
    {
        return array_replace(['order_id'=>$attempt->gateway_reference,'gross_amount'=>(string)$attempt->amount.'.00',
            'currency'=>'IDR','transaction_status'=>$status,'fraud_status'=>'accept','transaction_id'=>'tx-qa-'.$attempt->id,'status_code'=>'200','merchant_id'=>'merchant-test'],$extra);
    }

    private function notification(PaymentAttempt $attempt, array $extra=[]): array
    {
        $data=array_replace(['order_id'=>$attempt->gateway_reference,'gross_amount'=>(string)$attempt->amount.'.00','status_code'=>'200','transaction_status'=>'settlement'],$extra);
        $data['signature_key']=hash('sha512',$data['order_id'].$data['status_code'].$data['gross_amount'].config('services.midtrans.server_key'));
        return $data;
    }

    public function test_start_uses_server_amount_reserves_stock_and_double_click_reuses_attempt(): void
    {
        $order=$this->readyOrder();$this->fakeGateway();
        $this->start($order)->assertOk();$this->start($order)->assertOk();
        $this->assertDatabaseCount('payment_attempts',1);
        $attempt=PaymentAttempt::firstOrFail();
        $this->assertSame(24000,$attempt->amount);
        $this->assertSame(9,$order->orderItems->first()->produk->fresh()->stok);
        Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r['transaction_details']['gross_amount']===24000 && collect($r['item_details'])->sum(fn($row)=>$row['price']*$row['quantity'])===24000);
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'awaiting_payment','total_harga'=>15000]);
        $this->postJson(route('payment.token',$order))->assertOk()->assertJsonPath('token','qa-snap-token');
        $this->get(route('payment.show',$order))->assertOk()->assertDontSee(config('services.midtrans.server_key'));
    }

    public function test_timeout_retains_reference_reservation_and_never_sends_second_create(): void
    {
        $order=$this->readyOrder();$this->fakeGateway(fn()=>throw new ConnectionException('sensitive-transport-detail'));
        $this->start($order)->assertOk()->assertJsonPath('status','unknown')->assertDontSee('sensitive-transport-detail');
        $reference=PaymentAttempt::firstOrFail()->gateway_reference;
        $this->start($order)->assertOk();
        $this->postJson(route('payment.sync',$order))->assertStatus(409);
        $this->assertSame($reference,PaymentAttempt::firstOrFail()->gateway_reference);
        $this->get(route('payment.show',$order))->assertOk()->assertSee($reference)->assertSee('jangan membayar ulang')->assertSee('dukungan Midtrans');
        $this->assertSame(9,$order->orderItems->first()->produk->fresh()->stok);
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/status'));
        $this->assertDatabaseCount('payment_attempts',1);
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'awaiting_payment']);
    }

    public function test_all_cart_recipient_shipping_and_product_mutations_are_frozen(): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();
        $item=$order->orderItems->first();
        $this->postJson(route('order.addToCart',$item->produk_id))->assertStatus(409);
        $this->patchJson(route('order.updateQuantity',$item),['quantity'=>2])->assertNotFound();
        $this->deleteJson(route('order.removeItem',$item))->assertNotFound();
        $this->putJson(route('order.recipient',$order),[])->assertNotFound();
        $this->postJson(route('shipping.quote',$order),[])->assertNotFound();
        $this->postJson(route('shipping.select',$order),[])->assertNotFound();
        $this->get(route('order.cart'))->assertRedirect(route('payment.show',$order));
        $this->get(route('order.checkout',$order))->assertRedirect(route('payment.show',$order));
        $this->actingAs($this->makeUser(['role'=>'0']))->putJson(route('backend.produk.update',$item->produk_id),['stok'=>999])->assertStatus(409);
        $this->assertSame(9,$item->produk->fresh()->stok);
    }

    public function test_active_amount_and_invoice_snapshot_do_not_expire_with_shipping_quote(): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();
        $this->travel(11)->minutes();
        $this->assertEquals(24000,$order->fresh()->summary()['total']);
        $this->get(route('order.invoice',$order))->assertOk()->assertSee('belum dibayar')->assertSee('Rp 24.000');
        $this->get(route('order.history'))->assertOk()->assertViewHas('orders',fn($o)=>$o->total()===0)->assertViewHas('waitingOrders',fn($o)=>$o->count()===1);
        $this->actingAs($this->makeUser(['role'=>'0']))->get(route('backend.pesanan.chart',['year'=>now()->year]))->assertViewHas('counts',fn($counts)=>array_sum($counts)===0);
    }

    public function test_signature_rejection_does_not_call_provider_or_change_stock(): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();$a=PaymentAttempt::firstOrFail();
        $body=$this->notification($a);$body['signature_key']=str_repeat('0',128);
        $this->postJson(route('payment.webhook'),$body)->assertForbidden();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'awaiting_payment']);
    }

    public function test_signed_notification_is_not_enough_without_matching_get_status(): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();$a=PaymentAttempt::firstOrFail();
        $this->fakeGateway(null,Http::response($this->provider($a,'pending')));
        $this->postJson(route('payment.webhook'),$this->notification($a))->assertOk();
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'awaiting_payment']);
        $this->fakeGateway(null,Http::response($this->provider($a)));
        $this->postJson(route('payment.webhook'),$this->notification($a,['transaction_status'=>'pending']))->assertOk();
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'Paid']);
    }

    /** @dataProvider mismatches */
    public function test_mismatched_provider_data_is_rejected(string $field, string $value): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();$a=PaymentAttempt::firstOrFail();
        $this->fakeGateway(null,Http::response($this->provider($a,'settlement',[$field=>$value])));
        $this->postJson(route('payment.sync',$order))->assertStatus(409);
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'awaiting_payment']);
    }

    public static function mismatches(): array
    {
        return [['gross_amount','1.00'],['currency','USD'],['order_id','another-reference'],['merchant_id','another-merchant']];
    }

    /** @dataProvider unpaidStatuses */
    public function test_fraud_and_pending_are_not_paid(string $status, ?string $fraud): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();$a=PaymentAttempt::firstOrFail();
        $this->fakeGateway(null,Http::response($this->provider($a,$status,['fraud_status'=>$fraud])));
        $this->postJson(route('payment.sync',$order))->assertOk();
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'awaiting_payment']);
        $this->assertSame(9,$order->orderItems->first()->produk->fresh()->stok);
    }

    public static function unpaidStatuses(): array { return [['pending','accept'],['capture','challenge'],['capture','deny'],['capture',null],['settlement','deny'],['authorize','accept']]; }

    /** @dataProvider paidStatuses */
    public function test_payment_success_is_idempotent_and_cannot_downgrade_shipped_orders(string $status): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();$a=PaymentAttempt::firstOrFail();
        $this->fakeGateway(null,Http::response($this->provider($a,$status)));
        $this->postJson(route('payment.webhook'),$this->notification($a))->assertOk();
        $this->postJson(route('payment.webhook'),$this->notification($a))->assertOk();
        $this->assertDatabaseHas('payment_attempts',['id'=>$a->id,'status'=>'paid','stock_state'=>'consumed']);
        $this->assertSame(9,$order->orderItems->first()->produk->fresh()->stok);
        $admin=$this->makeUser(['role'=>'0']);$this->actingAs($admin)->patchJson(route('backend.pesanan.shipping',$order),['status'=>'Kirim','noresi'=>'SANDBOX-RESI-QA'])->assertRedirect();
        $this->fakeGateway(null,Http::response($this->provider($a,'pending')));
        $this->postJson(route('payment.webhook'),$this->notification($a))->assertOk();
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'Kirim']);
        $this->actingAs($admin)->patchJson(route('backend.pesanan.shipping',$order),['status'=>'Selesai'])->assertRedirect();
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'Selesai']);
        $order->orderItems->first()->produk->update(['nama_produk'=>'Nama baru']);
        $this->actingAs($order->customer->user)->get(route('order.invoice',$order))->assertOk()->assertSee('Produk Uji')->assertDontSee('Nama baru');
    }

    public static function paidStatuses(): array { return [['settlement'],['capture']]; }

    /** @dataProvider failures */
    public function test_verified_failure_releases_stock_once_and_requires_fresh_quote(string $status): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();$a=PaymentAttempt::firstOrFail();
        $this->fakeGateway(null,Http::response($this->provider($a,$status)));
        $this->postJson(route('payment.sync',$order))->assertOk();
        $this->postJson(route('payment.webhook'),$this->notification($a))->assertOk();
        $this->assertSame(10,$order->orderItems->first()->produk->fresh()->stok);
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'pending','payment_attempt_id'=>null,'shipping_quote'=>null,'biaya_ongkir'=>0]);
        $this->assertDatabaseHas('payment_attempts',['id'=>$a->id,'stock_state'=>'released','status'=>'failed']);
        $this->start($order)->assertUnprocessable();
    }

    public static function failures(): array { return [['expire'],['cancel'],['deny'],['failure']]; }

    public function test_late_verified_success_after_stock_release_requires_review_not_false_failure(): void
    {
        $order=$this->readyOrder();$this->fakeGateway();$this->start($order)->assertOk();$a=PaymentAttempt::firstOrFail();
        $this->fakeGateway(null,Http::response($this->provider($a,'expire')));
        $this->postJson(route('payment.sync',$order))->assertOk();
        $this->fakeGateway(null,Http::response($this->provider($a,'settlement')));
        $this->postJson(route('payment.webhook'),$this->notification($a))->assertOk();
        $this->assertDatabaseHas('payment_attempts',['id'=>$a->id,'status'=>'review']);
        $this->assertDatabaseHas('order',['id'=>$order->id,'status'=>'payment_review']);
    }

    public function test_two_customers_cannot_reserve_same_last_unit_sequentially(): void
    {
        $product=$this->makeProduct(['stok'=>1]);
        $first=$this->readyOrder($product);$second=$this->readyOrder($product);
        $this->fakeGateway();
        $this->actingAs($first->customer->user);$this->start($first)->assertOk();
        $this->actingAs($second->customer->user);$this->start($second)->assertUnprocessable();
        $this->assertSame(0,$product->fresh()->stok);
        $this->assertDatabaseCount('payment_attempts',1);
    }

    public function test_invalid_shipping_amount_and_configuration_do_not_reserve_stock(): void
    {
        $order=$this->readyOrder();$this->fakeGateway();
        config(['services.midtrans.is_production'=>true]);$this->start($order)->assertStatus(503);
        config(['services.midtrans.is_production'=>false]);
        $quote=$order->shipping_quote;$quote['expires_at']=now()->subMinute()->timestamp;$order->update(['shipping_quote'=>$quote]);
        $this->start($order)->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_attempts',0);
        $this->assertSame(10,$order->orderItems->first()->produk->fresh()->stok);
    }

    public function test_local_processing_failure_can_retry_without_marking_provider_success_failed(): void
    {
        $order = $this->readyOrder(); $this->fakeGateway(); $this->start($order)->assertOk();
        $attempt = PaymentAttempt::firstOrFail();
        $this->fakeGateway(null, Http::response($this->provider($attempt)));
        $failOnce = true;
        Order::updating(function ($record) use (&$failOnce) {
            if ($record->status === 'Paid' && $failOnce) {
                $failOnce = false;
                throw new \RuntimeException('Simulated local persistence failure.');
            }
        });
        $this->postJson(route('payment.webhook'), $this->notification($attempt))->assertStatus(500);
        $this->assertDatabaseHas('payment_attempts', ['id' => $attempt->id, 'status' => 'active', 'stock_state' => 'reserved']);
        $this->postJson(route('payment.webhook'), $this->notification($attempt))->assertOk();
        $this->assertDatabaseHas('payment_attempts', ['id' => $attempt->id, 'status' => 'paid', 'stock_state' => 'consumed']);
        $this->assertSame(9, $order->orderItems->first()->produk->fresh()->stok);
    }

    public function test_csrf_is_required_for_payments_but_exact_webhook_is_exempt(): void
    {
        $order = $this->readyOrder(); $this->fakeGateway(); $this->start($order)->assertOk();
        $attempt = PaymentAttempt::firstOrFail();
        $this->fakeGateway(null, Http::response($this->provider($attempt)));
        $middleware = new class($this->app, $this->app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
            protected function runningUnitTests() { return false; }
        };
        $this->app->instance(\App\Http\Middleware\VerifyCsrfToken::class, $middleware);
        $this->postJson(route('payment.start', $order))->assertStatus(419);
        $this->postJson(route('payment.sync', $order))->assertStatus(419);
        $this->postJson(route('payment.webhook'), $this->notification($attempt))->assertOk();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'Paid']);
    }

    public function test_owner_auth_and_schema_guard(): void
    {
        $order=$this->readyOrder();$owner=$order->customer->user;$this->fakeGateway();
        $this->actingAs($this->makeUser());
        $this->start($order)->assertNotFound();
        $this->get(route('payment.show',$order))->assertNotFound();
        $this->postJson(route('payment.sync',$order))->assertNotFound();
        $this->postJson(route('payment.token',$order))->assertNotFound();
        $this->actingAs($owner);
        Schema::drop('payment_reservations');
        $this->start($order)->assertStatus(503);
        Http::assertNothingSent();
    }
}
