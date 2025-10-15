<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FeatureRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class GitlabWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if (! $this->tokenIsValid($request)) {
            abort(401, 'Token webhook GitLab tidak valid.');
        }

        $event = $request->header('X-Gitlab-Event', '');

        if (! in_array($event, ['Issue Hook', 'Confidential Issue Hook'], true)) {
            return response()->json([
                'message' => 'Event diabaikan.'
            ], 202);
        }

        $payload = $request->all();
        $attributes = Arr::get($payload, 'object_attributes', []);

        $issueId = Arr::get($attributes, 'id');
        $issueIid = Arr::get($attributes, 'iid');

        if (! $issueId || ! $issueIid) {
            return response()->json([
                'message' => 'Payload issue tidak lengkap.'
            ], 422);
        }

        $configuredProjectId = (string) config('services.gitlab.project_id');
        $payloadProjectId = (string) Arr::get($payload, 'project.id');

        if ($configuredProjectId && $payloadProjectId && $configuredProjectId !== $payloadProjectId) {
            return response()->json([
                'message' => 'Issue berasal dari project yang tidak dipantau.'
            ], 202);
        }

        $featureRequest = FeatureRequest::query()
            ->where('gitlab_issue_id', $issueId)
            ->orWhere('gitlab_issue_iid', $issueIid)
            ->first();

        $message = 'Issue GitLab diperbarui.';

        if (! $featureRequest) {
            $user = $this->resolveDefaultUser();

            if (! $user) {
                return response()->json([
                    'message' => 'Tidak ada pengguna default untuk menampung issue GitLab.'
                ], 503);
            }

            $featureRequest = new FeatureRequest([
                'user_id' => $user->id,
                'status' => $this->mapIssueStateToStatus(Arr::get($attributes, 'state')) ?? 'pending',
                'development_status' => $this->mapIssueStateToDevelopmentStatus(Arr::get($attributes, 'state')) ?? 1,
                'priority' => 'biasa',
                'request_types' => ['gitlab_issue'],
                'requester_unit' => $user->unit?->name,
                'requester_instansi' => $user->instansi,
            ]);

            $message = 'Issue GitLab baru ditautkan.';
        }

        $title = Arr::get($attributes, 'title') ?? sprintf('Issue GitLab #%s', $issueIid);
        $description = Arr::get($attributes, 'description');
        $issueUrl = Arr::get($attributes, 'url') ?? Arr::get($attributes, 'web_url');
        $issueState = Arr::get($attributes, 'state');

        $featureRequest->fill(array_filter([
            'title' => $title,
            'description' => $description,
            'gitlab_issue_id' => $issueId,
            'gitlab_issue_iid' => $issueIid,
            'gitlab_issue_url' => $issueUrl,
            'gitlab_issue_state' => $issueState,
            'gitlab_synced_at' => now(),
        ], static fn ($value) => ! is_null($value)));

        if ($status = $this->mapIssueStateToStatus($issueState)) {
            $featureRequest->status = $status;
        }

        if ($developmentStatus = $this->mapIssueStateToDevelopmentStatus($issueState)) {
            $featureRequest->development_status = $developmentStatus;
        }

        $featureRequest->save();

        return response()->json([
            'message' => $message,
        ]);
    }

    protected function tokenIsValid(Request $request): bool
    {
        $secret = config('services.gitlab.webhook_secret');

        if (! $secret) {
            return true;
        }

        $provided = $request->header('X-Gitlab-Token');

        return is_string($provided) && hash_equals($secret, $provided);
    }

    protected function resolveDefaultUser(): ?User
    {
        $email = config('services.gitlab.default_requester_email');

        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        return User::where('level', UserRole::ADMIN->value)->orderBy('id')->first();
    }

    protected function mapIssueStateToStatus(?string $state): ?string
    {
        return match ($state) {
            'closed' => 'done',
            default => null,
        };
    }

    protected function mapIssueStateToDevelopmentStatus(?string $state): ?int
    {
        return match ($state) {
            'closed' => 4,
            'locked' => 3,
            'opened', 'reopened' => 2,
            default => null,
        };
    }
}
