<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div>
        <label for="hari" class="block text-sm font-semibold text-slate-700 mb-1">Hari <span class="text-red-500">*</span></label>
        <select id="hari" name="hari" required
            class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary focus:outline-none @error('hari') border-red-500 @enderror">
            <option value="">Pilih Hari</option>
            @foreach($hariOptions as $hari)
                <option value="{{ $hari }}" @selected(old('hari', $jadwalPeriksa?->hari) === $hari)>{{ $hari }}</option>
            @endforeach
        </select>
        @error('hari')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
        <select id="status" name="status" required
            class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary focus:outline-none @error('status') border-red-500 @enderror">
            <option value="1" @selected((string) old('status', (int) ($jadwalPeriksa?->status ?? true)) === '1')>Aktif</option>
            <option value="0" @selected((string) old('status', (int) ($jadwalPeriksa?->status ?? true)) === '0')>Tidak Aktif</option>
        </select>
        @error('status')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="jam_mulai" class="block text-sm font-semibold text-slate-700 mb-1">Jam Mulai <span class="text-red-500">*</span></label>
        <input id="jam_mulai" type="time" name="jam_mulai" step="60" value="{{ old('jam_mulai', substr($jadwalPeriksa?->jam_mulai ?? '', 0, 5)) }}" required
            class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary focus:outline-none @error('jam_mulai') border-red-500 @enderror">
        @error('jam_mulai')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="jam_selesai" class="block text-sm font-semibold text-slate-700 mb-1">Jam Selesai <span class="text-red-500">*</span></label>
        <input id="jam_selesai" type="time" name="jam_selesai" step="60" value="{{ old('jam_selesai', substr($jadwalPeriksa?->jam_selesai ?? '', 0, 5)) }}" required
            class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary focus:outline-none @error('jam_selesai') border-red-500 @enderror">
        @error('jam_selesai')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-primary hover:bg-primary/90 text-white font-semibold text-sm transition">
        <i class="fas fa-save" aria-hidden="true"></i>
        {{ $submitLabel }}
    </button>
    <a href="{{ route('jadwal-periksa.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-sm transition">Batal</a>
</div>
