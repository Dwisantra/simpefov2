<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature_request_id',
        'user_id',
        'role',
        'sign_code',
        'note',
        'approved_at'
    ];

    protected $hidden = ['sign_code'];

    protected $casts = [
        'role' => 'integer',
    ];

    public function featureRequest()
    {
        return $this->belongsTo(FeatureRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
