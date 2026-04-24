<?php

namespace App\Http\Controllers;

use App\Models\FeatureRequest;
use App\Models\ApprovalValidationToken;
use App\Services\ApprovalValidationService;
use Illuminate\Http\Request;

class ApprovalValidationController extends Controller
{
    protected ApprovalValidationService $validationService;

    public function __construct(ApprovalValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Generate validation link for an approval
     * Called after creating an approval
     */
    public function generateLink(Request $request, FeatureRequest $featureRequest)
    {
        $user = $request->user();

        if (!$user->kode_sign) {
            return response()->json([
                'message' => 'Anda belum menyimpan kode ACC.'
            ], 422);
        }

        $featureRequest->loadMissing('approvals.user');

        // Find the pending approval for this feature request
        $approval = $featureRequest->approvals()
            ->orderByDesc('created_at')
            ->first();

        if (!$approval) {
            return response()->json([
                'message' => 'Tidak ada persetujuan yang ditemukan untuk pengajuan ini.'
            ], 404);
        }

        try {
            $result = $this->validationService->generateValidationLinkWithToken($approval, $featureRequest);

            return response()->json([
                'success' => true,
                'message' => 'Link Approval Manager berhasil dibuat',
                'link' => $result['link'],
                'expires_at' => $result['expires_at'],
                'expires_in' => '24 jam',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the validation form page (accessible without authentication)
     */
    public function showValidationPage(Request $request, string $code)
    {
        $validationToken = ApprovalValidationToken::where('short_code', $code)
            ->with(['featureRequest', 'approval'])
            ->first();

        if (!$validationToken) {
            return view('approval.validation-expired', [
                'message' => 'Link tidak ditemukan atau sudah kadaluarsa.',
                'reason' => 'invalid'
            ]);
        }

        // Check if token has been used
        if ($validationToken->used_at) {
            return view('approval.validation-expired', [
                'message' => 'Link ini sudah pernah digunakan. Silahkan minta link baru dari pemohon.',
                'reason' => 'already_used'
            ]);
        }

        // Check if token has expired
        if ($validationToken->expires_at->isPast()) {
            return view('approval.validation-expired', [
                'message' => 'Link sudah kadaluarsa. Silahkan minta link baru dari pemohon.',
                'reason' => 'expired'
            ]);
        }

        $featureRequest = $validationToken->featureRequest;

        return view('approval.validation-form', [
            'token' => $validationToken,
            'featureRequest' => $featureRequest,
            'code' => $code,
        ]);
    }

    /**
     * Process the validation form submission
     */
    public function submitValidation(Request $request, string $code)
    {
        $data = $request->validate([
            'sign_code' => 'required|string|min:4|max:20',
            'note' => 'nullable|string|max:500',
        ]);

        $validationToken = ApprovalValidationToken::where('short_code', $code)->first();

        if (!$validationToken) {
            return response()->json([
                'success' => false,
                'message' => 'Link tidak ditemukan atau sudah kadaluarsa.'
            ], 410);
        }

        // Check if token has been used
        if ($validationToken->isUsed()) {
            return response()->json([
                'success' => false,
                'message' => 'Link ini sudah pernah digunakan. Silahkan minta link baru dari pemohon.',
                'reason' => 'already_used'
            ], 410);
        }

        // Check if token has expired
        if ($validationToken->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Link sudah kadaluarsa. Silahkan minta link baru dari pemohon.',
                'reason' => 'expired'
            ], 410);
        }

        $result = $this->validationService->processApprovalValidation(
            $validationToken,
            $data['sign_code'],
            $data['note'] ?? null
        );

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 422);
    }

    /**
     * Show the success page after validation
     */
    public function showSuccessPage(string $code)
    {
        $validationToken = ApprovalValidationToken::where('short_code', $code)
            ->with(['featureRequest', 'approval.user'])
            ->first();

        if (!$validationToken) {
            return redirect('/')->with('error', 'Invalid success link');
        }

        if (!$validationToken->used_at) {
            return redirect('/')->with('error', 'Link belum digunakan');
        }

        $featureRequest = $validationToken->featureRequest;
        $approval = $validationToken->approval;

        return view('approval.validation-success', [
            'featureRequestId' => $featureRequest->id,
            'featureRequestTitle' => $featureRequest->title,
            'approvalUserName' => $approval->user->name,
            'approvalNote' => $approval->note,
            'approvalTime' => $approval->approved_at,
        ]);
    }
}
