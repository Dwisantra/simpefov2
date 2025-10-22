<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitlabIssueService
{
    protected ?string $baseUrl;
    protected ?string $token;
    protected $projectId;
    protected ?string $defaultLabels;
    protected ?string $bridgeUrl;

    public function __construct()
    {
        $baseUrl = rtrim((string) config('services.gitlab.url'), '/');
        $bridgeUrl = rtrim((string) config('services.gitlab.bridge_url'), '/');

        $this->baseUrl = $baseUrl !== '' ? $baseUrl : null;
        $this->token = config('services.gitlab.token');
        $this->projectId = config('services.gitlab.project_id');
        $this->defaultLabels = config('services.gitlab.labels');
        $this->bridgeUrl = $bridgeUrl !== '' ? $bridgeUrl : null;
    }

    public function isConfigured(): bool
    {
        if ($this->usesBridge()) {
            return ! empty($this->projectId);
        }

        return ! empty($this->baseUrl) && ! empty($this->token) && ! empty($this->projectId);
    }

    public function usesBridge(): bool
    {
        return ! empty($this->bridgeUrl);
    }

    protected function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integrasi GitLab belum dikonfigurasi.');
        }

        if ($this->usesBridge()) {
            return $this->bridgeClient();
        }

        return Http::withToken($this->token)
            ->acceptJson()
            ->baseUrl($this->baseUrl);
    }

    public function createIssue(array $payload): array
    {
        if ($this->defaultLabels && empty($payload['labels'])) {
            $payload['labels'] = $this->defaultLabels;
        }

        if ($this->usesBridge()) {
            $bridgePayload = $this->prepareBridgePayload($payload);
            $response = $this->bridgeClient()->post('/create-issue', $bridgePayload);
        } else {
            $response = $this->client()->post(
                sprintf('/api/v4/projects/%s/issues', urlencode($this->projectId)),
                $payload
            );
        }

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'membuat issue GitLab'));
        }

        return $response->json();
    }

    public function updateIssue(int $issueIid, array $payload): array
    {
        if ($this->usesBridge()) {
            throw new RuntimeException('Pembaruan issue GitLab tidak tersedia pada integrasi ini.');
        }

        $response = $this->client()->put(
            sprintf('/api/v4/projects/%s/issues/%s', urlencode($this->projectId), $issueIid),
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'memperbarui issue GitLab'));
        }

        return $response->json();
    }

    protected function errorMessage(?array $body, string $action): string
    {
        $message = $body['message'] ?? null;

        if (is_array($message)) {
            $message = collect($message)
                ->flatten()
                ->filter()
                ->implode(', ');
        }

        return $message ? sprintf('Gagal %s: %s', $action, $message) : sprintf('Gagal %s.', $action);
    }

    protected function bridgeClient(): PendingRequest
    {
        if (! $this->usesBridge()) {
            throw new RuntimeException('Integrasi bridge GitLab belum dikonfigurasi.');
        }

        return Http::acceptJson()->baseUrl($this->bridgeUrl);
    }

    protected function prepareBridgePayload(array $payload): array
    {
        $bridgePayload = $payload;

        if (empty($bridgePayload['project_id'])) {
            $bridgePayload['project_id'] = $this->projectId;
        }

        if (isset($bridgePayload['labels']) && is_array($bridgePayload['labels'])) {
            $bridgePayload['labels'] = implode(',', $bridgePayload['labels']);
        }

        if (isset($bridgePayload['assignee_ids'])) {
            $assigneeIds = array_values((array) $bridgePayload['assignee_ids']);
            $bridgePayload['assignee_id'] = Arr::first($assigneeIds);
            unset($bridgePayload['assignee_ids']);
        }

        if (isset($bridgePayload['project_id'])) {
            $bridgePayload['project_id'] = (int) $bridgePayload['project_id'];
        }

        if (array_key_exists('assignee_id', $bridgePayload) && $bridgePayload['assignee_id'] !== null) {
            $bridgePayload['assignee_id'] = (int) $bridgePayload['assignee_id'];
        }

        return Arr::only($bridgePayload, [
            'project_id',
            'title',
            'description',
            'labels',
            'assignee_id',
        ]);
    }
}
