<?php

namespace App\Http\Controllers\Pasien;

use App\Http\Controllers\Controller;
use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PoliController extends Controller
{
    public function get(): View
    {
        $user = Auth::user();
        $polis = Poli::all();
        $jadwal = JadwalPeriksa::with('dokter', 'dokter.poli')
            ->where('status', true)
            ->whereHas('dokter.poli')
            ->get();

        return view('pasien.daftar', [
            'user' => $user,
            'polis' => $polis,
            'jadwals' => $jadwal,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id_poli' => 'required|integer|exists:poli,id',
            'id_jadwal' => 'required|integer|exists:jadwal_periksa,id',
            'keluhan' => 'required|string',
        ], [
            'keluhan.required' => 'Keluhan wajib diisi.',
            'keluhan.string' => 'Keluhan harus berupa teks.',
        ]);

        DB::transaction(function () use ($validated): void {
            $pasien = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();

            $jadwal = JadwalPeriksa::whereKey($validated['id_jadwal'])
                ->where('status', true)
                ->whereHas('dokter', fn (Builder $query): Builder => $query->where('id_poli', $validated['id_poli']))
                ->lockForUpdate()
                ->first();

            if (! $jadwal) {
                throw ValidationException::withMessages([
                    'id_jadwal' => 'Jadwal yang dipilih tidak aktif atau tidak sesuai dengan poli pilihan.',
                ]);
            }

            $pendaftaranAktif = DaftarPoli::where('id_pasien', $pasien->id)
                ->whereDoesntHave('periksas')->lockForUpdate()->first(['id']);

            if ($pendaftaranAktif) {
                throw ValidationException::withMessages([
                    'id_jadwal' => 'Anda masih memiliki pendaftaran aktif. Selesaikan pemeriksaan terlebih dahulu sebelum mendaftar kembali.',
                ]);
            }

            $nomorTerakhir = (int) DaftarPoli::where('id_jadwal', $jadwal->id)
                ->orderByDesc('no_antrian')->lockForUpdate()->value('no_antrian');

            if ($nomorTerakhir >= 2147483647) {
                throw ValidationException::withMessages(['id_jadwal' => 'Nomor antrean pada jadwal ini sudah mencapai batas.']);
            }

            DaftarPoli::create([
                'id_pasien' => $pasien->id,
                'id_jadwal' => $validated['id_jadwal'],
                'keluhan' => $validated['keluhan'],
                'no_antrian' => $nomorTerakhir + 1,
            ]);
        }, 3);

        return redirect()
            ->back()
            ->with('message', 'Berhasil Mendaftar ke Poli')
            ->with('type', 'success');
    }
}
