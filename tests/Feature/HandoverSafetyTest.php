<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\Support\StorefrontTestCase;

class HandoverSafetyTest extends StorefrontTestCase
{
    /** @dataProvider references */
    public function test_referenced_user_delete_preserves_all_data_and_photo(string $kind): void
    {
        $dir = sys_get_temp_dir().'/toko-user-delete-'.bin2hex(random_bytes(8));
        $old = public_path();
        File::ensureDirectoryExists($dir.'/storage/img-user');
        $this->app->usePublicPath($dir);
        try {
            $customer = $this->makeCustomer([], $this->makeUser(['foto'=>'keep.jpg']));
            File::put($dir.'/storage/img-user/keep.jpg', 'photo-original');
            $order = $kind === 'customer' ? null : $this->makeOrder($customer, $this->makeProduct(), $kind === 'paid' ? 'Paid' : 'pending');
            if ($kind === 'payment') {
                \App\Models\PaymentAttempt::create(['order_id'=>$order->id,'gateway_reference'=>'qa-ref','amount'=>15000,'snapshot'=>[], 'status'=>'unknown']);
            }
            $this->actingAs($this->makeUser(['role'=>'0']))->deleteJson(route('backend.user.destroy',$customer->user_id))->assertUnprocessable();
            $this->assertDatabaseHas('user',['id'=>$customer->user_id]);
            $this->assertDatabaseHas('customer',['id'=>$customer->id]);
            if ($order) {
                $this->assertDatabaseHas('order',['id'=>$order->id]);
                $this->assertDatabaseHas('order_item',['order_id'=>$order->id,'quantity'=>1]);
            }
            $this->assertSame('photo-original',File::get($dir.'/storage/img-user/keep.jpg'));
            if ($kind === 'payment') $this->assertDatabaseHas('payment_attempts',['gateway_reference'=>'qa-ref']);
        } finally {
            $this->app->usePublicPath($old); File::deleteDirectory($dir);
        }
    }

    public static function references(): array { return [['customer'],['pending'],['paid'],['payment']]; }

    public function test_rejection_is_visible_after_normal_browser_form_submission(): void
    {
        $customer = $this->makeCustomer();
        $this->actingAs($this->makeUser(['role'=>'0']))
            ->from(route('backend.user.index'))
            ->delete(route('backend.user.destroy', $customer->user_id))
            ->assertRedirect(route('backend.user.index'))
            ->assertSessionHasErrors('user')->assertSessionMissing('success');
        $this->get(route('backend.user.index'))->assertOk()
            ->assertSee('User terkait customer, pesanan, atau pembayaran dan tidak dapat dihapus. Gunakan status Nonaktif.');
    }

    public function test_direct_model_deletion_cannot_bypass_guards(): void
    {
        $customer=$this->makeCustomer();
        try { $customer->user->delete(); $this->fail('Expected guard'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('user',$e->errors()); }
        $order=$this->makeOrder($customer,$this->makeProduct());
        try { $customer->delete(); $this->fail('Expected guard'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('customer',$e->errors()); }
        $this->assertDatabaseHas('order',['id'=>$order->id]);
        $this->assertDatabaseHas('user',['id'=>$customer->user_id]);
    }

    public function test_user_with_direct_order_reference_is_not_deleted(): void
    {
        $user=$this->makeUser(['role'=>'0']);
        $order=$this->makeOrder($this->makeCustomer(),$this->makeProduct());
        $order->forceFill(['user_id'=>$user->id])->save();
        $this->actingAs($this->makeUser(['role'=>'1']))->deleteJson(route('backend.user.destroy',$user))->assertUnprocessable();
        $this->assertDatabaseHas('user',['id'=>$user->id]);
        $this->assertDatabaseHas('order',['id'=>$order->id]);
    }

    public function test_unreferenced_user_and_unreferenced_customer_can_still_be_deleted(): void
    {
        $user=$this->makeUser(['role'=>'0']);$customer=$this->makeCustomer();
        $this->actingAs($this->makeUser(['role'=>'1']))->delete(route('backend.user.destroy',$user))->assertRedirect();
        $this->assertDatabaseMissing('user',['id'=>$user->id]);
        $this->delete(route('backend.customer.destroy',$customer))->assertSessionHas('success');
        $this->assertDatabaseMissing('user',['id'=>$customer->user_id]);
    }

    public function test_empty_schema_can_bootstrap_first_admin_without_seeding(): void
    {
        $this->assertDatabaseCount('user',0);
        $this->assertDatabaseCount('order',0);
        $this->artisan('app:bootstrap-admin')
            ->expectsQuestion('Nama admin','Admin QA')
            ->expectsQuestion('Email admin','admin-qa@example.test')
            ->expectsQuestion('Password admin (minimal 12 karakter)','Fixture-Password-123!')
            ->expectsQuestion('Konfirmasi password','Fixture-Password-123!')
            ->expectsOutput('Admin awal berhasil dibuat. Password tidak ditampilkan.')
            ->assertExitCode(0);
        $user=User::firstOrFail();
        $this->assertSame('1',(string)$user->role);
        $this->assertTrue(Hash::check('Fixture-Password-123!',$user->password));
        $this->post(route('backend.login'),['email'=>$user->email,'password'=>'Fixture-Password-123!'])->assertRedirect(route('backend.beranda'));
        $hash=$user->password;
        $this->artisan('app:bootstrap-admin')->expectsOutput('Admin aktif sudah tersedia. Tidak ada akun yang diubah.')->assertExitCode(1);
        $this->assertSame($hash,$user->fresh()->password);
    }

    public function test_bootstrap_does_not_overwrite_existing_customer_email(): void
    {
        $user=$this->makeUser();$hash=$user->password;
        $this->artisan('app:bootstrap-admin')
            ->expectsQuestion('Nama admin','Admin QA')->expectsQuestion('Email admin',$user->email)
            ->expectsQuestion('Password admin (minimal 12 karakter)','Fixture-Password-123!')
            ->expectsQuestion('Konfirmasi password','Fixture-Password-123!')->assertExitCode(1);
        $this->assertDatabaseCount('user',1);
        $this->assertSame($hash,$user->fresh()->password);
    }

    public function test_bootstrap_refuses_non_interactive_execution(): void
    {
        $this->artisan('app:bootstrap-admin', ['--no-interaction'=>true])
            ->expectsOutput('Perintah ini memerlukan terminal interaktif.')->assertExitCode(1);
        $this->assertDatabaseCount('user', 0);
    }

    public function test_factory_matches_real_user_schema(): void
    {
        $user=User::factory()->create();
        $this->assertNotEmpty($user->nama);
        $this->assertDatabaseHas('user',['id'=>$user->id,'role'=>'2','status'=>1]);
    }
}
