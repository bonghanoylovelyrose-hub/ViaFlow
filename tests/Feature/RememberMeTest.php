<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [['admin', 'admin.dashboard'], ['consumer', 'consumer.dashboard'], ['meter_reader', 'meter-reading.dashboard']];
    }

    private function account(string $role): array
    {
        $user = User::factory()->create(['role' => $role, 'email' => $role === 'admin' ? 'admin@example.com' : $role . '@example.com', 'password' => 'password123']);
        if ($role === 'consumer') {
            Consumer::create(['user_id' => $user->id, 'full_name' => 'Test Consumer', 'account_number' => '1001', 'meter_number' => 'MT-1001', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        }

        return [$user, $role === 'consumer' ? 'MT-1001' : $user->email];
    }

    private function cookie($response, string $prefix): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), $prefix)) return $cookie;
        }

        return null;
    }

    private function restart(?Cookie $cookie = null): void
    {
        $this->flushSession();
        Auth::forgetGuards();
        if ($cookie) $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }

    #[DataProvider('roles')]
    public function test_remembered_login_restores_role_after_session_is_lost(string $role, string $view): void
    {
        [$user, $login] = $this->account($role);
        $response = $this->post('/login', ['login' => $login, 'password' => 'password123', 'remember' => '1']);
        $response->assertRedirect(route('dashboard'));
        $cookie = $this->cookie($response, 'remember_');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());
        $this->assertNotNull($user->fresh()->remember_expires_at);
        $this->restart($cookie);
        $this->get('/dashboard')->assertOk()->assertViewIs($view);
        $this->assertAuthenticatedAs($user);
        $this->get('/login')->assertRedirect(route('dashboard'));
    }

    #[DataProvider('roles')]
    public function test_unchecked_login_has_no_remember_cookie_and_ends_with_session(string $role, string $view): void
    {
        [$user, $login] = $this->account($role);
        $response = $this->post('/login', ['login' => $login, 'password' => 'password123']);
        $response->assertRedirect(route('dashboard'));
        $this->assertNull($this->cookie($response, 'remember_'));
        $session = $this->cookie($response, config('session.cookie'));
        $this->assertNotNull($session);
        $this->assertSame(0, $session->getExpiresTime());
        $this->restart();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_expired_remember_cookie_cannot_restore_login(): void
    {
        [$user, $login] = $this->account('admin');
        $response = $this->post('/login', ['login' => $login, 'password' => 'password123', 'remember' => '1']);
        $cookie = $this->cookie($response, 'remember_');
        $user->forceFill(['remember_expires_at' => now()->subSecond()])->save();
        $this->restart($cookie);
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_logout_invalidates_the_remember_token(): void
    {
        [$user, $login] = $this->account('admin');
        $response = $this->post('/login', ['login' => $login, 'password' => 'password123', 'remember' => '1']);
        $cookie = $this->cookie($response, 'remember_');
        $this->post('/logout')->assertRedirect(route('login'));
        $this->restart($cookie);
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_https_configuration_sets_secure_and_httponly_cookies(): void
    {
        config(['session.secure' => true]);
        [$user, $login] = $this->account('admin');
        $response = $this->post('/login', ['login' => $login, 'password' => 'password123', 'remember' => '1']);
        $this->assertTrue($this->cookie($response, 'remember_')->isSecure());
        $this->assertTrue($this->cookie($response, 'remember_')->isHttpOnly());
    }
}