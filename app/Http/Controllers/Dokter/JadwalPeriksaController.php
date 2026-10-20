<?php

namespace App\Http\Controllers\Dokter;

use App\Http\Controllers\Controller;
use App\Models\JadwalPeriksa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JadwalPeriksaController extends Controller
{
    public function index(): View
    {
        $jadwals = Auth::user()->jadwalPeriksas()->orderBy('hari')->orderBy('jam_mulai')->get();

        return view('dokter.jadwal-periksa.index', compact('jadwals'));
    }

    public function create(): View
    {
        return view('dokter.jadwal-periksa.create', ['hariOptions' => JadwalPeriksa::HARI]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateJadwal($request);

        Auth::user()->jadwalPeriksas()->create($validated);

        return redirect()->route('jadwal-periksa.index')->with('success', 'Jadwal periksa berhasil ditambahkan.');
    }

    public function edit(JadwalPeriksa $jadwalPeriksa): View
    {
        abort_unless((int) $jadwalPeriksa->id_dokter === (int) Auth::id(), 404);

        return view('dokter.jadwal-periksa.edit', [
            'jadwalPeriksa' => $jadwalPeriksa,
            'hariOptions' => JadwalPeriksa::HARI,
        ]);
    }

    public function update(Request $request, JadwalPeriksa $jadwalPeriksa): RedirectResponse
    {
        abort_unless((int) $jadwalPeriksa->id_dokter === (int) Auth::id(), 404);

        $validated = $this->validateJadwal($request);

        $jadwalPeriksa->update($validated);

        return redirect()->route('jadwal-periksa.index')->with('success', 'Jadwal periksa berhasil diperbarui.');
    }

    public function destroy(JadwalPeriksa $jadwalPeriksa): RedirectResponse
    {
        abort_unless((int) $jadwalPeriksa->id_dokter === (int) Auth::id(), 404);

        if ($jadwalPeriksa->daftarPolis()->exists()) {
            return redirect()->route('jadwal-periksa.index')
                ->with('error', 'Jadwal tidak dapat dihapus karena sudah memiliki pendaftaran pasien. Nonaktifkan jadwal jika tidak digunakan.');
        }

        $jadwalPeriksa->delete();

        return redirect()->route('jadwal-periksa.index')->with('success', 'Jadwal periksa berhasil dihapus.');
    }

    /**
     * @return array{hari: string, jam_mulai: string, jam_selesai: string, status?: bool|int|string}
     */
    private function validateJadwal(Request $request): array
    {
        return $request->validate([
            'hari' => ['required', Rule::in(JadwalPeriksa::HARI)],
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'status' => 'sometimes|boolean',
        ], [
            'hari.required' => 'Hari wajib dipilih.',
            'hari.in' => 'Hari yang dipilih tidak valid.',
            'jam_mulai.required' => 'Jam mulai wajib diisi.',
            'jam_mulai.date_format' => 'Jam mulai harus menggunakan format HH:MM.',
            'jam_selesai.required' => 'Jam selesai wajib diisi.',
            'jam_selesai.date_format' => 'Jam selesai harus menggunakan format HH:MM.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
            'status.boolean' => 'Status harus Aktif atau Tidak Aktif.',
        ]);
    }
}
