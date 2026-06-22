<?php

namespace App\Models;

use App\Enums\PartnerCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'logo_path',
        'contact_first_name',
        'contact_last_name',
        'contact_email',
        'contact_phone',
        'contact_profile',
        'contact_position',
    ];

    protected $casts = [
        'category' => PartnerCategory::class,
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_partner');
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        if ($category) {
            $query->where('category', $category);
        }

        return $query;
    }
}
