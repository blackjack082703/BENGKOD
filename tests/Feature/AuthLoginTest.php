<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_opens(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke akun Anda');
    }

    #[DataProvider('roles')]
    public function test_valid_login_opens_the_dashboard_for_the_users_role(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'rahasia123']);

        $this->followingRedirects()->post(route('login'), [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertOk()->assertViewIs($role.'.dashboard')->assertSeeText('Keluar');

        $this->assertAuthenticatedAs($user);
    }

    public static function roles(): array
    {
        return ['pasien' => ['pasien'], 'dokter' => ['dokter'], 'admin' => ['admin']];
    }

    public function test_wrong_password_returns_a_clear_error_and_preserves_only_email(): void
    {
        $user = User::factory()->create(['role' => 'pasien', 'password' => 'rahasia123']);

        $this->followingRedirects()->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'salah123',
        ])->assertOk()->assertViewIs('auth.login')
            ->assertSee('Email atau password salah')
            ->assertSee('value="'.$user->email.'"', false)
            ->assertDontSee('value="salah123"', false)
            ->assertSessionMissingInput('password');

        $this->assertGuest();
    }

    public function test_unknown_email_returns_the_same_login_error(): void
    {
        $this->from(route('login'))->post(route('login'), [
            'email' => 'tidak-ada@example.com',
            'password' => 'rahasia123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah'])
            ->assertSessionHasInput('email', 'tidak-ada@example.com')
            ->assertSessionMissingInput('password');

        $this->assertGuest();
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_credentials_show_validation_errors_without_authenticating(array $data, array $errors): void
    {
        $response = $this->followingRedirects()->from(route('login'))->post(route('login'), $data)
            ->assertOk()->assertViewIs('auth.login')
            ->assertSessionMissingInput('password');

        $this->assertGuest();
        foreach ($errors as $message) {
            $response->assertSee($message);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidCredentials(): array
    {
        return [
            'missing credentials' => [[], ['email' => 'Email wajib diisi.', 'password' => 'Password wajib diisi.']],
            'missing password' => [['email' => 'pasien@example.com'], ['password' => 'Password wajib diisi.']],
            'invalid email' => [['email' => 'bukan-email', 'password' => 'rahasia123'], ['email' => 'Format email tidak valid.']],
            'long email' => [
                ['email' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.com', 'password' => 'rahasia123'],
                ['email' => 'Email maksimal 255 karakter.'],
            ],
            'array email' => [['email' => ['pasien@example.com'], 'password' => 'rahasia123'], ['email' => 'Email harus berupa teks.']],
            'array password' => [['email' => 'pasien@example.com', 'password' => ['rahasia123']], ['password' => 'Password harus berupa teks.']],
        ];
    }

    public function test_login_form_escapes_preserved_invalid_email(): void
    {
        $email = '<script>alert("email")</script>';

        $this->followingRedirects()->from(route('login'))->post(route('login'), [
            'email' => $email,
            'password' => 'rahasia123',
        ])->assertOk()->assertSee('Format email tidak valid.')
            ->assertSee('&lt;script&gt;', false)->assertDontSee($email, false)
            ->assertDontSee('value="rahasia123"', false);

        $this->assertGuest();
    }

    #[DataProvider('roleMenus')]
    public function test_dashboard_renders_own_role_menu_and_logout(string $role, array $visible, array $hidden): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->get(route($role.'.dashboard'));

        $response->assertOk()->assertViewIs($role.'.dashboard')
            ->assertSee('action="'.route('logout').'"', false)->assertSeeText('Keluar');
        foreach ($visible as $menu) {
            $response->assertSeeText($menu);
        }
        foreach ($hidden as $menu) {
            $response->assertDontSeeText($menu);
        }
    }

    public static function roleMenus(): array
    {
        return [
            'pasien' => ['pasien', ['Menu Pasien', 'Pendaftaran Periksa'], ['Menu Admin', 'Menu Dokter', 'Periksa Pasien']],
            'dokter' => ['dokter', ['Menu Dokter', 'Jadwal Periksa', 'Periksa Pasien'], ['Menu Admin', 'Menu Pasien', 'Pendaftaran Periksa']],
            'admin' => ['admin', ['Menu Admin', 'Manajemen Dokter', 'Manajemen Pasien', 'Manajemen Obat'], ['Menu Dokter', 'Menu Pasien', 'Periksa Pasien']],
        ];
    }
}
