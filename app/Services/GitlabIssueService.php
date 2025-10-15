<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitlabIssueService
{
    protected ?string $baseUrl;
    protected ?string $token;
    protected $projectId;
    protected ?string $defaultLabels;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.gitlab.url'), '/');
        $this->token = config('services.gitlab.token');
        $this->projectId = config('services.gitlab.project_id');
        $this->defaultLabels = config('services.gitlab.labels');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->token) && ! empty($this->projectId);
    }

    protected function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integrasi GitLab belum dikonfigurasi.');
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

        $response = $this->client()->post(
            sprintf('/api/v4/projects/%s/issues', urlencode($this->projectId)),
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'membuat issue GitLab'));
        }

        return $response->json();
    }

    public function updateIssue(int $issueIid, array $payload): array
    {
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
}
