<x-layouts.app title="Tambah Pasien">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('pasien.index') }}" aria-label="Kembali ke daftar pasien"
            class="flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition">
            <i class="fas fa-arrow-left text-sm" aria-hidden="true"></i>
        </a>
        <h2 class="text-2xl font-bold text-slate-800">Tambah Pasien</h2>
    </div>

    <div class="card bg-base-100 shadow-md rounded-2xl border border-slate-200">
        <div class="card-body p-6 md:p-8">
            <p class="text-sm text-slate-500 mb-6">Lengkapi data dan akun pasien. Nomor rekam medis dibuat otomatis setelah data disimpan.</p>

            <form action="{{ route('pasien.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="nama" class="block text-sm font-semibold text-slate-700 mb-1">
                            Nama Pasien <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="nama" name="nama" value="{{ old('nama') }}" maxlength="255"
                            autocomplete="name" placeholder="Masukkan nama pasien..." required
                            aria-invalid="{{ $errors->has('nama') ? 'true' : 'false' }}" @error('nama') aria-describedby="nama-error" @enderror
                            class="w-full px-4 py-2 rounded-lg border-2 border-slate-300 focus:border-primary focus:outline-none @error('nama') border-red-500 @enderror">
                        @error('nama')
                            <p id="nama-error" class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255"
                            autocomplete="email" placeholder="Masukkan email..." required
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror
                            class="w-full px-4 py-2 rounded-lg border-2 border-slate-300 focus:border-primary focus:outline-none @error('email') border-red-500 @enderror">
                        @error('email')
                            <p id="email-error" class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="no_ktp" class="block text-sm font-semibold text-slate-700 mb-1">
                            No. KTP <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="no_ktp" name="no_ktp" value="{{ old('no_ktp') }}"
                            inputmode="numeric" pattern="[0-9]+" maxlength="16" placeholder="Masukkan No. KTP..." required
                            aria-invalid="{{ $errors->has('no_ktp') ? 'true' : 'false' }}" @error('no_ktp') aria-describedby="no_ktp-error" @enderror
                            class="w-full px-4 py-2 rounded-lg border-2 border-slate-300 focus:border-primary focus:outline-none @error('no_ktp') border-red-500 @enderror">
                        @error('no_ktp')
                            <p id="no_ktp-error" class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="no_hp" class="block text-sm font-semibold text-slate-700 mb-1">
                            No. HP <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" id="no_hp" name="no_hp" value="{{ old('no_hp') }}" autocomplete="tel"
                            inputmode="numeric" pattern="[0-9]+" maxlength="15" placeholder="Masukkan No. HP..." required
                            aria-invalid="{{ $errors->has('no_hp') ? 'true' : 'false' }}" @error('no_hp') aria-describedby="no_hp-error" @enderror
                            class="w-full px-4 py-2 rounded-lg border-2 border-slate-300 focus:border-primary focus:outline-none @error('no_hp') border-red-500 @enderror">
                        @error('no_hp')
                            <p id="no_hp-error" class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="alamat" class="block text-sm font-semibold text-slate-700 mb-1">
                            Alamat <span class="text-red-500">*</span>
                        </label>
                        <textarea id="alamat" name="alamat" rows="3" maxlength="255" autocomplete="street-address"
                            placeholder="Masukkan alamat..." required
                            aria-invalid="{{ $errors->has('alamat') ? 'true' : 'false' }}" @error('alamat') aria-describedby="alamat-error" @enderror
                            class="w-full px-4 py-2 rounded-lg border-2 border-slate-300 focus:border-primary focus:outline-none @error('alamat') border-red-500 @enderror">{{ old('alamat') }}</textarea>
                        @error('alamat')
                            <p id="alamat-error" class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="password" name="password" minlength="6" autocomplete="new-password"
                            placeholder="Minimal 6 karakter..." required
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror
                            class="w-full px-4 py-2 rounded-lg border-2 border-slate-300 focus:border-primary focus:outline-none @error('password') border-red-500 @enderror">
                        @error('password')
                            <p id="password-error" class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1">
                            Konfirmasi Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="password_confirmation" name="password_confirmation" minlength="6"
                            autocomplete="new-password" placeholder="Ulangi password..." required
                            class="w-full px-4 py-2 rounded-lg border-2 border-slate-300 focus:border-primary focus:outline-none">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-6 py-2.5 rounded-lg bg-primary hover:bg-primary/90 text-white font-semibold text-sm transition">
                        <i class="fas fa-save mr-1" aria-hidden="true"></i> Simpan
                    </button>
                    <a href="{{ route('pasien.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-sm transition">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
