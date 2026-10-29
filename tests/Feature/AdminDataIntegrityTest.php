<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('wrongRoleTargets')]
    public function test_account_endpoints_reject_targets_of_another_role(string $resource, string $role, string $method, string $suffix): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => $role]);

        $this->actingAs($admin)->call($method, '/admin/'.$resource.'/'.$target->id.$suffix, [])
            ->assertNotFound();

        $this->assertModelExists($target);
        $this->assertDatabaseCount('users', 2);
    }

    public static function wrongRoleTargets(): array
    {
        $cases = [];
        foreach (['pasien' => ['admin', 'dokter'], 'dokter' => ['admin', 'pasien']] as $resource => $roles) {
            foreach ($roles as $role) {
                foreach (['edit' => ['GET', '/edit'], 'update' => ['PUT', ''], 'delete' => ['DELETE', '']] as $action => [$method, $suffix]) {
                    $cases[$resource.' '.$action.' '.$role] = [$resource, $role, $method, $suffix];
                }
            }
        }

        return $cases;
    }

    public function test_patient_can_be_updated_without_changing_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['password' => 'rahasia123']);
        $oldHash = $patient->password;

        $this->actingAs($admin)->put(route('pasien.update', $patient), [
            'nama' => 'Nama Baru', 'alamat' => 'Semarang', 'no_ktp' => '1234567890123456',
            'no_hp' => '081234567890', 'email' => $patient->email, 'password' => '',
        ])->assertRedirect(route('pasien.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $patient->id, 'nama' => 'Nama Baru']);
        $this->assertSame($oldHash, $patient->fresh()->password);
    }

    public function test_valid_doctor_can_be_created_and_updated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $data = $this->doctorData();

        $this->actingAs($admin)->post(route('dokter.store'), $data)
            ->assertRedirect(route('dokter.index'))->assertSessionHasNoErrors();

        $doctor = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame('dokter', $doctor->role);
        $this->assertTrue(Hash::check('rahasia123', $doctor->password));
        $data['nama'] = 'Dokter Diperbarui';
        $data['password'] = '';
        $this->put(route('dokter.update', $doctor), $data)
            ->assertRedirect(route('dokter.index'))->assertSessionHasNoErrors();
        $this->assertSame('Dokter Diperbarui', $doctor->fresh()->nama);
        $this->assertTrue(Hash::check('rahasia123', $doctor->fresh()->password));
    }

    #[DataProvider('writeMethods')]
    public function test_invalid_doctor_email_is_rejected(string $method): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $doctor = User::factory()->create(['role' => 'dokter']);
        $data = $this->doctorData();
        $data['email'] = 'bukan-email';
        $url = $method === 'POST' ? route('dokter.store') : route('dokter.update', $doctor);

        $this->actingAs($admin)->call($method, $url, $data)
            ->assertSessionHasErrors(['email' => 'Format email tidak valid.']);

        $this->assertDatabaseMissing('users', ['email' => 'bukan-email']);
        $this->assertDatabaseCount('users', 2);
    }

    public static function writeMethods(): array
    {
        return ['create' => ['POST'], 'update' => ['PUT']];
    }

    public function test_deleting_used_poli_doctor_or_patient_preserves_medical_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $poli = Poli::create(['nama_poli' => 'Umum', 'keterangan' => 'Umum']);
        $doctor = User::factory()->for($poli, 'poli')->create(['role' => 'dokter']);
        $patient = User::factory()->create();
        $schedule = JadwalPeriksa::factory()->for($doctor, 'dokter')->create();
        $registration = DaftarPoli::create([
            'id_pasien' => $patient->id, 'id_jadwal' => $schedule->id, 'keluhan' => 'Demam', 'no_antrian' => 1,
        ]);
        $periksa = $registration->periksas()->create([
            'tgl_periksa' => '2026-10-29 08:00:00', 'catatan' => 'Riwayat penting', 'biaya_periksa' => 150000,
        ]);

        $this->actingAs($admin)->delete(route('polis.destroy', $poli))->assertRedirect(route('polis.index'))
            ->assertSessionHas('error', 'Poli tidak dapat dihapus karena masih digunakan oleh dokter atau pengguna lain.');
        $this->delete(route('dokter.destroy', $doctor))->assertRedirect(route('dokter.index'))
            ->assertSessionHas('error', 'Dokter tidak dapat dihapus karena masih memiliki jadwal atau pendaftaran pasien.');
        $this->delete(route('pasien.destroy', $patient))->assertRedirect(route('pasien.index'))
            ->assertSessionHas('error', 'Pasien tidak dapat dihapus karena sudah memiliki pendaftaran atau riwayat pemeriksaan.');

        foreach ([$poli, $doctor, $patient, $schedule, $registration, $periksa] as $model) {
            $this->assertModelExists($model);
        }
    }

    public function test_unused_poli_doctor_and_patient_can_still_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $poli = Poli::create(['nama_poli' => 'Umum', 'keterangan' => 'Umum']);
        $doctor = User::factory()->for($poli, 'poli')->create(['role' => 'dokter']);
        $patient = User::factory()->create();

        $this->actingAs($admin)->delete(route('dokter.destroy', $doctor))->assertRedirect(route('dokter.index'));
        $this->delete(route('pasien.destroy', $patient))->assertRedirect(route('pasien.index'));
        $this->delete(route('polis.destroy', $poli))->assertRedirect(route('polis.index'));

        $this->assertModelMissing($doctor);
        $this->assertModelMissing($patient);
        $this->assertModelMissing($poli);
    }

    #[DataProvider('removedShowRoutes')]
    public function test_unsupported_show_requests_return_method_not_allowed(string $role, string $url): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertMethodNotAllowed();
    }

    public static function removedShowRoutes(): array
    {
        return [
            'poli' => ['admin', '/admin/polis/1'], 'doctor' => ['admin', '/admin/dokter/1'],
            'patient' => ['admin', '/admin/pasien/1'], 'schedule' => ['dokter', '/dokter/jadwal-periksa/1'],
        ];
    }

    private function doctorData(): array
    {
        $poli = Poli::create(['nama_poli' => 'Umum', 'keterangan' => 'Umum']);

        return [
            'nama' => 'Dokter Baru', 'alamat' => 'Semarang', 'no_ktp' => '1234567890123456',
            'no_hp' => '081234567890', 'email' => 'dokter-baru@example.com',
            'id_poli' => $poli->id, 'password' => 'rahasia123',
        ];
    }
}
