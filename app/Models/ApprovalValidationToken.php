<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ApprovalValidationToken extends Model
{
    protected $fillable = [
        'approval_id',
        'feature_request_id',
        'user_id',
        'token',
        'short_code',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function featureRequest()
    {
        return $this->belongsTo(FeatureRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if token is still valid (not expired and not yet used)
     */
    public function isValid(): bool
    {
        return !$this->used_at && $this->expires_at->isFuture();
    }

    /**
     * Check if token has been used
     */
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
