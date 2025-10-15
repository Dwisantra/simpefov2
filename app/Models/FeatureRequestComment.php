<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureRequestComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature_request_id',
        'user_id',
        'comment',
        'attachment_path',
        'attachment_name',
    ];

    protected $hidden = ['attachment_path'];

    protected $appends = ['attachment_url'];

    public function featureRequest()
    {
        return $this->belongsTo(FeatureRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return url("/api/feature-requests/{$this->feature_request_id}/comments/{$this->id}/attachment");
    }
}
