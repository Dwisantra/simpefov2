<?php

namespace App\Http\Controllers\Manager;

use App\Enums\ManagerCategory;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\FeatureRequest;
use Illuminate\Http\Request;

class JangmedPriorityController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureJangmedManager($request);

        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));

        $scope = strtolower((string) $request->query('scope', 'active'));

        $statuses = match ($scope) {
            'completed' => ['done'],
            default => ['approved_b'],
        };

        $query = FeatureRequest::with([
            'user:id,name,unit_id,instansi',
            'user.unit:id,name,manager_category_id',
            'requesterUnit:id,name,instansi,manager_category_id',
        ])
            ->whereIn('status', $statuses)
            ->orderByDesc('updated_at');

        return $query->paginate($perPage)->withQueryString();
    }

    public function update(Request $request, FeatureRequest $featureRequest)
    {
        $this->ensureJangmedManager($request);

        if (! in_array($featureRequest->status, ['approved_b', 'done'], true)) {
            return response()->json([
                'message' => 'Prioritas hanya dapat diatur setelah proses pengajuan selesai.',
            ], 422);
        }

        $data = $request->validate([
            'priority' => ['required', 'in:biasa,sedang,cito'],
        ]);

        $featureRequest->priority = $data['priority'];
        $featureRequest->save();

        return response()->json([
            'message' => 'Prioritas ticket berhasil diperbarui.',
            'feature' => $featureRequest->fresh([
                'user:id,name,unit_id,instansi',
                'user.unit:id,name,manager_category_id',
                'requesterUnit:id,name,instansi,manager_category_id',
            ]),
        ]);
    }

    protected function ensureJangmedManager(Request $request): void
    {
        $user = $request->user();

        $role = UserRole::tryFromMixed($user?->level);
        $category = ManagerCategory::tryFromMixed($user?->manager_category_id);

        if ($role?->value !== UserRole::MANAGER->value || $category?->value !== ManagerCategory::JANGMED->value) {
            abort(403, 'Hanya manager Jangmed yang dapat mengatur prioritas ini.');
        }
    }
}
