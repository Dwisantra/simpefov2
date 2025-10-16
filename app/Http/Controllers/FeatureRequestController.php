<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FeatureRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FeatureRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = FeatureRequest::with([
            'approvals.user:id,name,level',
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'requesterUnit:id,name,instansi,manager_category_id',
        ])
            ->withCount('comments')
            ->orderBy('created_at', 'desc');

        if ((int) $user->level === UserRole::USER->value) {
            $query->where('user_id', $user->id);
        }

        if ((int) $user->level === UserRole::MANAGER->value) {
            $managerCategoryId = (int) ($user->manager_category_id ?? 0);

            if ($managerCategoryId > 0) {
                $query->where(function ($builder) use ($managerCategoryId) {
                    $builder
                        ->where('manager_category_id', $managerCategoryId)
                        ->orWhere(function ($inner) use ($managerCategoryId) {
                            $inner
                                ->whereNull('manager_category_id')
                                ->whereHas('user.unit', function ($unitQuery) use ($managerCategoryId) {
                                    $unitQuery->where('manager_category_id', $managerCategoryId);
                                });
                        });
                });
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        $stage = strtolower((string) $request->query('stage', ''));

        if ($stage === 'submission') {
            $query->whereIn('status', ['pending', 'approved_manager', 'approved_a']);
        } elseif ($stage === 'development') {
            $query->whereIn('status', ['approved_b', 'done']);
        }

        $perPage = max(1, min((int) $request->input('per_page', 10), 50));

        return $query->paginate($perPage)->withQueryString();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_types' => 'required|array|min:1',
            'request_types.*' => 'in:new_feature,new_report,bug_fix',
            'description' => 'required|string',
            'sign_code' => 'required|string|min:4|max:20',
            'note' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();

        if (! $user->is_verified) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi oleh admin.'
            ], 403);
        }

        if (! $user->unit_id || ! $user->unit) {
            return response()->json([
                'message' => 'Profil Anda belum memiliki unit yang aktif.'
            ], 422);
        }

        if ((int) $user->level !== UserRole::USER->value) {
            return response()->json([
                'message' => 'Hanya pemohon yang dapat mengajukan ticket baru.'
            ], 403);
        }

        if (! $user->kode_sign) {
            return response()->json([
                'message' => 'Anda belum menyimpan kode ACC. Mohon atur kode Anda terlebih dahulu.'
            ], 422);
        }

        if (! Hash::check($data['sign_code'], $user->kode_sign)) {
            return response()->json([
                'message' => 'Kode ACC tidak sesuai.'
            ], 422);
        }

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $storedName = Str::uuid()->toString() . ($extension ? '.' . $extension : '');
            $attachmentPath = $file->storeAs('feature-requests', $storedName, 'local');
        }

        $requestTypes = array_values(array_unique($data['request_types']));

        $labels = [
            'new_feature' => 'Pembuatan Fitur Baru',
            'new_report' => 'Pembuatan Report/Cetakan',
            'bug_fix' => 'Lapor Bug/Error',
        ];

        $title = 'Pengajuan: ' . implode(', ', array_map(fn($type) => $labels[$type] ?? $type, $requestTypes));

        $feature = FeatureRequest::create([
            'user_id' => $user->id,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'development_status' => 1,
            'priority' => 'biasa',
            'request_types' => $requestTypes,
            'requester_unit_id' => $user->unit_id,
            'requester_instansi' => $user->instansi,
            'manager_category_id' => $user->unit?->manager_category_id,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        $feature->approvals()->create([
            'user_id' => $user->id,
            'role' => UserRole::USER->value,
            'sign_code' => Hash::make($data['sign_code']),
            'note' => $data['note'] ?? null,
            'approved_at' => now(),
        ]);

        $feature->load([
            'approvals.user:id,name,level',
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'requesterUnit:id,name,instansi,manager_category_id',
        ])->loadCount('comments');

        return response()->json($feature, 201);
    }

    public function show(Request $request, FeatureRequest $featureRequest)
    {
        $user = $request->user();

        if ((int) $user->level === UserRole::USER->value && $featureRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke ticket ini.'], 403);
        }

        return $featureRequest
            ->load([
                'approvals.user:id,name,level',
                'user:id,name,level,unit_id,instansi',
                'user.unit:id,name,instansi',
                'requesterUnit:id,name,instansi,manager_category_id',
                'comments.user:id,name,level'
            ])
            ->loadCount('comments');
    }

    public function downloadAttachment(Request $request, FeatureRequest $featureRequest)
    {
        if (! $featureRequest->attachment_path) {
            abort(404);
        }

        $user = $request->user();

        if ((int) $user->level === UserRole::USER->value && $featureRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk mengunduh berkas ini.'], 403);
        }

        if (! Storage::disk('local')->exists($featureRequest->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $featureRequest->attachment_path,
            $featureRequest->attachment_name ?? basename($featureRequest->attachment_path)
        );
    }

    public function update(Request $request, FeatureRequest $featureRequest)
    {
        $user = $request->user();

        if ((int) $user->level !== UserRole::ADMIN->value) {
            return response()->json([
                'message' => 'Hanya admin yang dapat memperbarui ticket.'
            ], 403);
        }

        $data = $request->validate([
            'priority' => ['sometimes', 'required', 'in:biasa,sedang,cito'],
            'development_status' => ['sometimes', 'required', 'integer', 'in:1,2,3,4'],
        ]);

        if (empty($data)) {
            return response()->json([
                'message' => 'Tidak ada perubahan yang dikirim.'
            ], 422);
        }

        $featureRequest->fill($data);
        $featureRequest->save();

        return $featureRequest->fresh([
            'approvals.user:id,name,level',
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'requesterUnit:id,name,instansi,manager_category_id',
            'comments.user:id,name,level'
        ])->loadCount('comments');
    }

    public function destroy(Request $request, FeatureRequest $featureRequest)
    {
        $user = $request->user();

        if ((int) $user->level !== UserRole::ADMIN->value) {
            return response()->json([
                'message' => 'Hanya admin yang dapat menghapus ticket.'
            ], 403);
        }

        $featureRequest->loadMissing('comments');

        if ($featureRequest->attachment_path) {
            Storage::disk('local')->delete($featureRequest->attachment_path);
        }

        foreach ($featureRequest->comments as $comment) {
            if ($comment->attachment_path) {
                Storage::disk('local')->delete($comment->attachment_path);
            }
        }

        $featureRequest->delete();

        return response()->json([
            'message' => 'Ticket berhasil dihapus.'
        ]);
    }
}
