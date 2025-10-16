<?php

namespace App\Http\Controllers;

use App\Enums\ManagerCategory;
use App\Enums\UserRole;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $query = Unit::orderBy('name');

        if ($request->boolean('all')) {
            return $query->get();
        }

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'instansi' => 'required|in:wiradadi,raffa',
            'is_active' => 'sometimes|boolean',
            'manager_category_id' => [
                'nullable',
                'integer',
                Rule::in(array_map(fn (ManagerCategory $category) => $category->value, ManagerCategory::cases())),
            ],
        ]);

        $unit = Unit::create([
            'name' => $data['name'],
            'instansi' => $data['instansi'],
            'is_active' => $data['is_active'] ?? true,
            'manager_category_id' => $data['manager_category_id'] ?? null,
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
            'manager_category_id' => [
                'nullable',
                'integer',
                Rule::in(array_map(fn (ManagerCategory $category) => $category->value, ManagerCategory::cases())),
            ],
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
