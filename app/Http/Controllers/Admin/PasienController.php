<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasienController extends Controller
{
    public function index()
    {
        $pasiens = User::where('role', 'pasien')->with('poli')->get();

        return view('admin.pasien.index', compact('pasiens'));
    }

    public function create(): View
    {
        return view('admin.pasien.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string|max:255',
            'no_ktp' => 'required|string|regex:/^[0-9]+$/|max:16|unique:users,no_ktp',
            'no_hp' => 'required|string|regex:/^[0-9]+$/|max:15',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute maksimal :max karakter.',
            'regex' => ':attribute hanya boleh berisi angka.',
            'email.email' => 'Format email tidak valid.',
            'unique' => ':attribute sudah terdaftar.',
            'password.min' => 'Password minimal :min karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ], [
            'nama' => 'Nama pasien',
            'alamat' => 'Alamat',
            'no_ktp' => 'No. KTP',
            'no_hp' => 'No. HP',
            'email' => 'Email',
            'password' => 'Password',
        ]);

        User::createPatient($validated);

        return redirect()->route('pasien.index')->with('success', 'Pasien berhasil ditambahkan.');
    }

    public function edit(User $pasien)
    {
        return view('admin.pasien.edit', compact('pasien'));
    }

    public function update(Request $request, User $pasien)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_ktp' => 'required|string|max:16|unique:users,no_ktp,'.$pasien->id,
            'no_hp' => 'required|string|max:15',
            'email' => 'required|string|email|unique:users,email,'.$pasien->id,
            'password' => 'required|string|min:6',
        ]);

        $updateData = [
            'nama' => $request->nama,
            'alamat' => $request->alamat,
            'no_ktp' => $request->no_ktp,
            'no_hp' => $request->no_hp,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $pasien->password = Hash::make($request->password);
        }

        $pasien->update($updateData);

        return redirect()
            ->route('pasien.index')
            ->with('message', 'Data Pasien berhasil di Ubah>')
            ->with('type', 'success');
    }

    public function destroy(User $pasien)
    {
        $pasien->delete();

        return redirect()
            ->route('pasien.index')
            ->with('message', 'Data Pasien berhasil dihapus')
            ->with('type', 'success');
    }
}
