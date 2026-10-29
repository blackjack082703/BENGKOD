<?php

namespace App\Http\Controllers\Dokter;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePeriksaRequest;
use App\Models\DaftarPoli;
use App\Models\Obat;
use App\Models\Periksa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PeriksaPasienController extends Controller
{
    public function index(): View
    {
        $dokterId = Auth::id();

        $daftarPasien = DaftarPoli::with(['pasien', 'jadwalPeriksa', 'periksas'])
            ->whereHas('jadwalPeriksa', function ($query) use ($dokterId) {
                $query->where('id_dokter', $dokterId);
            })
            ->orderBy('no_antrian')
            ->get();

        return view('dokter.periksa-pasien.index', compact('daftarPasien'));
    }

    public function create(string $id): View|RedirectResponse
    {
        $daftar = DaftarPoli::whereHas('jadwalPeriksa', fn (Builder $query): Builder => $query->where('id_dokter', Auth::id()))
            ->findOrFail($id);

        if ($daftar->periksas()->exists()) {
            return redirect()->route('periksa-pasien.index')->with('error', 'Pasien pada pendaftaran ini sudah diperiksa.');
        }

        $obats = Obat::all();

        return view('dokter.periksa-pasien.create', [
            'obats' => $obats,
            'id' => $daftar->id,
            'biayaJasa' => Periksa::BIAYA_JASA,
        ]);
    }

    public function store(StorePeriksaRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $daftar = DaftarPoli::whereHas('jadwalPeriksa', fn (Builder $query): Builder => $query->where('id_dokter', Auth::id()))
                ->whereKey($validated['id_daftar_poli'])->lockForUpdate()->firstOrFail();

            if ($daftar->periksas()->exists()) {
                throw ValidationException::withMessages([
                    'id_daftar_poli' => 'Pasien pada pendaftaran ini sudah diperiksa.',
                ]);
            }

            $obats = Obat::whereIn('id', $validated['obat_ids'])->orderBy('id')->lockForUpdate()->get();

            if ($obats->count() !== count($validated['obat_ids'])) {
                throw ValidationException::withMessages(['obat_json' => 'Obat yang dipilih tidak tersedia.']);
            }

            $totalBiaya = Periksa::BIAYA_JASA + (int) $obats->sum('harga');

            if ($totalBiaya > 2147483647) {
                throw ValidationException::withMessages(['obat_json' => 'Total biaya melebihi batas yang dapat disimpan.']);
            }

            $periksa = $daftar->periksas()->create([
                'tgl_periksa' => now(),
                'catatan' => $validated['catatan'] ?? null,
                'biaya_periksa' => $totalBiaya,
            ]);

            foreach ($obats as $obat) {
                $periksa->detailPeriksas()->create(['id_obat' => $obat->id]);
            }
        }, 3);

        return redirect()
            ->route('periksa-pasien.index')
            ->with('success', 'Data periksa berhasil disimpan.');
    }
}
