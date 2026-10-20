<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } else if ($user->role === 'dokter') {
                return redirect()->route('dokter.dashboard');
            } else {
                return redirect()->route('pasien.dashboard');
            }
        }

        return back()->withErrors(['email', 'Email atau password salah']);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string|max:255',
            'no_ktp' => 'required|string|regex:/^[0-9]+$/|max:255|unique:users,no_ktp',
            'no_hp' => 'required|string|regex:/^[0-9]+$/|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        DB::transaction(function () use ($validated) {
            $month = now('Asia/Jakarta')->format('Ym');
            DB::table('medical_record_sequences')->insertOrIgnore([
                'month' => $month,
                'last_number' => 0,
            ]);

            $sequence = DB::table('medical_record_sequences')
                ->where('month', $month)->lockForUpdate()->first();

            // Include existing medical record numbers when initializing the sequence.
            $existingNumber = User::where('no_rm', 'like', $month.'-%')
                ->pluck('no_rm')
                ->map(fn ($number) => (int) substr($number, 7))
                ->max() ?? 0;
            $nextNumber = max($sequence->last_number, $existingNumber) + 1;

            DB::table('medical_record_sequences')->where('month', $month)
                ->update(['last_number' => $nextNumber]);

            User::create([
                ...$validated,
                'no_rm' => $month.'-'.str_pad($nextNumber, 3, '0', STR_PAD_LEFT),
                'role' => 'pasien',
                'password' => Hash::make($validated['password']),
            ]);
        }, 3);

        return redirect()->route('login')->with('success', 'Registrasi berhasil! Silakan login.');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}
