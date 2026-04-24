<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalValidationToken;
use App\Models\FeatureRequest;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApprovalValidationService
{
    /**
     * Generate a unique short code for the validation link
     */
    private function generateShortCode(): string
    {
        do {
            $code = Str::random(8);
        } while (ApprovalValidationToken::where('short_code', $code)->exists());

        return $code;
    }

    /**
     * Generate a validation token and return the link
     */
    public function generateValidationLink(Approval $approval, FeatureRequest $featureRequest): string
    {
        ApprovalValidationToken::where('approval_id', $approval->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $shortCode = $this->generateShortCode();
        $token = Str::random(32);
        $hashedToken = Hash::make($token);

        ApprovalValidationToken::create([
            'approval_id' => $approval->id,
            'feature_request_id' => $featureRequest->id,
            'user_id' => $approval->user_id,
            'token' => $hashedToken,
            'short_code' => $shortCode,
            'expires_at' => now()->addHours(24),
        ]);

        // Return the short URL
        return route('approval.validate-link', ['code' => $shortCode]);
    }

    /**
     * Generate a validation token and return both the link and expiration time
     */
    public function generateValidationLinkWithToken(Approval $approval, FeatureRequest $featureRequest): array
    {
        ApprovalValidationToken::where('approval_id', $approval->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $shortCode = $this->generateShortCode();
        $token = Str::random(32);
        $hashedToken = Hash::make($token);

        $expiresAt = now()->addHours(24);

        ApprovalValidationToken::create([
            'approval_id' => $approval->id,
            'feature_request_id' => $featureRequest->id,
            'user_id' => $approval->user_id,
            'token' => $hashedToken,
            'short_code' => $shortCode,
            'expires_at' => $expiresAt,
        ]);

        // Return the short URL with expiration time
        return [
            'link' => route('approval.validate-link', ['code' => $shortCode]),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Verify and process the validation token
     */
    public function verifyValidationToken(string $shortCode): ?ApprovalValidationToken
    {
        $validationToken = ApprovalValidationToken::where('short_code', $shortCode)
            ->with(['approval', 'featureRequest', 'user'])
            ->first();

        if (!$validationToken) {
            return null;
        }

        if ($validationToken->isExpired()) {
            return null;
        }

        if ($validationToken->isUsed()) {
            return null;
        }

        return $validationToken;
    }

    /**
     * Process the approval after verification with sign code
     */
    public function processApprovalValidation(
        ApprovalValidationToken $validationToken,
        string $signCode,
        ?string $note = null
    ): array {
        $approval = $validationToken->approval;
        $featureRequest = $validationToken->featureRequest;
        $featureRequest->loadMissing('user.unit');

        $instansi = $featureRequest->requester_instansi ?? $featureRequest->user?->instansi;
        $requiresDirectorA = !(
            $instansi === 'wiradadi'
            && config('feature-requests.skip_raffa_director_for_wiradadi')
        );

        $stageMap = [
            'pending' => UserRole::MANAGER->value,
            'approved_manager' => $requiresDirectorA ? UserRole::DIRECTOR_A->value : UserRole::DIRECTOR_B->value,
            'approved_a' => UserRole::DIRECTOR_B->value,
        ];

        $expectedRole = $stageMap[$featureRequest->status] ?? null;

        if (!$expectedRole) {
            return [
                'success' => false,
                'message' => 'Pengajuan ini sudah selesai diproses.'
            ];
        }

        if ($featureRequest->approvals()->where('role', $expectedRole)->exists()) {
            return [
                'success' => false,
                'message' => 'Persetujuan untuk peran ini sudah tercatat.'
            ];
        }

        $approvingUser = $this->findApprovingUser($featureRequest, $expectedRole);

        if (!$approvingUser) {
            return [
                'success' => false,
                'message' => 'Tidak dapat menemukan user yang seharusnya melakukan persetujuan.'
            ];
        }

        if (!$approvingUser->kode_sign || !Hash::check($signCode, $approvingUser->kode_sign)) {
            return [
                'success' => false,
                'message' => 'Kode ACC tidak sesuai.'
            ];
        }

        try {
            $featureRequest->approvals()->create([
                'user_id' => $approvingUser->id,
                'role' => $expectedRole,
                'sign_code' => Hash::make($signCode),
                'note' => $note,
                'approved_at' => now(),
            ]);

            $validationToken->markAsUsed();

            $nextStatus = [
                'pending' => 'approved_manager',
                'approved_manager' => $requiresDirectorA ? 'approved_a' : 'approved_b',
                'approved_a' => 'approved_b',
            ][$featureRequest->status] ?? null;

            if ($nextStatus) {
                $featureRequest->update(['status' => $nextStatus]);
            }

            return [
                'success' => true,
                'message' => 'Persetujuan berhasil dicatat.',
                'feature_request_id' => $featureRequest->id,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses persetujuan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Find the user who should approve based on feature request and required role
     */
    private function findApprovingUser(FeatureRequest $featureRequest, int $role): ?User
    {
        if ($role === UserRole::MANAGER->value) {
            $managerCategory = $featureRequest->manager_category_id ?? 
                              $featureRequest->user?->unit?->manager_category_id;

            if ($managerCategory) {
                return User::where('level', UserRole::MANAGER->value)
                    ->where('manager_category_id', $managerCategory)
                    ->where('verified_at', '!=', null)
                    ->first();
            }
        }
        
        // if (in_array($role, [UserRole::DIRECTOR_A->value, UserRole::DIRECTOR_B->value])) {
        //     return User::where('level', $role)
        //         ->where('verified_at', '!=', null)
        //         ->first();
        // }

        return null;
    }
}
