<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DokterController extends Controller
{
    public function index(): View
    {
        $dokters = User::where('role', 'dokter')->with('poli')->get();

        return view('admin.dokter.index', compact('dokters'));
    }

    public function create(): View
    {
        $polis = Poli::all();

        return view('admin.dokter.create', compact('polis'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_ktp' => 'required|string|max:16|unique:users,no_ktp',
            'no_hp' => 'required|string|max:15',
            'id_poli' => 'required|integer|exists:poli,id',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ], ['email.email' => 'Format email tidak valid.']);

        User::create([
            'nama' => $request->nama,
            'alamat' => $request->alamat,
            'no_ktp' => $request->no_ktp,
            'no_hp' => $request->no_hp,
            'id_poli' => $request->id_poli,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'dokter',
        ]);

        return redirect()->route('dokter.index')
            ->with('message', 'Data Dokter Berhasil di tambahkan')
            ->with('type', 'success');
    }

    public function edit(User $dokter): View
    {
        abort_unless($dokter->role === 'dokter', 404);
        $polis = Poli::all();

        return view('admin.dokter.edit', compact('dokter', 'polis'));
    }

    public function update(Request $request, User $dokter): RedirectResponse
    {
        abort_unless($dokter->role === 'dokter', 404);
        $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_ktp' => 'required|string|max:16|unique:users,no_ktp,'.$dokter->id,
            'no_hp' => 'required|string|max:15',
            'id_poli' => 'required|integer|exists:poli,id',
            'email' => 'required|string|email|max:255|unique:users,email,'.$dokter->id,
            'password' => 'nullable|string|min:6',
        ], ['email.email' => 'Format email tidak valid.']);

        $updateData = [
            'nama' => $request->nama,
            'alamat' => $request->alamat,
            'no_ktp' => $request->no_ktp,
            'no_hp' => $request->no_hp,
            'id_poli' => $request->id_poli,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $dokter->password = Hash::make($request->password);
        }

        $dokter->update($updateData);

        return redirect()->route('dokter.index')
            ->with('message', 'Data Dokter Berhasil di ubah')
            ->with('type', 'success');
    }

    public function destroy(User $dokter): RedirectResponse
    {
        abort_unless($dokter->role === 'dokter', 404);

        if ($dokter->jadwalPeriksas()->exists() || $dokter->daftarPolis()->exists()) {
            return redirect()->route('dokter.index')
                ->with('error', 'Dokter tidak dapat dihapus karena masih memiliki jadwal atau pendaftaran pasien.');
        }

        $dokter->delete();

        return redirect()->route('dokter.index')
            ->with('message', 'Data Dokter Berhasil dihapus')
            ->with('type', 'success');
    }
}
