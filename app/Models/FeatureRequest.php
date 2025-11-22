<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureRequest extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const DEVELOPMENT_STATUS_ANALYSIS = 1;
    public const DEVELOPMENT_STATUS_PENGERJAAN = 2;
    public const DEVELOPMENT_STATUS_TESTING = 3;
    public const DEVELOPMENT_STATUS_READY_RELEASE = 4;

    public const DEVELOPMENT_STATUS_LABELS = [
        self::DEVELOPMENT_STATUS_ANALYSIS => 'Analisis',
        self::DEVELOPMENT_STATUS_PENGERJAAN => 'Pengerjaan',
        self::DEVELOPMENT_STATUS_TESTING => 'Testing',
        self::DEVELOPMENT_STATUS_READY_RELEASE => 'Ready Release',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'development_status',
        'release_date',
        'release_status',
        'release_set_by',
        'priority',
        'request_types',
        'requester_unit_id',
        'requester_instansi',
        'manager_category_id',
        'attachment_path',
        'attachment_name',
        'gitlab_issue_id',
        'gitlab_issue_iid',
        'gitlab_issue_url',
        'gitlab_issue_state',
        'gitlab_synced_at',
    ];

    protected $hidden = ['attachment_path'];

    protected $appends = [
        'status_label',
        'status_progress',
        'attachment_url',
        'current_stage_role',
        'current_stage_role_label',
        'request_types_label',
        'priority_label',
        'development_status_label',
        'workflow_stage',
        'workflow_stage_label',
        'requester_unit_name',
        'requires_director_a_approval',
    ];

    protected $casts = [
        'request_types' => 'array',
        'gitlab_synced_at' => 'datetime',
        'development_status' => 'integer',
        'manager_category_id' => 'integer',
        'requester_unit_id' => 'integer',
        'release_status' => 'integer',
        'release_date' => 'date',
    ];

    public function approvals()
    {
        return $this->hasMany(Approval::class)->orderBy('approved_at');
    }

    public function comments()
    {
        return $this->hasMany(FeatureRequestComment::class)->latest();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requesterUnit()
    {
        return $this->belongsTo(Unit::class, 'requester_unit_id');
    }

    public function releaseSetter()
    {
        return $this->belongsTo(User::class, 'release_set_by');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function getStatusLabelAttribute(): string
    {
        $requiresDirectorA = $this->requires_director_a_approval;

        return match ($this->status) {
            'pending' => 'Menunggu ACC Manager',
            'approved_manager' => $requiresDirectorA
                ? 'Menunggu Direktur RS Raffa Majenang'
                : 'Menunggu Direktur RS Wiradadi Husada',
            'approved_a' => 'Menunggu Direktur RS Wiradadi Husada',
            'approved_b', 'done' => 'Selesai',
            default => ucfirst($this->status ?? 'Tidak diketahui'),
        };
    }

    public function getStatusProgressAttribute(): int
    {
        $requiresDirectorA = $this->requires_director_a_approval;

        $map = $requiresDirectorA
            ? [
                'pending' => 1,
                'approved_manager' => 2,
                'approved_a' => 3,
                'approved_b' => 4,
                'done' => 4,
            ]
            : [
                'pending' => 1,
                'approved_manager' => 2,
                'approved_a' => 2,
                'approved_b' => 3,
                'done' => 3,
            ];

        return $map[$this->status] ?? 0;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return url("/api/feature-requests/{$this->id}/attachment");
    }

    public function getCurrentStageRoleAttribute(): ?int
    {
        return match ($this->status) {
            'pending' => UserRole::MANAGER->value,
            'approved_manager' => UserRole::DIRECTOR_A->value,
            'approved_a' => UserRole::DIRECTOR_B->value,
            default => null,
        };
    }

    public function getCurrentStageRoleLabelAttribute(): ?string
    {
        $role = UserRole::tryFromMixed($this->current_stage_role);

        return $role?->label();
    }

    public function getRequestTypesLabelAttribute(): string
    {
        if (empty($this->request_types)) {
            return $this->title ?? '-';
        }

        $labels = [
            'new_feature' => 'Pembuatan Fitur Baru',
            'new_report' => 'Pembuatan Report/Cetakan',
            'bug_fix' => 'Lapor Bug/Error',
            'gitlab_issue' => 'Issue dari GitLab',
        ];

        $types = is_array($this->request_types) ? $this->request_types : [];

        $resolved = array_map(fn($key) => $labels[$key] ?? $key, $types);
        $resolved = array_filter($resolved);

        return implode(', ', $resolved);
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'cito' => 'Prioritas Cito',
            'sedang' => 'Prioritas Sedang',
            default => 'Prioritas Biasa',
        };
    }

    public function getDevelopmentStatusLabelAttribute(): ?string
    {
        if ($this->development_status === null) {
            return null;
        }

        return self::DEVELOPMENT_STATUS_LABELS[$this->development_status] ?? null;
    }

    public function getWorkflowStageAttribute(): string
    {
        return in_array($this->status, ['approved_b', 'done'], true)
            ? 'development'
            : 'submission';
    }

    public function getWorkflowStageLabelAttribute(): string
    {
        return $this->workflow_stage === 'development'
            ? 'Tahap Pengerjaan'
            : 'Tahap Pengajuan';
    }

    public function getRequesterUnitNameAttribute(): ?string
    {
        return $this->requesterUnit?->name ?? $this->user?->unit?->name;
    }

    public function getRequiresDirectorAApprovalAttribute(): bool
    {
        $instansi = $this->requester_instansi ?? $this->user?->instansi;

        if ($instansi === 'wiradadi' && config('feature-requests.skip_raffa_director_for_wiradadi')) {
            return false;
        }

        return true;
    }
}
