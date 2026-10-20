<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function patientData(string $number): array
    {
        return [
            'nama' => 'Pasien Uji',
            'alamat' => 'Semarang',
            'no_ktp' => '001234567890'.$number,
            'no_hp' => '081234567890',
            'email' => 'pasien'.$number.'@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'admin',
        ];
    }

    public function test_registration_numbers_increment_and_reset_each_month(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 13));
        User::create([
            'nama' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        $this->post('/register', $this->patientData('1'))
            ->assertRedirect(route('login'))->assertSessionHas('success');
        $patient = User::where('email', 'pasien1@example.com')->firstOrFail();
        $this->assertSame('202610-001', $patient->no_rm);
        $this->assertSame('pasien', $patient->role);
        $this->assertSame('081234567890', $patient->no_hp);
        $this->assertTrue(Hash::check('rahasia123', $patient->password));

        $this->post('/register', $this->patientData('2'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'pasien2@example.com', 'no_rm' => '202610-002']);

        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 1));
        $this->post('/register', $this->patientData('3'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'pasien3@example.com', 'no_rm' => '202611-001']);
    }

    public function test_duplicate_identity_and_invalid_password_are_rejected(): void
    {
        $data = $this->patientData('1');
        $this->post('/register', $data)->assertSessionHasNoErrors();
        $this->post('/register', $data)->assertSessionHasErrors(['email', 'no_ktp']);

        $invalid = $this->patientData('2');
        $invalid['password_confirmation'] = 'berbeda';
        $this->post('/register', $invalid)->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => $invalid['email']]);
    }
}
