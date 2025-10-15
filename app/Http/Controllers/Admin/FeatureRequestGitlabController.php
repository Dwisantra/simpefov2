<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\FeatureRequest;
use App\Services\GitlabIssueService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use RuntimeException;

class FeatureRequestGitlabController extends Controller
{
    public function sync(Request $request, FeatureRequest $featureRequest, GitlabIssueService $gitlab)
    {
        $this->ensureAdmin($request);

        if (! $gitlab->isConfigured()) {
            return response()->json([
                'message' => 'Integrasi GitLab belum diatur. Mohon lengkapi konfigurasi terlebih dahulu.'
            ], 503);
        }

        $data = $request->validate([
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer'],
            'state_event' => ['nullable', 'in:close,reopen'],
        ]);

        $payload = [
            'title' => $featureRequest->title,
            'description' => $this->buildDescription($featureRequest),
        ];

        if (! empty($data['labels'])) {
            $payload['labels'] = implode(',', $data['labels']);
        }

        if (! empty($data['assignee_ids'])) {
            $payload['assignee_ids'] = array_values($data['assignee_ids']);
        }

        if (! empty($data['state_event']) && $featureRequest->gitlab_issue_iid) {
            $payload['state_event'] = $data['state_event'];
        }

        try {
            if ($featureRequest->gitlab_issue_iid) {
                $issue = $gitlab->updateIssue($featureRequest->gitlab_issue_iid, $payload);
                $message = 'Issue GitLab berhasil diperbarui.';
            } else {
                $issue = $gitlab->createIssue($payload);
                $message = 'Issue GitLab baru berhasil dibuat.';
            }
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $featureRequest->fill([
            'gitlab_issue_id' => Arr::get($issue, 'id'),
            'gitlab_issue_iid' => Arr::get($issue, 'iid'),
            'gitlab_issue_url' => Arr::get($issue, 'web_url', $featureRequest->gitlab_issue_url),
            'gitlab_issue_state' => Arr::get($issue, 'state'),
            'gitlab_synced_at' => now(),
        ]);

        $featureRequest->save();
        $featureRequest->refresh()->load([
            'approvals.user:id,name,level',
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'comments.user:id,name,level',
        ])->loadCount('comments');

        return response()->json([
            'message' => $message,
            'issue' => [
                'id' => $featureRequest->gitlab_issue_id,
                'iid' => $featureRequest->gitlab_issue_iid,
                'url' => $featureRequest->gitlab_issue_url,
                'state' => $featureRequest->gitlab_issue_state,
                'synced_at' => $featureRequest->gitlab_synced_at,
            ],
            'feature' => $featureRequest,
        ]);
    }

    protected function ensureAdmin(Request $request): void
    {
        if (UserRole::tryFromMixed($request->user()->level ?? $request->user()->role)?->value !== UserRole::ADMIN->value) {
            abort(403, 'Hanya admin yang dapat mengelola integrasi GitLab.');
        }
    }

    protected function buildDescription(FeatureRequest $featureRequest): string
    {
        $requestTypes = collect($featureRequest->request_types ?? [])
            ->map(function ($type) {
                return match ($type) {
                    'new_feature' => 'Pembuatan Fitur Baru',
                    'new_report' => 'Pembuatan Report/Cetakan',
                    'bug_fix' => 'Lapor Bug/Error',
                    default => (string) $type,
                };
            })
            ->filter()
            ->implode(', ');

        $descriptionLines = [
            '### Informasi Permintaan',
            sprintf('- **ID Tiket:** %s', $featureRequest->id),
            sprintf('- **Judul:** %s', $featureRequest->title),
            sprintf('- **Jenis Permintaan:** %s', $requestTypes ?: 'Tidak ditentukan'),
            sprintf('- **Prioritas:** %s', $featureRequest->priority_label ?? ucfirst($featureRequest->priority ?? '-')),
            sprintf('- **Status:** %s', $featureRequest->status_label ?? ucfirst($featureRequest->status ?? '-')),
            sprintf('- **Status Pengembangan:** %s', $featureRequest->development_status_label ?? '-'),
            sprintf('- **Pemohon:** %s', $featureRequest->user?->name ?? '-'),
            sprintf('- **Unit:** %s', $featureRequest->requester_unit ?? $featureRequest->user?->unit?->name ?? '-'),
            sprintf('- **Instansi:** %s', $featureRequest->requester_instansi ?? $featureRequest->user?->instansi ?? '-'),
            '',
            '### Deskripsi Pengajuan',
            $featureRequest->description ?: '_Tidak ada deskripsi tambahan._',
        ];

        if ($featureRequest->attachment_name && $featureRequest->attachment_url) {
            $descriptionLines[] = '';
            $descriptionLines[] = sprintf('Lampiran: [%s](%s)', $featureRequest->attachment_name, $featureRequest->attachment_url);
        }

        $descriptionLines[] = '';
        $descriptionLines[] = sprintf('[Lihat tiket di SIMPEFO](%s)', url(sprintf('/feature-request/%s', $featureRequest->id)));

        return implode("\n", $descriptionLines);
    }
}
