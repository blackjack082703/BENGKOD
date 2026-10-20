<x-layouts.app title="Tambah Jadwal Periksa">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('jadwal-periksa.index') }}" aria-label="Kembali ke daftar"
            class="flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition">
            <i class="fas fa-arrow-left text-sm" aria-hidden="true"></i>
        </a>
        <h2 class="text-2xl font-bold text-slate-800">Tambah Jadwal Periksa</h2>
    </div>

    <div class="card bg-base-100 shadow-md rounded-2xl border border-slate-200">
        <div class="card-body p-6 md:p-8">
            <form action="{{ route('jadwal-periksa.store') }}" method="POST">
                @csrf
                @include('dokter.jadwal-periksa._form', ['jadwalPeriksa' => null, 'hariOptions' => $hariOptions, 'submitLabel' => 'Simpan'])
            </form>
        </div>
    </div>
</x-layouts.app>
