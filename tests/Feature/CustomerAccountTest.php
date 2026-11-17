<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Support\StorefrontTestCase;

class CustomerAccountTest extends StorefrontTestCase
{
    private function profileData(array $attributes = []): array
    {
        return array_replace([
            'nama' => 'Nama Baru', 'email' => 'baru@example.test', 'hp' => '081234567890',
            'alamat' => 'Jalan Uji 1', 'pos' => '12345',
        ], $attributes);
    }

    public function test_customer_can_view_and_update_own_account_without_changing_credentials(): void
    {
        $customer = $this->makeCustomer();
        $user = $customer->user;
        $oldPassword = $user->password;
        $this->actingAs($user)->get(route('customer.akun', $user->id))->assertOk()
            ->assertSee('value="'.$user->nama.'"', false)->assertSee('value="'.$user->email.'"', false);
        $this->put(route('customer.updateakun', $user->id), $this->profileData([
            'role' => '1', 'status' => 0, 'password' => 'attacker-password', 'user_id' => 999,
        ]))->assertRedirect(route('customer.akun', $user->id))->assertSessionHas('success');
        $this->assertDatabaseHas('user', ['id' => $user->id, 'nama' => 'Nama Baru', 'email' => 'baru@example.test', 'role' => '2', 'status' => 1, 'password' => $oldPassword]);
        $this->assertDatabaseHas('customer', ['id' => $customer->id, 'user_id' => $user->id, 'alamat' => 'Jalan Uji 1', 'pos' => '12345']);
    }

    public function test_customer_cannot_read_or_update_another_account(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $this->actingAs($owner->user)->get(route('customer.akun', $other->user_id))->assertForbidden();
        $this->put(route('customer.updateakun', $other->user_id), $this->profileData())->assertForbidden();
        $this->assertDatabaseHas('user', ['id' => $other->user_id, 'email' => $other->user->email, 'nama' => $other->user->nama]);
        $this->assertDatabaseHas('customer', ['id' => $other->id, 'alamat' => null]);
    }

    public function test_email_validation_uses_user_table_and_allows_unchanged_email(): void
    {
        $customer = $this->makeCustomer();
        $other = $this->makeUser();
        $this->actingAs($customer->user)->put(route('customer.updateakun', $customer->user_id), $this->profileData(['email' => $customer->user->email]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->put(route('customer.updateakun', $customer->user_id), $this->profileData(['email' => $other->email]))
            ->assertSessionHasErrors('email');
        $this->put(route('customer.updateakun', $customer->user_id), $this->profileData(['email' => 'invalid']))->assertSessionHasErrors('email');
        $data = $this->profileData();
        unset($data['email']);
        $this->put(route('customer.updateakun', $customer->user_id), $data)->assertSessionHasErrors('email');
    }

    public function test_phone_and_photo_are_validated(): void
    {
        $customer = $this->makeCustomer();
        $this->actingAs($customer->user)->put(route('customer.updateakun', $customer->user_id), $this->profileData([
            'hp' => 'abcdefghijk', 'foto' => UploadedFile::fake()->create('bukan-gambar.txt', 2, 'text/plain'),
        ]))->assertSessionHasErrors(['hp', 'foto']);
        $this->assertDatabaseHas('user', ['id' => $customer->user_id, 'hp' => $customer->user->hp, 'foto' => null]);
    }

    public function test_photo_upload_and_replacement_use_an_isolated_public_directory(): void
    {
        $publicPath = sys_get_temp_dir().'/tokoonline-photo-test-'.bin2hex(random_bytes(8));
        $originalPublicPath = public_path();
        $customer = $this->makeCustomer([], $this->makeUser(['foto' => 'lama.jpg']));
        File::ensureDirectoryExists($publicPath.'/storage/img-customer');
        File::put($publicPath.'/storage/img-customer/lama.jpg', 'old test image');
        $this->app->usePublicPath($publicPath);
        try {
            $this->actingAs($customer->user)->get(route('customer.akun', $customer->user_id))->assertOk()
                ->assertSee('storage/img-customer/lama.jpg');
            $this->put(route('customer.updateakun', $customer->user_id), $this->profileData([
                'foto' => UploadedFile::fake()->image('baru.png', 30, 30),
            ]))->assertSessionHasNoErrors()->assertSessionHas('success');
            $photo = $customer->user->fresh()->foto;
            $this->assertFileExists($publicPath.'/storage/img-customer/'.$photo);
            $this->assertFileDoesNotExist($publicPath.'/storage/img-customer/lama.jpg');
            $this->assertSame([385, 400], array_slice(getimagesize($publicPath.'/storage/img-customer/'.$photo), 0, 2));
        } finally {
            $this->app->usePublicPath($originalPublicPath);
            File::deleteDirectory($publicPath);
        }
    }

    public function test_customer_without_relation_receives_controlled_not_found_response(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('customer.akun', $user->id))->assertNotFound();
        $this->put(route('customer.updateakun', $user->id), $this->profileData())->assertNotFound();
        $this->get('/cart')->assertNotFound();
    }
}
