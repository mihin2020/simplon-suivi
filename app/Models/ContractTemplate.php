<?php

namespace App\Models;

use App\Enums\ContractTarget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'target',
        'body_html',
        'header_html',
        'footer_html',
        'manual_variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target' => ContractTarget::class,
            'manual_variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTarget(Builder $query, ?string $target): Builder
    {
        if ($target) {
            $query->where('target', $target);
        }

        return $query;
    }
}
