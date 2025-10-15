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

    public const DEVELOPMENT_STATUS_LABELS = [
        1 => 'Analisis',
        2 => 'Pengerjaan',
        3 => 'Testing',
        4 => 'Ready Release',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'development_status',
        'priority',
        'request_types',
        'requester_unit',
        'requester_instansi',
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
    ];

    protected $casts = [
        'request_types' => 'array',
        'gitlab_synced_at' => 'datetime',
        'development_status' => 'integer',
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

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->requester_instansi === 'wiradadi' && $this->status === 'approved_manager') {
            return 'Menunggu Direktur RS Wiradadi Husada';
        }

        return match ($this->status) {
            'pending' => 'Menunggu ACC Manager',
            'approved_manager' => 'Menunggu Direktur RS Raffa Majenang',
            'approved_a' => 'Menunggu Direktur RS Wiradadi Husada',
            'approved_b', 'done' => 'Selesai',
            default => ucfirst($this->status ?? 'Tidak diketahui'),
        };
    }

    public function getStatusProgressAttribute(): int
    {
        if ($this->requester_instansi === 'wiradadi') {
            return match ($this->status) {
                'pending' => 1,
                'approved_manager', 'approved_a' => 2,
                'approved_b', 'done' => 3,
                default => 0,
            };
        }

        $map = [
            'pending' => 1,
            'approved_manager' => 2,
            'approved_a' => 3,
            'approved_b' => 4,
            'done' => 4,
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
        if ($this->requester_instansi === 'wiradadi' && $this->status === 'approved_manager') {
            return UserRole::DIRECTOR_B->value;
        }

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
}
