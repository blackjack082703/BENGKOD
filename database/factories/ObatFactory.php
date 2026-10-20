<?php

namespace Database\Factories;

use App\Models\Obat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obat>
 */
class ObatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_obat' => fake()->word(),
            'kemasan' => 'Strip',
            'harga' => 10000,
        ];
    }
}
