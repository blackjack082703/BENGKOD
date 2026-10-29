<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasienCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_patient_form_from_the_list(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('pasien.index'))->assertOk()->assertSee(route('pasien.create'));
        $this->get(route('pasien.create'))->assertOk()
            ->assertSee('Tambah Pasien')->assertSee(route('pasien.store'))
            ->assertSee('name="nama"', false)->assertSee('name="email"', false)
            ->assertSee('name="no_ktp"', false)->assertSee('name="no_hp"', false)
            ->assertSee('name="alamat"', false)->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)->assertSee('Simpan');
    }

    public function test_admin_creates_patient_with_medical_record_number_and_can_use_the_account(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 21));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post(route('pasien.store'), $this->validData() + ['role' => 'admin', 'no_rm' => 'palsu', 'id' => 999])
            ->assertRedirect(route('pasien.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Pasien berhasil ditambahkan.');

        $this->assertDatabaseHas('users', [
            'nama' => 'Pasien Baru', 'email' => 'baru@example.com', 'alamat' => 'Semarang',
            'no_ktp' => '0012345678901234', 'no_hp' => '081234567890',
            'no_rm' => '202610-001', 'role' => 'pasien',
        ]);
        $patient = User::where('email', 'baru@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('rahasia123', $patient->password));
        $this->assertDatabaseMissing('users', ['id' => 999]);
        $this->assertAuthenticatedAs($admin);
        $this->get(route('pasien.index'))->assertOk()->assertSee('Pasien Baru')
            ->assertSee('202610-001')->assertSee('Pasien berhasil ditambahkan.');

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'baru@example.com', 'password' => 'rahasia123'])
            ->assertRedirect(route('pasien.dashboard'));
        $this->assertAuthenticatedAs($patient);
    }

    public function test_admin_and_public_registration_share_the_same_medical_record_sequence(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 21));
        $this->post(route('register'), $this->validData())->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('pasien.store'), array_replace($this->validData(), [
            'email' => 'kedua@example.com', 'no_ktp' => '0012345678901235',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'kedua@example.com', 'no_rm' => '202610-002']);

        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 1));
        $this->post(route('pasien.store'), array_replace($this->validData(), [
            'email' => 'ketiga@example.com', 'no_ktp' => '0012345678901236',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'ketiga@example.com', 'no_rm' => '202611-001']);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_without_creating_a_patient(array $overrides, array $errors): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->from(route('pasien.create'))->post(route('pasien.store'), array_replace($this->validData(), $overrides))
            ->assertRedirect(route('pasien.create'))->assertSessionHasErrors($errors);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('medical_record_sequences', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            'required fields' => [
                ['nama' => '', 'alamat' => '', 'email' => '', 'no_ktp' => '', 'no_hp' => '', 'password' => ''],
                ['nama' => 'Nama pasien wajib diisi.', 'alamat' => 'Alamat wajib diisi.', 'email' => 'Email wajib diisi.',
                    'no_ktp' => 'No. KTP wajib diisi.', 'no_hp' => 'No. HP wajib diisi.', 'password' => 'Password wajib diisi.'],
            ],
            'invalid email' => [['email' => 'salah'], ['email' => 'Format email tidak valid.']],
            'email too long' => [['email' => str_repeat('a', 244).'@example.com'], ['email' => 'Email maksimal 255 karakter.']],
            'name too long' => [['nama' => str_repeat('a', 256)], ['nama' => 'Nama pasien maksimal 255 karakter.']],
            'address too long' => [['alamat' => str_repeat('a', 256)], ['alamat' => 'Alamat maksimal 255 karakter.']],
            'identity contains letters' => [['no_ktp' => '123ABC'], ['no_ktp' => 'No. KTP hanya boleh berisi angka.']],
            'identity too long' => [['no_ktp' => str_repeat('1', 17)], ['no_ktp' => 'No. KTP maksimal 16 karakter.']],
            'phone contains letters' => [['no_hp' => '0812ABC'], ['no_hp' => 'No. HP hanya boleh berisi angka.']],
            'phone too long' => [['no_hp' => str_repeat('1', 16)], ['no_hp' => 'No. HP maksimal 15 karakter.']],
            'short password' => [['password' => '12345', 'password_confirmation' => '12345'], ['password' => 'Password minimal 6 karakter.']],
            'password mismatch' => [['password_confirmation' => 'berbeda'], ['password' => 'Konfirmasi password tidak cocok.']],
            'missing password confirmation' => [['password_confirmation' => ''], ['password' => 'Konfirmasi password tidak cocok.']],
            'array name' => [['nama' => ['Pasien']], ['nama' => 'Nama pasien harus berupa teks.']],
        ];
    }

    public function test_duplicate_identity_displays_errors_and_preserves_escaped_input(): void
    {
        User::factory()->create(['email' => 'baru@example.com', 'no_ktp' => '0012345678901234']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->followingRedirects()->from(route('pasien.create'))->post(route('pasien.store'),
            array_replace($this->validData(), ['nama' => '<script>nama</script>', 'alamat' => '<b>alamat</b>']))
            ->assertOk()->assertSee('Email sudah terdaftar.')->assertSee('No. KTP sudah terdaftar.')
            ->assertSee('&lt;script&gt;nama&lt;/script&gt;', false)->assertDontSee('<script>nama</script>', false)
            ->assertSee('&lt;b&gt;alamat&lt;/b&gt;', false)->assertDontSee('<b>alamat</b>', false)
            ->assertDontSee('value="rahasia123"', false);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_guests_cannot_open_the_form_or_create_patients(): void
    {
        $this->get(route('pasien.create'))->assertRedirect(route('login'));
        $this->post(route('pasien.store'), $this->validData())->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_open_the_form_or_create_patients(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get(route('pasien.create'))->assertForbidden();
        $this->post(route('pasien.store'), $this->validData())->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    public static function nonAdminRoles(): array
    {
        return ['doctor' => ['dokter'], 'patient' => ['pasien']];
    }

    private function validData(): array
    {
        return [
            'nama' => 'Pasien Baru', 'alamat' => 'Semarang', 'no_ktp' => '0012345678901234',
            'no_hp' => '081234567890', 'email' => 'baru@example.com',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ];
    }
}
