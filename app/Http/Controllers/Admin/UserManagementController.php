<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        return User::with('unit')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAdmin($request);

        $roleValues = array_map(fn (UserRole $role) => $role->value, UserRole::cases());

        $data = $request->validate([
            'level' => ['sometimes', 'required', 'integer', Rule::in($roleValues)],
            'instansi' => ['sometimes', 'required', Rule::in(['wiradadi', 'raffa'])],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'is_verified' => ['sometimes', 'boolean'],
        ]);

        $targetInstansi = $data['instansi'] ?? $user->instansi;
        $targetUnit = null;

        if (array_key_exists('unit_id', $data) && $data['unit_id']) {
            $targetUnit = Unit::whereKey($data['unit_id'])->first();

            if ($targetUnit && $targetInstansi && $targetUnit->instansi !== $targetInstansi) {
                return response()->json([
                    'message' => 'Unit tidak sesuai dengan instansi yang dipilih.'
                ], 422);
            }
        }

        if (array_key_exists('instansi', $data)) {
            $user->instansi = $data['instansi'];
        }

        if (array_key_exists('level', $data)) {
            $user->level = $data['level'];
        }

        if (array_key_exists('is_verified', $data)) {
            $user->verified_at = $data['is_verified']
                ? ($user->verified_at ?? now())
                : null;
        }

        if ($targetUnit) {
            $user->unit()->associate($targetUnit);
        } elseif (array_key_exists('unit_id', $data)) {
            $user->unit()->dissociate();
        } elseif (
            array_key_exists('instansi', $data)
            && $user->unit
            && $targetInstansi
            && $user->unit->instansi !== $targetInstansi
        ) {
            $user->unit()->dissociate();
        }

        $user->save();

        return response()->json([
            'message' => 'Data pengguna berhasil diperbarui.',
            'user' => $user->fresh(['unit']),
        ]);
    }

    protected function ensureAdmin(Request $request): void
    {
        if (UserRole::tryFromMixed($request->user()->level ?? $request->user()->role)?->value !== UserRole::ADMIN->value) {
            abort(403, 'Hanya admin yang dapat mengelola master data.');
        }
    }
}
