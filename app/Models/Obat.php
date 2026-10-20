<?php

namespace App\Models;

use Database\Factories\ObatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obat extends Model
{
    /** @use HasFactory<ObatFactory> */
    use HasFactory;

    protected $table = 'obat';

    protected $fillable = [
        'nama_obat',
        'kemasan',
        'harga',
    ];

    public function detailPeriksas(): HasMany
    {
        return $this->hasMany(DetailPeriksa::class, 'id_obat');
    }
}
