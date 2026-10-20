<x-layouts.app title="Jadwal Periksa">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Jadwal Periksa</h2>
        <a href="{{ route('jadwal-periksa.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary/90 text-white rounded-xl text-sm font-semibold transition">
            <i class="fas fa-plus text-sm" aria-hidden="true"></i>
            Tambah Jadwal
        </a>
    </div>

    <div class="card bg-base-100 shadow-md rounded-2xl border border-slate-200">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-6 py-4">Hari</th>
                            <th scope="col" class="px-6 py-4">Jam Mulai</th>
                            <th scope="col" class="px-6 py-4">Jam Selesai</th>
                            <th scope="col" class="px-6 py-4">Status</th>
                            <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-slate-700">
                        @forelse($jadwals as $jadwal)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="px-6 py-4 font-semibold text-slate-800">{{ $jadwal->hari }}</td>
                                <td class="px-6 py-4">{{ substr($jadwal->jam_mulai, 0, 5) }}</td>
                                <td class="px-6 py-4">{{ substr($jadwal->jam_selesai, 0, 5) }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap {{ $jadwal->status ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $jadwal->status ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('jadwal-periksa.edit', $jadwal) }}" class="inline-flex items-center gap-1 px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-semibold transition">
                                            <i class="fas fa-pen-to-square" aria-hidden="true"></i> Edit
                                        </a>
                                        <form action="{{ route('jadwal-periksa.destroy', $jadwal) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus jadwal ini?')">
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
                                <td colspan="5" class="text-center py-16 text-slate-400">
                                    <i class="fas fa-calendar-days text-3xl mb-3 block" aria-hidden="true"></i>
                                    Belum ada jadwal periksa
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
