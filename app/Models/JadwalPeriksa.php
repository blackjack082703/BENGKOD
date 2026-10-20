<?php

namespace App\Models;

use Database\Factories\JadwalPeriksaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalPeriksa extends Model
{
    /** @use HasFactory<JadwalPeriksaFactory> */
    use HasFactory;

    public const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    protected $table = 'jadwal_periksa';

    protected $fillable = [
        'id_dokter',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_dokter');
    }

    public function daftarPolis(): HasMany
    {
        return $this->hasMany(DaftarPoli::class, 'id_jadwal');
    }
}
