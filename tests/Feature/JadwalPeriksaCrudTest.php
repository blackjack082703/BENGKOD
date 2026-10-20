<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JadwalPeriksaCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_sees_empty_list_and_create_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dokter']));

        $this->get(route('jadwal-periksa.index'))->assertOk()->assertSee('Belum ada jadwal periksa');
        $this->get(route('jadwal-periksa.create'))->assertOk()->assertSee('Tambah Jadwal Periksa')
            ->assertSee('Senin')->assertSee('Minggu')->assertSee('name="status"', false);
    }

    public function test_doctor_only_sees_own_schedules_including_status_and_times(): void
    {
        $dokter = User::factory()->create(['role' => 'dokter']);
        JadwalPeriksa::factory()->for($dokter, 'dokter')->create(['hari' => 'Senin', 'status' => true]);
        JadwalPeriksa::factory()->for($dokter, 'dokter')->create(['hari' => 'Rabu', 'status' => false]);
        JadwalPeriksa::factory()->create(['hari' => 'Minggu', 'jam_mulai' => '21:00', 'jam_selesai' => '22:00']);
        $this->actingAs($dokter);

        $this->get(route('jadwal-periksa.index'))->assertOk()
            ->assertSee('Senin')->assertSee('Rabu')->assertSee('08:00')->assertSee('12:00')
            ->assertSee('Aktif')->assertSee('Tidak Aktif')
            ->assertDontSee('Minggu')->assertDontSee('21:00');
    }

    public function test_store_uses_authenticated_doctor_and_ignores_submitted_owner(): void
    {
        $dokter = User::factory()->create(['role' => 'dokter']);
        $other = User::factory()->create(['role' => 'dokter']);
        $this->actingAs($dokter);

        $this->post(route('jadwal-periksa.store'), $this->validData() + ['id_dokter' => $other->id])
            ->assertRedirect(route('jadwal-periksa.index'))
            ->assertSessionHas('success', 'Jadwal periksa berhasil ditambahkan.');

        $this->assertDatabaseCount('jadwal_periksa', 1);
        $this->assertDatabaseHas('jadwal_periksa', ['id_dokter' => $dokter->id] + $this->validData());
        $this->get(route('jadwal-periksa.index'))->assertOk()->assertSee('Jadwal periksa berhasil ditambahkan.');
    }

    public function test_status_defaults_to_active_if_not_submitted(): void
    {
        $dokter = User::factory()->create(['role' => 'dokter']);
        $this->actingAs($dokter);
        $data = $this->validData();
        unset($data['status']);

        $this->post(route('jadwal-periksa.store'), $data)
            ->assertRedirect(route('jadwal-periksa.index'))->assertSessionHasNoErrors();

        $jadwal = $dokter->jadwalPeriksas()->firstOrFail();
        $this->assertTrue($jadwal->status);
    }

    public function test_edit_form_normalizes_database_times_and_can_update_and_deactivate_schedule(): void
    {
        $dokter = User::factory()->create(['role' => 'dokter']);
        $other = User::factory()->create(['role' => 'dokter']);
        $jadwal = JadwalPeriksa::factory()->for($dokter, 'dokter')->create([
            'jam_mulai' => '08:00:00', 'jam_selesai' => '12:00:00',
        ]);
        $this->actingAs($dokter);

        $this->get(route('jadwal-periksa.edit', $jadwal))->assertOk()
            ->assertSee('value="08:00"', false)->assertSee('value="12:00"', false);
        $this->put(route('jadwal-periksa.update', $jadwal), [
            'hari' => 'Jumat', 'jam_mulai' => '13:00', 'jam_selesai' => '16:00',
            'status' => '0', 'id_dokter' => $other->id,
        ])->assertRedirect(route('jadwal-periksa.index'))
            ->assertSessionHas('success', 'Jadwal periksa berhasil diperbarui.');

        $this->assertDatabaseHas('jadwal_periksa', [
            'id' => $jadwal->id, 'id_dokter' => $dokter->id, 'hari' => 'Jumat',
            'jam_mulai' => '13:00', 'jam_selesai' => '16:00', 'status' => false,
        ]);
        $this->get(route('jadwal-periksa.edit', $jadwal))->assertOk()
            ->assertSee('value="0" selected', false);
    }

    public function test_doctor_can_delete_own_unused_schedule(): void
    {
        $jadwal = JadwalPeriksa::factory()->create();
        $this->actingAs($jadwal->dokter);

        $this->delete(route('jadwal-periksa.destroy', $jadwal))
            ->assertRedirect(route('jadwal-periksa.index'))
            ->assertSessionHas('success', 'Jadwal periksa berhasil dihapus.');

        $this->assertModelMissing($jadwal);
    }

    public function test_schedule_with_patient_registration_cannot_be_deleted(): void
    {
        $jadwal = JadwalPeriksa::factory()->create();
        $daftar = DaftarPoli::create([
            'id_pasien' => User::factory()->create()->id,
            'id_jadwal' => $jadwal->id, 'keluhan' => 'Demam', 'no_antrian' => 1,
        ]);
        $this->actingAs($jadwal->dokter);

        $this->delete(route('jadwal-periksa.destroy', $jadwal))
            ->assertRedirect(route('jadwal-periksa.index'))
            ->assertSessionHas('error', 'Jadwal tidak dapat dihapus karena sudah memiliki pendaftaran pasien. Nonaktifkan jadwal jika tidak digunakan.');

        $this->assertModelExists($jadwal);
        $this->assertModelExists($daftar);
    }

    #[DataProvider('recordEndpoints')]
    public function test_doctor_cannot_access_or_change_another_doctors_schedule(string $method, string $suffix): void
    {
        $jadwal = JadwalPeriksa::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'dokter']));

        $this->call($method, '/dokter/jadwal-periksa/'.$jadwal->id.$suffix, [
            'hari' => 'Minggu', 'jam_mulai' => '20:00', 'jam_selesai' => '21:00', 'status' => false,
        ])->assertNotFound();

        $this->assertDatabaseHas('jadwal_periksa', [
            'id' => $jadwal->id, 'id_dokter' => $jadwal->id_dokter, 'hari' => 'Senin', 'status' => true,
        ]);
    }

    #[DataProvider('recordEndpoints')]
    public function test_missing_schedule_returns_not_found(string $method, string $suffix): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dokter']));

        $this->call($method, '/dokter/jadwal-periksa/999999'.$suffix, $this->validData())->assertNotFound();

        $this->assertDatabaseCount('jadwal_periksa', 0);
    }

    public static function recordEndpoints(): array
    {
        return ['edit' => ['GET', '/edit'], 'update' => ['PUT', ''], 'delete' => ['DELETE', '']];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_schedule_is_rejected_without_saving(string $method, array $overrides, array $errors): void
    {
        $jadwal = JadwalPeriksa::factory()->create();
        $this->actingAs($jadwal->dokter);
        $uri = $method === 'POST' ? route('jadwal-periksa.store') : route('jadwal-periksa.update', $jadwal);

        $this->from(route('jadwal-periksa.create'))->call($method, $uri, array_replace($this->validData(), $overrides))
            ->assertRedirect(route('jadwal-periksa.create'))->assertSessionHasErrors($errors);

        $this->assertDatabaseCount('jadwal_periksa', 1);
        $this->assertDatabaseHas('jadwal_periksa', ['id' => $jadwal->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00']);
    }

    public static function invalidInputs(): array
    {
        $cases = [
            'required fields' => [
                ['hari' => '', 'jam_mulai' => '', 'jam_selesai' => ''],
                ['hari' => 'Hari wajib dipilih.', 'jam_mulai' => 'Jam mulai wajib diisi.', 'jam_selesai' => 'Jam selesai wajib diisi.'],
            ],
            'unknown day' => [['hari' => 'Monday'], ['hari' => 'Hari yang dipilih tidak valid.']],
            'invalid start' => [['jam_mulai' => '25:00'], ['jam_mulai' => 'Jam mulai harus menggunakan format HH:MM.']],
            'invalid end' => [['jam_selesai' => 'siang'], ['jam_selesai' => 'Jam selesai harus menggunakan format HH:MM.']],
            'same time' => [['jam_selesai' => '08:00'], ['jam_selesai' => 'Jam selesai harus setelah jam mulai.']],
            'earlier end' => [['jam_selesai' => '07:59'], ['jam_selesai' => 'Jam selesai harus setelah jam mulai.']],
            'invalid status' => [['status' => 'aktif'], ['status' => 'Status harus Aktif atau Tidak Aktif.']],
        ];
        $data = [];
        foreach (['POST', 'PUT'] as $method) {
            foreach ($cases as $name => [$overrides, $errors]) {
                $data[$method.' '.$name] = [$method, $overrides, $errors];
            }
        }

        return $data;
    }

    public function test_validation_preserves_day_and_times_and_shows_error_in_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dokter']));

        $this->followingRedirects()->from(route('jadwal-periksa.create'))
            ->post(route('jadwal-periksa.store'), [
                'hari' => 'Selasa', 'jam_mulai' => '09:00', 'jam_selesai' => '08:00', 'status' => '0',
            ])->assertOk()->assertSee('Jam selesai harus setelah jam mulai.')
            ->assertSee('value="Selasa" selected', false)->assertSee('value="09:00"', false)
            ->assertSee('value="08:00"', false)->assertSee('value="0" selected', false);

        $this->assertDatabaseCount('jadwal_periksa', 0);
    }

    #[DataProvider('protectedEndpoints')]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $jadwal = JadwalPeriksa::factory()->create();
        $uri = str_replace('{id}', (string) $jadwal->id, $uri);

        $this->call($method, $uri, $this->validData())->assertRedirect(route('login'));

        $this->assertDatabaseCount('jadwal_periksa', 1);
        $this->assertModelExists($jadwal);
    }

    #[DataProvider('forbiddenEndpoints')]
    public function test_non_doctor_roles_cannot_access_or_change_schedules(string $role, string $method, string $uri): void
    {
        $jadwal = JadwalPeriksa::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        $uri = str_replace('{id}', (string) $jadwal->id, $uri);

        $this->call($method, $uri, ['hari' => 'Minggu'] + $this->validData())->assertForbidden();

        $this->assertDatabaseCount('jadwal_periksa', 1);
        $this->assertDatabaseHas('jadwal_periksa', ['id' => $jadwal->id, 'hari' => 'Senin']);
    }

    public static function protectedEndpoints(): array
    {
        return [
            'list' => ['GET', '/dokter/jadwal-periksa'],
            'create form' => ['GET', '/dokter/jadwal-periksa/create'],
            'store' => ['POST', '/dokter/jadwal-periksa'],
            'edit form' => ['GET', '/dokter/jadwal-periksa/{id}/edit'],
            'update' => ['PUT', '/dokter/jadwal-periksa/{id}'],
            'delete' => ['DELETE', '/dokter/jadwal-periksa/{id}'],
        ];
    }

    public static function forbiddenEndpoints(): array
    {
        $data = [];
        foreach (['admin', 'pasien'] as $role) {
            foreach (self::protectedEndpoints() as $name => [$method, $uri]) {
                $data[$role.' '.$name] = [$role, $method, $uri];
            }
        }

        return $data;
    }

    private function validData(): array
    {
        return ['hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'status' => true];
    }
}
