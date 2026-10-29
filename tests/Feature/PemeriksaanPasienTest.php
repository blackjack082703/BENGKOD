<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\DetailPeriksa;
use App\Models\JadwalPeriksa;
use App\Models\Obat;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PemeriksaanPasienTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_sees_only_own_patients_and_can_open_form(): void
    {
        [$doctor, $registration] = $this->registration();
        [, $other] = $this->registration();

        $this->actingAs($doctor)->get(route('periksa-pasien.index'))->assertOk()
            ->assertSee($registration->pasien->nama)->assertDontSee($other->pasien->nama);
        $this->get(route('periksa-pasien.create', $registration))->assertOk()
            ->assertSee('150.000')->assertDontSee('name="biaya_periksa"', false);
    }

    public function test_total_uses_database_prices_and_examination_unlocks_registration(): void
    {
        [$doctor, $registration] = $this->registration();
        $obats = Obat::factory()->count(2)->sequence(['harga' => 10000], ['harga' => 25000])->create();
        $data = array_replace($this->data($registration), [
            'obat_json' => $obats->pluck('id')->toJson(), 'biaya_periksa' => -150000, 'obat_ids' => [999999],
        ]);

        $this->actingAs($doctor)->post(route('periksa-pasien.store'), $data)
            ->assertRedirect(route('periksa-pasien.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Data periksa berhasil disimpan.');

        $this->assertDatabaseHas('periksa', ['id_daftar_poli' => $registration->id, 'biaya_periksa' => 185000]);
        $this->assertDatabaseCount('detail_periksa', 2);
        $this->actingAs($registration->pasien)->post(route('pasien.daftar.submit'), [
            'id_poli' => $doctor->id_poli, 'id_jadwal' => $registration->id_jadwal, 'keluhan' => 'Kontrol',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('daftar_poli', 2);
    }

    public function test_examination_without_medicine_costs_only_the_doctor_fee(): void
    {
        [$doctor, $registration] = $this->registration();

        $this->actingAs($doctor)->post(route('periksa-pasien.store'), $this->data($registration))
            ->assertRedirect(route('periksa-pasien.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('periksa', ['id_daftar_poli' => $registration->id, 'biaya_periksa' => 150000]);
        $this->assertDatabaseCount('detail_periksa', 0);
    }

    public function test_doctor_cannot_open_or_examine_another_doctors_registration(): void
    {
        [, $registration] = $this->registration();
        $doctor = User::factory()->create(['role' => 'dokter']);

        $this->actingAs($doctor)->get(route('periksa-pasien.create', $registration))->assertNotFound();
        $this->post(route('periksa-pasien.store'), $this->data($registration))->assertNotFound();

        $this->assertDatabaseCount('periksa', 0);
        $this->assertDatabaseCount('detail_periksa', 0);
    }

    public function test_missing_registration_form_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dokter']))
            ->get(route('periksa-pasien.create', 999999))->assertNotFound();
    }

    public function test_completed_registration_cannot_be_examined_twice(): void
    {
        [$doctor, $registration] = $this->registration();
        $periksa = $registration->periksas()->create([
            'tgl_periksa' => '2026-10-29 08:00:00', 'catatan' => 'Selesai', 'biaya_periksa' => 150000,
        ]);

        $this->actingAs($doctor)->post(route('periksa-pasien.store'), $this->data($registration))
            ->assertSessionHasErrors(['id_daftar_poli' => 'Pasien pada pendaftaran ini sudah diperiksa.']);
        $this->get(route('periksa-pasien.create', $registration))->assertRedirect(route('periksa-pasien.index'));

        $this->assertDatabaseCount('periksa', 1);
        $this->assertModelExists($periksa);
    }

    #[DataProvider('invalidData')]
    public function test_invalid_examination_data_is_rejected_without_saving(array $overrides, array $errors): void
    {
        [$doctor, $registration] = $this->registration();

        $this->actingAs($doctor)->from(route('periksa-pasien.create', $registration))
            ->post(route('periksa-pasien.store'), array_replace($this->data($registration), $overrides))
            ->assertRedirect(route('periksa-pasien.create', $registration))->assertSessionHasErrors($errors);

        $this->assertDatabaseCount('periksa', 0);
        $this->assertDatabaseCount('detail_periksa', 0);
    }

    public static function invalidData(): array
    {
        return [
            'missing registration' => [['id_daftar_poli' => null], ['id_daftar_poli' => 'Pendaftaran pasien wajib dipilih.']],
            'unknown registration' => [['id_daftar_poli' => 999999], ['id_daftar_poli' => 'Pendaftaran pasien tidak ditemukan.']],
            'missing medicines' => [['obat_json' => null], ['obat_json' => 'Daftar obat wajib dikirim.']],
            'invalid json' => [['obat_json' => 'bukan-json'], ['obat_json' => 'Daftar obat harus berupa JSON yang valid.']],
            'array input' => [['obat_json' => ['1']], ['obat_json' => 'Daftar obat harus berupa JSON yang valid.']],
            'object input' => [['obat_json' => '{}'], ['obat_ids' => 'Daftar obat harus berupa daftar ID obat.']],
            'scalar json' => [['obat_json' => '"obat"'], ['obat_ids' => 'Daftar obat harus berupa daftar ID obat.']],
            'unknown medicine' => [['obat_json' => '[999999]'], ['obat_ids.0' => 'Obat yang dipilih tidak tersedia.']],
            'invalid medicine id' => [['obat_json' => '["obat"]'], ['obat_ids.0' => 'ID obat tidak valid.']],
            'duplicate medicine' => [['obat_json' => '[999999,999999]'], ['obat_ids.0' => 'Obat yang sama tidak boleh dipilih lebih dari sekali.']],
            'invalid notes' => [['catatan' => ['catatan']], ['catatan' => 'Catatan harus berupa teks.']],
        ];
    }

    public function test_overflowing_total_is_rejected(): void
    {
        [$doctor, $registration] = $this->registration();
        $obat = Obat::factory()->create(['harga' => 2147483647]);

        $this->actingAs($doctor)->post(route('periksa-pasien.store'), array_replace($this->data($registration), [
            'obat_json' => json_encode([$obat->id]),
        ]))->assertSessionHasErrors(['obat_json' => 'Total biaya melebihi batas yang dapat disimpan.']);

        $this->assertDatabaseCount('periksa', 0);
    }

    public function test_failed_medicine_save_rolls_back_the_examination(): void
    {
        [$doctor, $registration] = $this->registration();
        $obat = Obat::factory()->create();
        $event = 'eloquent.creating: '.DetailPeriksa::class;
        Event::listen($event, function (): void {
            throw new RuntimeException('Simulasi kegagalan obat');
        });
        Exceptions::fake();

        try {
            $this->actingAs($doctor)->post(route('periksa-pasien.store'), array_replace($this->data($registration), [
                'obat_json' => json_encode([$obat->id]),
            ]))->assertInternalServerError();
        } finally {
            Event::forget($event);
        }

        Exceptions::assertReported(RuntimeException::class);
        $this->assertDatabaseCount('periksa', 0);
        $this->assertDatabaseCount('detail_periksa', 0);
    }

    public function test_history_and_details_are_scoped_to_the_doctor(): void
    {
        [$doctor, $registration] = $this->registration();
        [, $otherRegistration] = $this->registration();
        $periksa = $registration->periksas()->create([
            'tgl_periksa' => '2026-10-29 08:00:00', 'catatan' => 'Catatan sendiri', 'biaya_periksa' => 150000,
        ]);
        $other = $otherRegistration->periksas()->create([
            'tgl_periksa' => '2026-10-29 09:00:00', 'catatan' => 'Catatan dokter lain', 'biaya_periksa' => 150000,
        ]);

        $this->actingAs($doctor)->get(route('riwayat-pasien.index'))->assertOk()
            ->assertSee($registration->pasien->nama)->assertDontSee($otherRegistration->pasien->nama);
        $this->get(route('riwayat-pasien.show', $periksa))->assertOk()->assertSee('Catatan sendiri');
        $this->get(route('riwayat-pasien.show', $other))->assertNotFound();
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_cannot_access_examinations_or_history(string $role): void
    {
        [, $registration] = $this->registration();
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('periksa-pasien.index'))->assertForbidden();
        $this->get(route('periksa-pasien.create', $registration))->assertForbidden();
        $this->post(route('periksa-pasien.store'), $this->data($registration))->assertForbidden();
        $this->get(route('riwayat-pasien.index'))->assertForbidden();

        $this->assertDatabaseCount('periksa', 0);
    }

    public static function forbiddenRoles(): array
    {
        return ['admin' => ['admin'], 'pasien' => ['pasien']];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('periksa-pasien.index'))->assertRedirect(route('login'));
        $this->post(route('periksa-pasien.store'), [])->assertRedirect(route('login'));
        $this->get(route('riwayat-pasien.index'))->assertRedirect(route('login'));
    }

    private function registration(): array
    {
        $poli = Poli::create(['nama_poli' => 'Umum', 'keterangan' => 'Pemeriksaan umum']);
        $doctor = User::factory()->for($poli, 'poli')->create(['role' => 'dokter']);
        $schedule = JadwalPeriksa::factory()->for($doctor, 'dokter')->create();
        $registration = DaftarPoli::create([
            'id_pasien' => User::factory()->create()->id, 'id_jadwal' => $schedule->id,
            'keluhan' => 'Demam', 'no_antrian' => 1,
        ]);

        return [$doctor, $registration];
    }

    private function data(DaftarPoli $registration): array
    {
        return ['id_daftar_poli' => $registration->id, 'obat_json' => '[]', 'catatan' => 'Istirahat'];
    }
}
