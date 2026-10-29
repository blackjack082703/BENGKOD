<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PoliRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_register_without_submitting_patient_id(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), [
                'id_poli' => $jadwal->dokter->id_poli,
                'id_jadwal' => $jadwal->id,
                'keluhan' => 'Demam sejak kemarin',
            ])->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('message', 'Berhasil Mendaftar ke Poli');

        $this->assertDatabaseCount('daftar_poli', 1);
        $this->assertDatabaseHas('daftar_poli', [
            'id_pasien' => $pasien->id,
            'id_jadwal' => $jadwal->id,
            'keluhan' => 'Demam sejak kemarin',
            'no_antrian' => 1,
        ]);
    }

    public function test_submitted_patient_id_cannot_register_another_patient(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $other = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), [
                'id_pasien' => $other->id,
                'id_poli' => $jadwal->dokter->id_poli,
                'id_jadwal' => $jadwal->id,
                'keluhan' => 'Sakit kepala',
            ])->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('message', 'Berhasil Mendaftar ke Poli');

        $this->assertDatabaseCount('daftar_poli', 1);
        $this->assertDatabaseHas('daftar_poli', [
            'id_pasien' => $pasien->id,
            'id_jadwal' => $jadwal->id,
            'keluhan' => 'Sakit kepala',
        ]);
        $this->assertDatabaseMissing('daftar_poli', ['id_pasien' => $other->id]);
    }

    public function test_guest_cannot_submit_a_patient_registration(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();

        $this->post(route('pasien.daftar.submit'), [
            'id_pasien' => $pasien->id,
            'id_poli' => $jadwal->dokter->id_poli,
            'id_jadwal' => $jadwal->id,
            'keluhan' => 'Demam',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('daftar_poli', 0);
    }

    #[DataProvider('nonPatientRoles')]
    public function test_non_patient_cannot_submit_a_patient_registration(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();

        $this->actingAs($user)->post(route('pasien.daftar.submit'), [
            'id_pasien' => $pasien->id,
            'id_poli' => $jadwal->dokter->id_poli,
            'id_jadwal' => $jadwal->id,
            'keluhan' => 'Demam',
        ])->assertForbidden();

        $this->assertDatabaseCount('daftar_poli', 0);
    }

    public function test_patient_cannot_register_again_for_the_same_schedule_before_examination(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();
        $daftar = $this->createRegistration($pasien, $jadwal);

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), $this->registrationData($jadwal))
            ->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasErrors([
                'id_jadwal' => 'Anda masih memiliki pendaftaran aktif. Selesaikan pemeriksaan terlebih dahulu sebelum mendaftar kembali.',
            ])
            ->assertSessionMissing('message');

        $this->assertDatabaseCount('daftar_poli', 1);
        $this->assertModelExists($daftar);
    }

    public function test_patient_cannot_register_for_another_poli_before_examination(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();
        $otherJadwal = $this->createSchedule();
        $daftar = $this->createRegistration($pasien, $jadwal);

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), $this->registrationData($otherJadwal))
            ->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasErrors([
                'id_jadwal' => 'Anda masih memiliki pendaftaran aktif. Selesaikan pemeriksaan terlebih dahulu sebelum mendaftar kembali.',
            ]);

        $this->assertDatabaseCount('daftar_poli', 1);
        $this->assertModelExists($daftar);
        $this->assertDatabaseMissing('daftar_poli', ['id_jadwal' => $otherJadwal->id]);
    }

    public function test_patient_can_register_again_after_examination_is_recorded(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();
        $daftar = $this->createRegistration($pasien, $jadwal);
        $daftar->periksas()->create([
            'tgl_periksa' => '2026-10-29 08:30:00',
            'catatan' => 'Pemeriksaan selesai',
            'biaya_periksa' => 50000,
        ]);

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), $this->registrationData($jadwal))
            ->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('message', 'Berhasil Mendaftar ke Poli');

        $this->assertDatabaseCount('daftar_poli', 2);
        $this->assertDatabaseHas('daftar_poli', [
            'id_pasien' => $pasien->id,
            'id_jadwal' => $jadwal->id,
            'keluhan' => 'Sakit kepala',
            'no_antrian' => 2,
        ]);
        $this->assertDatabaseCount('periksa', 1);
    }

    public function test_another_patients_active_registration_does_not_block_registration(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $other = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();
        $this->createRegistration($other, $jadwal);

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), $this->registrationData($jadwal))
            ->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('message', 'Berhasil Mendaftar ke Poli');

        $this->assertDatabaseCount('daftar_poli', 2);
        $this->assertDatabaseHas('daftar_poli', [
            'id_pasien' => $pasien->id,
            'id_jadwal' => $jadwal->id,
            'no_antrian' => 2,
        ]);
    }

    public function test_completed_registration_does_not_hide_another_unexamined_registration(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();
        $otherJadwal = $this->createSchedule();
        $this->createRegistration($pasien, $jadwal);
        $completed = $this->createRegistration($pasien, $otherJadwal);
        $completed->periksas()->create([
            'tgl_periksa' => '2026-10-29 09:00:00',
            'catatan' => 'Pemeriksaan selesai',
            'biaya_periksa' => 50000,
        ]);

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), $this->registrationData($otherJadwal))
            ->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasErrors([
                'id_jadwal' => 'Anda masih memiliki pendaftaran aktif. Selesaikan pemeriksaan terlebih dahulu sebelum mendaftar kembali.',
            ]);

        $this->assertDatabaseCount('daftar_poli', 2);
        $this->assertDatabaseCount('periksa', 1);
    }

    public function test_registration_form_only_lists_active_schedules_with_a_poli(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $active = $this->createSchedule();
        $inactive = $this->createSchedule();
        $inactive->update(['status' => false]);
        $unassigned = JadwalPeriksa::factory()->create();

        $this->actingAs($pasien)->get(route('pasien.daftar'))
            ->assertOk()
            ->assertSee('Pendaftaran Poli')
            ->assertSee('value="'.$active->id.'" data-poli="'.$active->dokter->id_poli.'"', false)
            ->assertDontSee('value="'.$inactive->id.'" data-poli=', false)
            ->assertDontSee('value="'.$unassigned->id.'" data-poli=', false)
            ->assertViewHas('jadwals', fn ($jadwals): bool => $jadwals->modelKeys() === [$active->id]);
    }

    public function test_registration_form_handles_no_available_schedules(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);

        $this->actingAs($pasien)->get(route('pasien.daftar'))
            ->assertOk()
            ->assertSee('-- Pilih Jadwal --')
            ->assertViewHas('jadwals', fn ($jadwals): bool => $jadwals->isEmpty());
    }

    public function test_patient_cannot_register_for_an_inactive_schedule(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();
        $jadwal->update(['status' => false]);

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), $this->registrationData($jadwal))
            ->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasErrors([
                'id_jadwal' => 'Jadwal yang dipilih tidak aktif atau tidak sesuai dengan poli pilihan.',
            ])
            ->assertSessionMissing('message');

        $this->assertDatabaseCount('daftar_poli', 0);
    }

    public function test_patient_cannot_register_for_a_schedule_from_another_poli(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $jadwal = $this->createSchedule();
        $otherPoli = Poli::create(['nama_poli' => 'Poli Gigi', 'keterangan' => 'Pemeriksaan gigi']);
        $data = $this->registrationData($jadwal);
        $data['id_poli'] = $otherPoli->id;

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), $data)
            ->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasErrors([
                'id_jadwal' => 'Jadwal yang dipilih tidak aktif atau tidak sesuai dengan poli pilihan.',
            ]);

        $this->assertDatabaseCount('daftar_poli', 0);
    }

    public function test_patient_cannot_register_for_a_schedule_without_an_assigned_poli(): void
    {
        $pasien = User::factory()->create(['role' => 'pasien']);
        $poli = Poli::create(['nama_poli' => 'Poli Umum', 'keterangan' => 'Pemeriksaan umum']);
        $jadwal = JadwalPeriksa::factory()->create();

        $this->actingAs($pasien)->from(route('pasien.daftar'))
            ->post(route('pasien.daftar.submit'), [
                'id_poli' => $poli->id,
                'id_jadwal' => $jadwal->id,
                'keluhan' => 'Demam',
            ])->assertRedirect(route('pasien.daftar'))
            ->assertSessionHasErrors([
                'id_jadwal' => 'Jadwal yang dipilih tidak aktif atau tidak sesuai dengan poli pilihan.',
            ]);

        $this->assertDatabaseCount('daftar_poli', 0);
    }

    public function test_empty_complaint_returns_validation_error_without_database_failure(): void
    {
        $pasien = User::factory()->create();
        $jadwal = $this->createSchedule();
        $data = $this->registrationData($jadwal);
        $data['keluhan'] = '';

        $this->actingAs($pasien)->from(route('pasien.daftar'))->post(route('pasien.daftar.submit'), $data)
            ->assertRedirect(route('pasien.daftar'))->assertSessionHasErrors(['keluhan' => 'Keluhan wajib diisi.']);

        $this->assertDatabaseCount('daftar_poli', 0);
    }

    public function test_queue_numbers_use_highest_number_even_if_a_previous_number_is_missing(): void
    {
        $pasien = User::factory()->create();
        $jadwal = $this->createSchedule();
        $this->createRegistration(User::factory()->create(), $jadwal);
        $this->createRegistration(User::factory()->create(), $jadwal, 3);

        $this->actingAs($pasien)->post(route('pasien.daftar.submit'), $this->registrationData($jadwal))
            ->assertSessionHasNoErrors()->assertSessionHas('message', 'Berhasil Mendaftar ke Poli');

        $this->assertDatabaseHas('daftar_poli', ['id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'no_antrian' => 4]);
    }

    public function test_full_queue_returns_validation_error_without_overflow(): void
    {
        $pasien = User::factory()->create();
        $jadwal = $this->createSchedule();
        $registration = $this->createRegistration(User::factory()->create(), $jadwal);
        $registration->update(['no_antrian' => 2147483647]);

        $this->actingAs($pasien)->post(route('pasien.daftar.submit'), $this->registrationData($jadwal))
            ->assertSessionHasErrors(['id_jadwal' => 'Nomor antrean pada jadwal ini sudah mencapai batas.']);

        $this->assertDatabaseCount('daftar_poli', 1);
    }

    public static function nonPatientRoles(): array
    {
        return [
            'admin' => ['admin'],
            'dokter' => ['dokter'],
        ];
    }

    private function createRegistration(User $pasien, JadwalPeriksa $jadwal, int $nomorAntrian = 1): DaftarPoli
    {
        return DaftarPoli::create([
            'id_pasien' => $pasien->id,
            'id_jadwal' => $jadwal->id,
            'keluhan' => 'Demam',
            'no_antrian' => $nomorAntrian,
        ]);
    }

    /**
     * @return array{id_poli: int, id_jadwal: int, keluhan: string}
     */
    private function registrationData(JadwalPeriksa $jadwal): array
    {
        return [
            'id_poli' => $jadwal->dokter->id_poli,
            'id_jadwal' => $jadwal->id,
            'keluhan' => 'Sakit kepala',
        ];
    }

    private function createSchedule(): JadwalPeriksa
    {
        $poli = Poli::create(['nama_poli' => 'Poli Umum', 'keterangan' => 'Pemeriksaan umum']);
        $dokter = User::factory()->for($poli, 'poli')->create(['role' => 'dokter']);

        return JadwalPeriksa::factory()->for($dokter, 'dokter')->create();
    }
}
