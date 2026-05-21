<?php

namespace App\Models;

use App\Enums\CampusFormationMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CampusFormation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'duration_months',
        'mode',
        'total_cost',
        'is_active',
    ];

    protected $casts = [
        'mode'       => CampusFormationMode::class,
        'is_active'  => 'boolean',
        'total_cost' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $formation) {
            if (empty($formation->slug)) {
                $formation->slug = Str::slug($formation->name);
            }
        });
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    public function activeCohorts(): HasMany
    {
        return $this->cohorts()->where('status', 'en_cours');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
