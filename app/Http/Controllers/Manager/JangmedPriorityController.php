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

        $query = FeatureRequest::with([
            'user:id,name,unit_id,instansi',
            'user.unit:id,name,manager_category_id',
            'requesterUnit:id,name,instansi,manager_category_id',
        ])
            ->orderByDesc('updated_at');

        if ($scope === 'completed') {
            $query->where(function ($builder) {
                $builder
                    ->where('status', 'done')
                    ->orWhere(function ($inner) {
                        $inner
                            ->where('status', 'approved_b')
                            ->whereNotNull('development_status')
                            ->where('development_status', '>=', 4);
                    });
            });
        } else {
            $query->where('status', 'approved_b')
                ->where(function ($builder) {
                    $builder
                        ->whereNull('development_status')
                        ->orWhere('development_status', '<', 4);
                });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function update(Request $request, FeatureRequest $featureRequest)
    {
        $this->ensureJangmedManager($request);

        if ($featureRequest->status === 'done' || ($featureRequest->status === 'approved_b' && $featureRequest->development_status !== null && $featureRequest->development_status >= 4)) {
            return response()->json([
                'message' => 'Prioritas ticket yang sudah selesai tidak dapat diubah.',
            ], 422);
        }

        if ($featureRequest->status !== 'approved_b') {
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
