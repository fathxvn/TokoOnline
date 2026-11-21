<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\StorefrontTestCase;

class CheckoutAndOrdersTest extends StorefrontTestCase
{
    private function recipientData(array $overrides = []): array
    {
        return array_replace([
            'nama_penerima' => 'Penerima Pesanan', 'email_penerima' => 'penerima@example.test',
            'hp_penerima' => '081234567890', 'alamat' => "Jalan Pengujian\nNomor 10", 'pos' => '12345',
        ], $overrides);
    }

    private function readyOrder(string $status = 'Paid'): Order
    {
        $order = $this->makeOrder($this->makeCustomer(), $this->makeProduct(), $status);
        $order->update($this->recipientData([
            'kurir' => 'KURIR-UJI', 'layanan_ongkir' => 'LAYANAN-UJI', 'biaya_ongkir' => 9000,
            'total_berat' => 250, 'estimasi_ongkir' => '2-3 hari',
        ]));

        return $order;
    }

    public function test_checkout_loads_profile_and_computes_items_weight_and_subtotal_on_server(): void
    {
        $customer = $this->makeCustomer(['alamat' => 'Alamat profil', 'pos' => '12345']);
        $order = $this->makeOrder($customer, $this->makeProduct());
        $order->orderItems()->firstOrFail()->update(['quantity' => 2]);
        $order->update(['total_harga' => 1, 'total_berat' => 1]);
        $this->actingAs($customer->user)->get(route('order.checkout', $order->id))->assertOk()
            ->assertViewHas('recipient', fn ($data) => $data['alamat'] === 'Alamat profil' && $data['nama_penerima'] === $customer->user->nama)
            ->assertViewHas('summary', fn ($data) => $data['subtotal'] === 30000.0 && $data['weight'] === 500 && $data['shipping'] === null && $data['total'] === null);
    }

