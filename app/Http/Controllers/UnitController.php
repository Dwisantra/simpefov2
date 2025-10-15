<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function publicIndex()
    {
        return Unit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        return Unit::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'instansi' => 'required|in:wiradadi,raffa',
            'is_active' => 'sometimes|boolean',
        ]);

        $unit = Unit::create([
            'name' => $data['name'],
            'instansi' => $data['instansi'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($unit, 201);
    }

    public function update(Request $request, Unit $unit)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'instansi' => 'sometimes|required|in:wiradadi,raffa',
            'is_active' => 'sometimes|boolean',
        ]);

        $unit->fill($data);
        $unit->save();

        return response()->json($unit);
    }

    public function destroy(Request $request, Unit $unit)
    {
        $this->ensureAdmin($request);

        if ($unit->users()->exists()) {
            return response()->json([
                'message' => 'Unit tidak dapat dihapus karena masih memiliki pengguna.'
            ], 422);
        }

        $unit->delete();

        return response()->json(['message' => 'Unit berhasil dihapus.']);
    }

    protected function ensureAdmin(Request $request): void
    {
        if (UserRole::tryFromMixed($request->user()->level ?? $request->user()->role)?->value !== UserRole::ADMIN->value) {
            abort(403, 'Hanya admin yang dapat mengelola unit.');
        }
    }
}
