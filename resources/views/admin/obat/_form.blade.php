<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div>
        <label for="nama_obat" class="block text-sm font-semibold text-slate-700 mb-1">Nama Obat <span class="text-red-500">*</span></label>
        <input id="nama_obat" type="text" name="nama_obat" value="{{ old('nama_obat', $obat?->nama_obat) }}"
            maxlength="255" placeholder="Contoh: Paracetamol 500 mg" required
            class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary focus:outline-none @error('nama_obat') border-red-500 @enderror">
        @error('nama_obat')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="kemasan" class="block text-sm font-semibold text-slate-700 mb-1">Kemasan <span class="text-red-500">*</span></label>
        <input id="kemasan" type="text" name="kemasan" value="{{ old('kemasan', $obat?->kemasan) }}"
            maxlength="35" placeholder="Contoh: Strip isi 10 tablet" required
            class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary focus:outline-none @error('kemasan') border-red-500 @enderror">
        @error('kemasan')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="harga" class="block text-sm font-semibold text-slate-700 mb-1">Harga (Rp) <span class="text-red-500">*</span></label>
        <input id="harga" type="number" name="harga" value="{{ old('harga', $obat?->harga) }}"
            min="0" max="2147483647" step="1" placeholder="Contoh: 10000" required
            class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary focus:outline-none @error('harga') border-red-500 @enderror">
        @error('harga')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-primary hover:bg-primary/90 text-white font-semibold text-sm transition">
        <i class="fas fa-save" aria-hidden="true"></i>
        {{ $submitLabel }}
    </button>
    <a href="{{ route('obat.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-sm transition">Batal</a>
</div>
