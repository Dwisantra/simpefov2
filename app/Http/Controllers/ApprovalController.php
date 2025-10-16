<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FeatureRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApprovalController extends Controller
{
    public function approve(Request $request, FeatureRequest $featureRequest)
    {
        $data = $request->validate([
            'sign_code' => 'required|string|min:4|max:20',
            'note' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        if (! $user->kode_sign) {
            return response()->json([
                'message' => 'Anda belum menyimpan kode ACC.'
            ], 422);
        }

        if (! Hash::check($data['sign_code'], $user->kode_sign)) {
            return response()->json([
                'message' => 'Kode ACC tidak sesuai.'
            ], 422);
        }

        $role = UserRole::tryFromMixed($user->level);

        if (! $role) {
            return response()->json([
                'message' => 'Role pengguna tidak dikenali.'
            ], 422);
        }

        $featureRequest->loadMissing('user.unit');

        $requiresDirectorA = true;

        if ($role->value === UserRole::MANAGER->value) {
            $managerCategoryId = (int) ($user->manager_category_id ?? 0);

            if ($managerCategoryId === 0) {
                return response()->json([
                    'message' => 'Anda tidak memiliki kategori manajer yang valid untuk persetujuan ini.'
                ], 403);
            }

            $ticketCategoryId = (int) ($featureRequest->manager_category_id ?? 0);

            if ($ticketCategoryId === 0) {
                $ticketCategoryId = (int) ($featureRequest->user?->unit?->manager_category_id ?? 0);
            }

            if ($ticketCategoryId === 0 || $ticketCategoryId !== $managerCategoryId) {
                return response()->json([
                    'message' => 'Ticket ini tidak berada dalam penugasan kategori Anda.'
                ], 403);
            }
        }

        $stageMap = [
            'pending' => UserRole::MANAGER,
            'approved_manager' => $requiresDirectorA ? UserRole::DIRECTOR_A : UserRole::DIRECTOR_B,
            'approved_a' => UserRole::DIRECTOR_B,
        ];

        $expectedRole = $stageMap[$featureRequest->status] ?? null;

        if (! $expectedRole) {
            return response()->json([
                'message' => 'Pengajuan ini sudah selesai diproses.'
            ], 422);
        }

        if ($expectedRole->value !== $role->value) {
            return response()->json([
                'message' => 'Anda belum dapat melakukan persetujuan pada tahap ini.'
            ], 422);
        }

        if ($featureRequest->approvals()->where('role', $role->value)->exists()) {
            return response()->json([
                'message' => 'Persetujuan untuk peran ini sudah tercatat.'
            ], 422);
        }

        $featureRequest->approvals()->create([
            'user_id' => $user->id,
            'role' => $role->value,
            'sign_code' => Hash::make($data['sign_code']),
            'note' => $data['note'] ?? null,
            'approved_at' => now(),
        ]);

        $newStatus = match ($role) {
            UserRole::MANAGER => $requiresDirectorA ? 'approved_manager' : 'approved_a',
            UserRole::DIRECTOR_A => 'approved_a',
            UserRole::DIRECTOR_B => 'approved_b',
            default => $featureRequest->status,
        };

        $featureRequest->update(['status' => $newStatus]);

        $featureRequest->load([
            'approvals.user:id,name,level',
            'user:id,name,level',
            'comments.user:id,name,level'
        ])->loadCount('comments');

        return response()->json([
            'message' => 'Persetujuan berhasil dicatat.',
            'feature' => $featureRequest,
        ]);
    }
}
