<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'nama',
        'alamat',
        'no_ktp',
        'no_hp',
        'no_rm',
        'role',
        'id_poli',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @param  array{nama: string, alamat: string, no_ktp: string, no_hp: string, email: string, password: string}  $attributes
     */
    public static function createPatient(array $attributes): self
    {
        return DB::transaction(function () use ($attributes): self {
            $month = now('Asia/Jakarta')->format('Ym');
            DB::table('medical_record_sequences')->insertOrIgnore([
                'month' => $month,
                'last_number' => 0,
            ]);

            $sequence = DB::table('medical_record_sequences')
                ->where('month', $month)->lockForUpdate()->first();

            $existingNumber = self::where('no_rm', 'like', $month.'-%')
                ->pluck('no_rm')
                ->map(fn (string $number): int => (int) substr($number, 7))
                ->max() ?? 0;
            $nextNumber = max($sequence->last_number, $existingNumber) + 1;

            DB::table('medical_record_sequences')->where('month', $month)
                ->update(['last_number' => $nextNumber]);

            return self::create([
                'nama' => $attributes['nama'],
                'alamat' => $attributes['alamat'],
                'no_ktp' => $attributes['no_ktp'],
                'no_hp' => $attributes['no_hp'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'no_rm' => $month.'-'.str_pad($nextNumber, 3, '0', STR_PAD_LEFT),
                'role' => 'pasien',
            ]);
        }, 3);
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class, 'id_poli');
    }

    public function jadwalPeriksas()
    {
        return $this->hasMany(JadwalPeriksa::class, 'id_dokter');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
