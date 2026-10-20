<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\DetailPeriksa;
use App\Models\JadwalPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoliklinikRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_examination_relations_can_be_loaded_in_both_directions(): void
    {
        $poli = Poli::create(['nama_poli' => 'Umum', 'keterangan' => 'Poli umum']);
        $dokter = User::create([
            'nama' => 'Dokter', 'email' => 'dokter@example.com',
            'password' => 'rahasia123', 'role' => 'dokter', 'id_poli' => $poli->id,
        ]);
        $pasien = User::create([
            'nama' => 'Pasien', 'email' => 'pasien@example.com',
            'password' => 'rahasia123', 'role' => 'pasien', 'no_rm' => '202610-001',
        ]);
        $jadwal = JadwalPeriksa::create([
            'id_dokter' => $dokter->id, 'hari' => 'Senin',
            'jam_mulai' => '08:00', 'jam_selesai' => '12:00',
        ]);
        $daftar = DaftarPoli::create([
            'id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id,
            'keluhan' => 'Demam', 'no_antrian' => 1,
        ]);
        $periksa = Periksa::create([
            'id_daftar_poli' => $daftar->id, 'tgl_periksa' => '2026-10-13 08:30:00',
            'catatan' => 'Istirahat', 'biaya_periksa' => 50000,
        ]);
        $obat = Obat::create(['nama_obat' => 'Paracetamol', 'kemasan' => 'Strip', 'harga' => 10000]);
        $detail = DetailPeriksa::create(['id_periksa' => $periksa->id, 'id_obat' => $obat->id]);

        $this->assertTrue($poli->dokters->first()->is($dokter));
        $this->assertTrue($dokter->poli->is($poli));
        $this->assertTrue($dokter->jadwalPeriksas->first()->is($jadwal));
        $this->assertTrue($jadwal->dokter->is($dokter));
        $this->assertTrue($jadwal->daftarPolis->first()->is($daftar));
        $this->assertTrue($daftar->pasien->is($pasien));
        $this->assertTrue($daftar->jadwalPeriksa->is($jadwal));
        $this->assertTrue($daftar->periksas->first()->is($periksa));
        $this->assertTrue($periksa->daftarPoli->is($daftar));
        $this->assertTrue($periksa->detailPeriksas->first()->is($detail));
        $this->assertTrue($obat->detailPeriksas->first()->is($detail));
        $this->assertTrue($detail->periksa->is($periksa));
        $this->assertTrue($detail->obat->is($obat));
        $this->assertArrayNotHasKey('password', $dokter->toArray());
    }
}