    public function test_saving_recipient_keeps_pending_and_ignores_client_supplied_prices_status_and_shipping(): void
    {
        $order = $this->readyOrder('pending');
        $originalUser = $order->customer->user->getAttributes();
        $this->actingAs($order->customer->user)->put(route('order.recipient', $order->id), $this->recipientData([
            'nama_penerima' => 'Penerima lain', 'harga' => 1, 'total_harga' => 1, 'total_berat' => 1,
            'status' => 'Paid', 'kurir' => 'PALSU', 'biaya_ongkir' => 1, 'customer_id' => 999,
        ]))->assertRedirect(route('order.checkout', $order->id))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order', [
            'id' => $order->id, 'status' => 'pending', 'nama_penerima' => 'Penerima lain',
            'total_harga' => 15000, 'total_berat' => 250, 'kurir' => null, 'layanan_ongkir' => null, 'biaya_ongkir' => 0,
        ]);
        $this->assertSame($originalUser, $order->customer->user->fresh()->getAttributes());
        $this->assertSame(10, $order->orderItems->first()->produk->stok);
        $order->customer->user->update(['nama' => 'Profil diganti']);
        $this->get(route('order.checkout', $order->id))->assertViewHas('recipient', fn ($data) => $data['nama_penerima'] === 'Penerima lain');
    }

    public function test_invalid_receiver_fields_are_rejected_without_saving(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), $this->makeProduct());
        $this->actingAs($order->customer->user)->putJson(route('order.recipient', $order->id), $this->recipientData([
            'nama_penerima' => '', 'email_penerima' => 'invalid', 'hp_penerima' => 'abc', 'alamat' => '', 'pos' => 'xxx',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['nama_penerima', 'email_penerima', 'hp_penerima', 'alamat', 'pos']);
        $this->assertDatabaseHas('order', ['id' => $order->id, 'nama_penerima' => null, 'status' => 'pending']);
    }

    public function test_recipient_write_rechecks_stock_and_rejects_unavailable_products(): void
    {
        $product = $this->makeProduct(['stok' => 0]);
        $order = $this->makeOrder($this->makeCustomer(), $product);
        $this->actingAs($order->customer->user)->putJson(route('order.recipient', $order->id), $this->recipientData())
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $product->update(['stok' => 10, 'status' => 0]);
        $this->putJson(route('order.recipient', $order->id), $this->recipientData())
            ->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->assertDatabaseHas('order', ['id' => $order->id, 'nama_penerima' => null]);
    }

    public function test_pending_cart_edit_invalidates_old_shipping_quote(): void
    {
        $order = $this->readyOrder('pending');
        $item = $order->orderItems()->firstOrFail();
        $this->actingAs($order->customer->user)->patch(route('order.updateQuantity', $item->id), ['quantity' => 2])->assertRedirect();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'pending', 'kurir' => null, 'biaya_ongkir' => 0, 'total_harga' => 30000]);
    }

    public function test_customer_order_invoice_checkout_and_writes_are_owner_only(): void
    {
        $other = $this->makeCustomer();
        $pending = $this->readyOrder('pending');
        $paid = $this->readyOrder();
        $this->actingAs($other->user);
        foreach ([route('order.checkout', $pending), route('order.show', $paid), route('order.invoice', $pending), route('order.invoice', $paid)] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->put(route('order.recipient', $pending), $this->recipientData())->assertNotFound();
        $this->assertDatabaseHas('order', ['id' => $pending->id, 'nama_penerima' => 'Penerima Pesanan']);
    }

    public function test_history_excludes_pending_and_unknown_states_and_accepts_module_case_variants(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        foreach (['pending', 'Paid', 'paid', 'Kirim', 'Selesai', 'invalid'] as $status) {
            $this->makeOrder($customer, $product, $status);
        }
        $this->readyOrder(); // Other customer's transaction must not be listed.
        $this->actingAs($customer->user)->get(route('order.history'))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 4 && $orders->every(fn ($order) => $order->customer_id === $customer->id));
    }

    public function test_transaction_is_not_editable_as_cart_or_recipient(): void
    {
        $order = $this->readyOrder();
        $item = $order->orderItems()->firstOrFail();
        $this->actingAs($order->customer->user)->get(route('order.checkout', $order))->assertNotFound();
        $this->put(route('order.recipient', $order), $this->recipientData())->assertNotFound();
        $this->patch(route('order.updateQuantity', $item), ['quantity' => 3])->assertNotFound();
        $this->delete(route('order.removeItem', $item))->assertNotFound();
        $this->get(route('order.show', $order))->assertOk()->assertViewHas('summary', fn ($s) => $s['total'] === 24000.0);
    }

    public function test_invoice_is_a_printable_order_summary_and_missing_shipping_is_not_shown_as_free(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), $this->makeProduct());
        $this->actingAs($order->customer->user)->get(route('order.invoice', $order))->assertOk()
            ->assertViewIs('v_order.invoice')->assertSee('bukan bukti pembayaran')->assertSee('belum dibayar')
            ->assertViewHas('summary', fn ($s) => $s['shipping'] === null && $s['total'] === null);
    }

    public function test_checkout_empty_cart_is_not_finalized(): void
    {
        $customer = $this->makeCustomer();
        $order = Order::create(['customer_id' => $customer->id, 'status' => 'pending', 'total_harga' => 0]);
        $this->actingAs($customer->user)->get(route('order.checkout', $order))->assertRedirect(route('order.cart'));
        $this->putJson(route('order.recipient', $order), $this->recipientData())->assertUnprocessable();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_invoice_preserves_stored_transaction_subtotal_if_historical_items_are_missing(): void
    {
        $order = $this->readyOrder();
        $order->orderItems()->delete();
        $this->actingAs($order->customer->user)->get(route('order.invoice', $order))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['subtotal'] === 15000.0 && $s['total'] === 24000.0 && !$s['items_match'])
            ->assertSee('berbeda dari subtotal transaksi tersimpan');
    }

    public function test_checkout_can_render_before_additive_migration_but_cannot_write_new_fields(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), $this->makeProduct());
        Schema::table('order', fn ($table) => $table->dropColumn(['nama_penerima', 'email_penerima', 'hp_penerima']));
        $this->actingAs($order->customer->user)->get(route('order.checkout', $order))->assertOk()
            ->assertViewHas('recipientSchemaReady', false)->assertSee('migrasi additive');
        $this->putJson(route('order.recipient', $order), $this->recipientData())->assertStatus(503);
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_admin_shipping_requires_paid_complete_order_and_preserves_server_shipping_values(): void
    {
        $order = $this->readyOrder();
        $this->actingAs($this->makeUser(['role' => '0']))->patch(route('backend.pesanan.shipping', $order), [
            'status' => 'Kirim', 'noresi' => 'RESI-UJI-123', 'biaya_ongkir' => 1, 'kurir' => 'PALSU',
        ])->assertRedirect(route('backend.pesanan.show', $order));
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'Kirim', 'noresi' => 'RESI-UJI-123', 'kurir' => 'KURIR-UJI', 'biaya_ongkir' => 9000]);
        $this->patch(route('backend.pesanan.shipping', $order), ['status' => 'Selesai', 'noresi' => 'JANGAN-DIGANTI'])->assertRedirect();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'Selesai', 'noresi' => 'RESI-UJI-123']);
        $this->patchJson(route('backend.pesanan.shipping', $order), ['status' => 'Kirim', 'noresi' => 'REPLAY'])->assertUnprocessable();
    }

    /** @dataProvider invalidShippingTransitions */
    public function test_admin_cannot_fake_payment_or_skip_shipping_states(string $current, string $target): void
    {
        $order = $this->readyOrder($current);
        $this->actingAs($this->makeUser(['role' => '1']))->patchJson(route('backend.pesanan.shipping', $order), ['status' => $target, 'noresi' => 'RESI-UJI'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => $current, 'noresi' => null]);
    }

    public static function invalidShippingTransitions(): array
    {
        return [['pending', 'Kirim'], ['pending', 'Paid'], ['Paid', 'Paid'], ['Paid', 'Selesai'], ['Kirim', 'Paid'], ['Selesai', 'Kirim']];
    }

    /** @dataProvider missingShippingRequirements */
    public function test_shipping_rejects_missing_prerequisites(string $field, $value): void
    {
        $order = $this->readyOrder();
        $order->update([$field => $value]);
        $this->actingAs($this->makeUser(['role' => '0']))->patchJson(route('backend.pesanan.shipping', $order), ['status' => 'Kirim', 'noresi' => 'RESI-UJI'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'Paid']);
    }

    public static function missingShippingRequirements(): array
    {
        return [['kurir', null], ['layanan_ongkir', null], ['biaya_ongkir', -1], ['alamat', ''], ['pos', '']];
    }

    public function test_tracking_number_is_required_for_shipping(): void
    {
        $order = $this->readyOrder();
        $this->actingAs($this->makeUser(['role' => '0']))->patchJson(route('backend.pesanan.shipping', $order), ['status' => 'Kirim'])
            ->assertUnprocessable()->assertJsonValidationErrors('noresi');
        $this->patchJson(route('backend.pesanan.shipping', $order), ['status' => 'Kirim', 'noresi' => '<script>'])
            ->assertUnprocessable()->assertJsonValidationErrors('noresi');
    }

    public function test_admin_list_filters_invoice_and_chart_exclude_pending_by_default(): void
    {
        $paid = $this->readyOrder();
        $paid->forceFill(['created_at' => '2026-03-10 10:00:00'])->save();
        $pending = $this->readyOrder('pending');
        $pending->forceFill(['created_at' => '2026-03-11 10:00:00'])->save();
        $this->actingAs($this->makeUser(['role' => '1']))->get(route('backend.pesanan.index'))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1 && $orders->first()->id === $paid->id);
        $this->get(route('backend.pesanan.index', ['status' => 'pending']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1 && $orders->first()->id === $pending->id);
        $this->get(route('backend.pesanan.show', $paid))->assertOk();
        $this->get(route('backend.pesanan.show', $pending))->assertOk();
        $this->get(route('backend.pesanan.invoice', $paid))->assertOk()->assertViewHas('summary', fn ($s) => $s['subtotal'] === 15000.0 && $s['shipping'] === 9000.0 && $s['total'] === 24000.0);
        $this->get(route('backend.pesanan.chart', ['year' => 2026]))->assertOk()
            ->assertViewHas('counts', fn ($counts) => count($counts) === 12 && $counts[3] === 1 && array_sum($counts) === 1);
        $this->getJson(route('backend.pesanan.chart', ['year' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('backend.pesanan.index', ['status' => 'invalid']))->assertUnprocessable();
    }

    public function test_guest_and_customer_cannot_access_admin_invoice_or_shipping(): void
    {
        $order = $this->readyOrder();
        $this->get(route('backend.pesanan.invoice', $order))->assertRedirect(route('backend.login'));
        $this->get(route('order.invoice', $order))->assertRedirect(route('auth.redirect'));
        $this->actingAs($order->customer->user)->get(route('backend.pesanan.invoice', $order))->assertForbidden();
        $this->patchJson(route('backend.pesanan.shipping', $order), ['status' => 'Kirim', 'noresi' => 'RESI-UJI'])->assertForbidden();
        $this->get(route('backend.pesanan.chart'))->assertForbidden();
    }

    public function test_manual_customer_deletion_with_order_is_blocked_and_hook_remains_intact(): void
    {
        $order = $this->readyOrder('pending');
        $customer = $order->customer;
        $this->actingAs($this->makeUser(['role' => '0']))->delete(route('backend.customer.destroy', $customer))->assertSessionHas('msgError');
        $this->assertDatabaseHas('customer', ['id' => $customer->id]);
        $this->assertDatabaseHas('user', ['id' => $customer->user_id]);
        $withoutOrder = $this->makeCustomer();
        $this->delete(route('backend.customer.destroy', $withoutOrder))->assertSessionHas('success');
        $this->assertDatabaseMissing('customer', ['id' => $withoutOrder->id]);
        $this->assertDatabaseMissing('user', ['id' => $withoutOrder->user_id]);
    }
}
