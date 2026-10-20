<?php

namespace Database\Factories;

use App\Models\JadwalPeriksa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JadwalPeriksa>
 */
class JadwalPeriksaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_dokter' => User::factory()->state(['role' => 'dokter']),
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'status' => true,
        ];
    }
}
