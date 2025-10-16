<?php

namespace App\Models;

use App\Enums\ManagerCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'instansi',
        'is_active',
        'manager_category_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'manager_category_id' => 'integer',
    ];

    protected $appends = [
        'manager_category_label',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function getManagerCategoryLabelAttribute(): ?string
    {
        $category = ManagerCategory::tryFromMixed($this->manager_category_id);

        return $category?->label();
    }
}
