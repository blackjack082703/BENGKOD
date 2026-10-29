<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicalSchemaIntegrityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_duplicate_queue_number_is_rejected_by_the_database(): void
    {
        $registration = $this->registration();

        $this->expectException(QueryException::class);

        DaftarPoli::create([
            'id_pasien' => User::factory()->create()->id, 'id_jadwal' => $registration->id_jadwal,
            'keluhan' => 'Batuk', 'no_antrian' => 1,
        ]);
    }

    public function test_duplicate_examination_is_rejected_by_the_database(): void
    {
        $registration = $this->registration();
        $data = ['tgl_periksa' => '2026-10-29 08:00:00', 'catatan' => 'Selesai', 'biaya_periksa' => 150000];
        $registration->periksas()->create($data);

        $this->expectException(QueryException::class);

        $registration->periksas()->create($data);
    }

    public function test_deleting_a_used_poli_is_rejected_by_the_database(): void
    {
        $registration = $this->registration();
        $poli = $registration->jadwalPeriksa->dokter->poli;

        $this->expectException(QueryException::class);

        $poli->delete();
    }

    public function test_deleting_a_patient_with_registration_is_rejected_by_the_database(): void
    {
        $registration = $this->registration();

        $this->expectException(QueryException::class);

        $registration->pasien->delete();
    }

    public function test_fresh_install_can_use_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $this->get(route('login'))->assertOk();

        $this->assertDatabaseCount('sessions', 1);
    }

    public function test_compatibility_migration_preserves_preexisting_sessions(): void
    {
        DB::table('sessions')->insert(['id' => 'existing-session', 'payload' => 'existing-payload', 'last_activity' => 1]);
        $migration = require database_path('migrations/2026_10_29_180111_create_sessions_table_if_missing.php');

        $migration->up();
        $migration->down();

        $this->assertDatabaseHas('sessions', ['id' => 'existing-session', 'payload' => 'existing-payload']);
    }

    public function test_integrity_migration_can_be_reversed_and_reapplied_without_losing_records(): void
    {
        $registration = $this->registration();
        $migration = require database_path('migrations/2026_10_29_180110_enforce_medical_record_integrity.php');

        $migration->down();
        $this->assertFalse(Schema::hasIndex('periksa', 'periksa_pendaftaran_unique'));
        $migration->up();

        $this->assertModelExists($registration);
        $this->assertTrue(Schema::hasIndex('periksa', 'periksa_pendaftaran_unique'));
        $this->assertTrue(Schema::hasIndex('daftar_poli', 'daftar_poli_jadwal_antrian_unique'));
    }

    private function registration(): DaftarPoli
    {
        $poli = Poli::create(['nama_poli' => 'Umum', 'keterangan' => 'Umum']);
        $doctor = User::factory()->for($poli, 'poli')->create(['role' => 'dokter']);
        $schedule = JadwalPeriksa::factory()->for($doctor, 'dokter')->create();

        return DaftarPoli::create([
            'id_pasien' => User::factory()->create()->id, 'id_jadwal' => $schedule->id,
            'keluhan' => 'Demam', 'no_antrian' => 1,
        ]);
    }
}
