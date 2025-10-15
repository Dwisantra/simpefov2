<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\\d).+$/', 'confirmed'],
            'password_confirmation' => 'required|string|min:8',
            'phone' => 'required|string|max:30',
            'instansi' => 'required|in:wiradadi,raffa',
            'unit_id' => 'required|exists:units,id',
        ]);

        $unit = Unit::where('id', $data['unit_id'])
            ->where('is_active', true)
            ->first();

        if (! $unit) {
            return response()->json([
                'message' => 'Unit tidak ditemukan atau tidak aktif.'
            ], 422);
        }

        if ($unit->instansi !== $data['instansi']) {
            return response()->json([
                'message' => 'Unit tidak sesuai dengan instansi yang dipilih.'
            ], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'initial_password' => Crypt::encryptString($data['password']),
            'level' => UserRole::USER->value,
            'phone' => $data['phone'],
            'instansi' => $data['instansi'],
            'unit_id' => $data['unit_id'],
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil. Menunggu verifikasi admin sebelum dapat masuk.',
            'user' => $user->fresh(['unit']),
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_verified) {
            return response()->json([
                'message' => 'Akun belum diverifikasi oleh admin.'
            ], 403);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->fresh(['unit'])
        ]);
    }

    public function me(Request $request)
    {
        return $request->user()->fresh(['unit']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function updateKodeSign(Request $request)
    {
        $request->validate([
            'kode_sign' => 'required|string|min:4|max:10'
        ]);

        $user = $request->user();

        if (! $user->is_verified) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi oleh admin.'
            ], 403);
        }

        $user->kode_sign = Hash::make($request->kode_sign);
        $user->save();

        return response()->json([
            'message' => 'Kode ACC berhasil disimpan',
            'user' => $user->fresh(['unit'])
        ]);
    }
}
