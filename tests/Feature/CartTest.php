<?php

namespace Tests\Feature;

use App\Models\Order;
use Tests\Support\StorefrontTestCase;

class CartTest extends StorefrontTestCase
{
    public function test_add_update_and_delete_cart_items_keep_totals_and_weight_consistent(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->actingAs($customer->user)->get('/cart')->assertOk()->assertSee('Keranjang belanja kosong.');
        $this->post(route('order.addToCart', $product->id))->assertRedirect(route('order.cart'));
        $order = Order::firstOrFail();
        $item = $order->orderItems()->firstOrFail();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'total_harga' => 15000, 'total_berat' => 250]);
        $product->update(['harga' => 20000]);
        $this->post(route('order.addToCart', $product->id))->assertRedirect(route('order.cart'));
        $this->assertDatabaseCount('order', 1);
        $this->assertDatabaseCount('order_item', 1);
        $this->assertDatabaseHas('order_item', ['id' => $item->id, 'quantity' => 2, 'harga' => 15000]);
        $this->assertDatabaseHas('order', ['id' => $order->id, 'total_harga' => 30000, 'total_berat' => 500]);
        $this->patch(route('order.updateQuantity', $item->id), ['quantity' => 3, 'harga' => 1, 'total_harga' => 1])
            ->assertRedirect(route('order.cart'))->assertSessionHas('success');
        $this->assertDatabaseHas('order', ['id' => $order->id, 'total_harga' => 45000, 'total_berat' => 750]);
        $this->get('/cart')->assertOk()->assertSee('Rp. 45.000')->assertSee('750 Gram')
            ->assertSee('action="'.route('order.updateQuantity', $item->id).'"', false)
            ->assertSee('action="'.route('order.removeItem', $item->id).'"', false)
            ->assertSee('disabled', false)->assertDontSee('action="#"', false);
        $this->delete(route('order.removeItem', $item->id))->assertRedirect(route('order.cart'));
        $this->assertDatabaseCount('order_item', 0);
        $this->assertDatabaseHas('order', ['id' => $order->id, 'total_harga' => 0, 'total_berat' => 0]);
        $this->get('/cart')->assertOk()->assertSee('Keranjang belanja kosong.');
        $this->assertSame(10, $product->fresh()->stok);
    }

    public function test_removing_one_of_multiple_items_preserves_remaining_totals(): void
    {
        $customer = $this->makeCustomer();
        $first = $this->makeProduct();
        $second = $this->makeProduct(['harga' => 25000, 'berat' => 100]);
        $this->actingAs($customer->user)->post(route('order.addToCart', $first->id));
        $this->post(route('order.addToCart', $second->id));
        $order = Order::firstOrFail();
        $this->delete(route('order.removeItem', $order->orderItems()->where('produk_id', $first->id)->firstOrFail()->id))->assertRedirect(route('order.cart'));
        $this->assertDatabaseHas('order', ['id' => $order->id, 'total_harga' => 25000, 'total_berat' => 100]);
        $this->assertDatabaseCount('order_item', 1);
    }

    public function test_stock_is_enforced_when_adding_and_updating(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['stok' => 1]);
        $this->actingAs($customer->user)->post(route('order.addToCart', $product->id))->assertRedirect(route('order.cart'));
        $item = Order::firstOrFail()->orderItems()->firstOrFail();
        $this->post(route('order.addToCart', $product->id))->assertSessionHasErrors('quantity');
        $this->patch(route('order.updateQuantity', $item->id), ['quantity' => 2])->assertSessionHasErrors('quantity');
        $this->assertDatabaseHas('order_item', ['id' => $item->id, 'quantity' => 1]);
        $this->assertDatabaseHas('order', ['id' => $item->order_id, 'total_harga' => 15000]);
    }

    /** @dataProvider invalidQuantities */
    public function test_invalid_quantities_are_rejected_without_changing_cart($quantity): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, $this->makeProduct());
        $item = $order->orderItems()->firstOrFail();
        $this->actingAs($customer->user)->patchJson(route('order.updateQuantity', $item->id), ['quantity' => $quantity])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseHas('order_item', ['id' => $item->id, 'quantity' => 1]);
    }

    public static function invalidQuantities(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'fractional' => [1.5], 'text' => ['abc'], 'missing' => [null], 'overflow' => [2147483648]];
    }

    public function test_customer_cannot_update_delete_or_view_another_customers_items(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $order = $this->makeOrder($other, $this->makeProduct());
        $item = $order->orderItems()->firstOrFail();
        $this->actingAs($owner->user)->get('/cart')->assertOk()->assertSee('Keranjang belanja kosong.');
        $this->patch(route('order.updateQuantity', $item->id), ['quantity' => 2])->assertNotFound();
        $this->delete(route('order.removeItem', $item->id))->assertNotFound();
        $this->assertDatabaseHas('order_item', ['id' => $item->id, 'quantity' => 1]);
    }

    public function test_paid_orders_are_not_shown_or_modified(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, $product, 'paid');
        $item = $order->orderItems()->firstOrFail();
        $this->actingAs($customer->user)->get('/cart')->assertOk()->assertSee('Keranjang belanja kosong.');
        $this->patch(route('order.updateQuantity', $item->id), ['quantity' => 2])->assertNotFound();
        $this->delete(route('order.removeItem', $item->id))->assertNotFound();
        $this->post(route('order.addToCart', $product->id))->assertRedirect(route('order.cart'));
        $this->assertDatabaseCount('order', 2);
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'paid', 'total_harga' => 15000]);
        $this->assertDatabaseHas('order_item', ['id' => $item->id, 'quantity' => 1]);
    }

    public function test_unavailable_products_cannot_be_added_and_failures_do_not_leave_empty_orders(): void
    {
        $customer = $this->makeCustomer();
        $inactive = $this->makeProduct(['status' => 0]);
        $soldOut = $this->makeProduct(['stok' => 0]);
        $this->actingAs($customer->user)->post(route('order.addToCart', $inactive->id))->assertNotFound();
        $this->post(route('order.addToCart', 999999))->assertNotFound();
        $this->post(route('order.addToCart', $soldOut->id))->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('order', 0);
        $this->assertDatabaseCount('order_item', 0);
    }

    public function test_inactive_product_can_be_removed_but_its_quantity_cannot_be_increased(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, $this->makeProduct(['status' => 0]));
        $item = $order->orderItems()->firstOrFail();
        $this->actingAs($customer->user)->patch(route('order.updateQuantity', $item->id), ['quantity' => 2])->assertNotFound();
        $this->delete(route('order.removeItem', $item->id))->assertRedirect(route('order.cart'));
        $this->assertDatabaseCount('order_item', 0);
    }
}
