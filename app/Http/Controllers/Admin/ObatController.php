<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObatController extends Controller
{
    public function index(): View
    {
        $obats = Obat::orderBy('nama_obat')->get();

        return view('admin.obat.index', compact('obats'));
    }

    public function create(): View
    {
        return view('admin.obat.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateObat($request);

        Obat::create($validated);

        return redirect()->route('obat.index')->with('success', 'Obat berhasil ditambahkan.');
    }

    public function edit(Obat $obat): View
    {
        return view('admin.obat.edit', compact('obat'));
    }

    public function update(Request $request, Obat $obat): RedirectResponse
    {
        $validated = $this->validateObat($request);

        $obat->update($validated);

        return redirect()->route('obat.index')->with('success', 'Obat berhasil diperbarui.');
    }

    public function destroy(Obat $obat): RedirectResponse
    {
        if ($obat->detailPeriksas()->exists()) {
            return redirect()->route('obat.index')
                ->with('error', 'Obat tidak dapat dihapus karena sudah digunakan dalam pemeriksaan.');
        }

        $obat->delete();

        return redirect()->route('obat.index')->with('success', 'Obat berhasil dihapus.');
    }

    /**
     * @return array{nama_obat: string, kemasan: string, harga: int|string}
     */
    private function validateObat(Request $request): array
    {
        return $request->validate([
            'nama_obat' => 'required|string|max:255',
            'kemasan' => 'required|string|max:35',
            'harga' => 'required|integer|min:0|max:2147483647',
        ], [
            'nama_obat.required' => 'Nama obat wajib diisi.',
            'nama_obat.string' => 'Nama obat harus berupa teks.',
            'nama_obat.max' => 'Nama obat maksimal 255 karakter.',
            'kemasan.required' => 'Kemasan wajib diisi.',
            'kemasan.string' => 'Kemasan harus berupa teks.',
            'kemasan.max' => 'Kemasan maksimal 35 karakter.',
            'harga.required' => 'Harga wajib diisi.',
            'harga.integer' => 'Harga harus berupa bilangan bulat dalam rupiah.',
            'harga.min' => 'Harga tidak boleh negatif.',
            'harga.max' => 'Harga maksimal Rp2.147.483.647.',
        ]);
    }
}
