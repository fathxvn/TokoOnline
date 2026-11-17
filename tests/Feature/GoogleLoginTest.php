<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\Support\StorefrontTestCase;

class GoogleLoginTest extends StorefrontTestCase
{
    private function mockGoogle(string $id = 'google-subject-1', string $email = 'google@example.test', $verified = true, array $rawOverrides = []): void
    {
        $socialUser = (new GoogleUser)->setRaw(array_replace([
            'sub' => $id, 'email' => $email, 'email_verified' => $verified,
        ], $rawOverrides))->map(['id' => $id, 'email' => $email, 'name' => 'Google Customer']);
        $socialUser->token = 'mock-access-token';
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    public function test_new_google_customers_get_distinct_random_passwords(): void
    {
        $this->mockGoogle();
        $this->withSession(['url.intended' => '/backend/user'])->get('/auth/google/callback')->assertRedirect(route('beranda'));
        $first = User::where('email', 'google@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($first);
        $this->assertSame('2', (string) $first->role);
        $this->assertSame(1, (int) $first->status);
        $this->assertFalse(Hash::check('default_password', $first->password));
        $this->assertDatabaseHas('customer', ['user_id' => $first->id, 'google_id' => 'google-subject-1']);
        $this->post('/logout');

        $this->mockGoogle('google-subject-2', 'second@example.test');
        $this->get('/auth/google/callback')->assertRedirect(route('beranda'));
        $second = User::where('email', 'second@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($second);
        $this->assertNotSame($first->password, $second->password);
        $this->assertFalse(Hash::check('default_password', $second->password));
    }

    public function test_existing_customer_without_relation_is_linked_without_changing_user_data(): void
    {
        $user = $this->makeUser(['email' => 'google@example.test', 'password' => Hash::make('default_password')]);
        $original = $user->fresh()->getAttributes();
        $this->mockGoogle();
        $this->get('/auth/google/callback')->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('user', 1);
        $this->assertDatabaseHas('customer', ['user_id' => $user->id, 'google_id' => 'google-subject-1']);
    }

    public function test_existing_customer_relation_is_reused_without_overwriting_address(): void
    {
        $customer = $this->makeCustomer(['alamat' => 'Alamat lama', 'pos' => '12345']);
        $this->mockGoogle('google-subject-1', $customer->user->email);
        $this->get('/auth/google/callback')->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($customer->user);
        $this->assertDatabaseCount('customer', 1);
        $this->assertDatabaseHas('customer', ['id' => $customer->id, 'google_id' => 'google-subject-1', 'alamat' => 'Alamat lama', 'pos' => '12345']);
    }

    public function test_linked_google_subject_takes_precedence_after_profile_email_changes(): void
    {
        $customer = $this->makeCustomer(['google_id' => 'google-subject-1']);
        $other = $this->makeUser(['email' => 'google@example.test']);
        $this->mockGoogle('google-subject-1', $other->email, false);
        $this->get('/auth/google/callback')->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($customer->user);
        $this->assertDatabaseCount('customer', 1);
        $this->assertDatabaseHas('user', ['id' => $customer->user_id, 'email' => $customer->user->email]);
    }

    /** @dataProvider unverifiedClaims */
    public function test_email_linking_requires_explicitly_verified_email($verified): void
    {
        $user = $this->makeUser(['email' => 'google@example.test']);
        $this->mockGoogle('google-subject-1', $user->email, $verified);
        $this->get('/auth/google/callback')->assertRedirect(route('beranda'))->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('customer', 0);
        $this->assertDatabaseCount('user', 1);
    }

    public static function unverifiedClaims(): array
    {
        return ['false' => [false], 'missing' => [null], 'untrusted string' => ['true']];
    }

    public function test_unverified_email_cannot_create_a_new_account(): void
    {
        $this->mockGoogle('google-subject-1', 'new@example.test', false);
        $this->get('/auth/google/callback')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('user', 0);
        $this->assertDatabaseCount('customer', 0);
    }

    /** @dataProvider blockedAccounts */
    public function test_google_does_not_login_or_modify_admins_and_inactive_accounts(string $role, int $status, bool $linked): void
    {
        $user = $this->makeUser(['email' => 'google@example.test', 'role' => $role, 'status' => $status]);
        $original = $user->fresh()->getAttributes();
        if ($linked) {
            $this->makeCustomer(['google_id' => 'google-subject-1'], $user);
        }
        $this->mockGoogle();
        $this->get('/auth/google/callback')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('customer', $linked ? 1 : 0);
    }

    public static function blockedAccounts(): array
    {
        return [
            'admin email' => ['0', 1, false],
            'super admin email' => ['1', 1, false],
            'inactive customer email' => ['2', 0, false],
            'linked inactive customer' => ['2', 0, true],
            'linked admin' => ['0', 1, true],
        ];
    }

    public function test_conflicting_google_link_is_rejected(): void
    {
        $customer = $this->makeCustomer(['google_id' => 'another-google-subject']);
        $this->mockGoogle('google-subject-1', $customer->user->email);
        $this->get('/auth/google/callback')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseHas('customer', ['id' => $customer->id, 'google_id' => 'another-google-subject']);
    }

    public function test_ambiguous_existing_google_links_are_rejected(): void
    {
        $this->makeCustomer(['google_id' => 'google-subject-1']);
        $this->makeCustomer(['google_id' => 'google-subject-1']);
        $this->mockGoogle();
        $this->get('/auth/google/callback')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('customer', 2);
    }

    public function test_google_subject_must_match_the_provider_claim(): void
    {
        $this->mockGoogle('google-subject-1', 'google@example.test', true, ['sub' => 'mismatch']);
        $this->get('/auth/google/callback')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('user', 0);
    }

    public function test_invalid_oauth_state_is_rejected_without_creating_data(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        $this->get('/auth/google/callback')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('user', 0);
    }

    public function test_failed_customer_creation_rolls_back_new_user(): void
    {
        Customer::creating(function () {
            throw new \RuntimeException('Simulated customer write failure.');
        });
        $this->mockGoogle();
        $this->get('/auth/google/callback')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('user', 0);
        $this->assertDatabaseCount('customer', 0);
    }
}
