<x-layouts.app title="Data Obat">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Data Obat</h2>
        <a href="{{ route('obat.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary/90 text-white rounded-xl text-sm font-semibold transition">
            <i class="fas fa-plus text-sm" aria-hidden="true"></i>
            Tambah Obat
        </a>
    </div>

    <div class="card bg-base-100 shadow-md rounded-2xl border border-slate-200">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-6 py-4">Nama Obat</th>
                            <th scope="col" class="px-6 py-4">Kemasan</th>
                            <th scope="col" class="px-6 py-4">Harga</th>
                            <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-slate-700">
                        @forelse($obats as $obat)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="px-6 py-4 font-semibold text-slate-800">{{ $obat->nama_obat }}</td>
                                <td class="px-6 py-4">{{ $obat->kemasan ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">Rp {{ number_format($obat->harga, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('obat.edit', $obat) }}" class="inline-flex items-center gap-1 px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-semibold transition">
                                            <i class="fas fa-pen-to-square" aria-hidden="true"></i> Edit
                                        </a>
                                        <form action="{{ route('obat.destroy', $obat) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus obat ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition">
                                                <i class="fas fa-trash" aria-hidden="true"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-16 text-slate-400">
                                    <i class="fas fa-pills text-3xl mb-3 block" aria-hidden="true"></i>
                                    Belum ada data obat
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
