<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'initial_password',
        'level',
        'manager_category_id',
        'kode_sign',
        'phone',
        'instansi',
        'unit_id',
        'verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'kode_sign',
        'initial_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'verified_at' => 'datetime',
            'level' => 'integer',
            'manager_category_id' => 'integer',
        ];
    }

    protected $appends = [
        'role',
        'role_label',
        'has_kode_sign',
        'is_verified',
        'manager_category_label',
    ];

    public function getRoleAttribute(): ?int
    {
        return is_null($this->level) ? null : (int) $this->level;
    }

    public function getRoleLabelAttribute(): string
    {
        $role = UserRole::tryFromMixed($this->level);

        return $role?->label() ?? '-';
    }

    public function getHasKodeSignAttribute(): bool
    {
        return ! empty($this->kode_sign);
    }

    public function getIsVerifiedAttribute(): bool
    {
        return ! is_null($this->verified_at);
    }

    public function getManagerCategoryLabelAttribute(): ?string
    {
        $category = \App\Enums\ManagerCategory::tryFromMixed($this->manager_category_id);

        return $category?->label();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
