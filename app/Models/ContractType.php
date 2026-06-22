<?php

namespace App\Models;

use App\Enums\ContractTypeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'context',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'context' => ContractTypeContext::class,
        ];
    }

    public function scopeInternship(Builder $query): Builder
    {
        return $query->where('context', ContractTypeContext::Internship->value);
    }

    public function scopeEmployment(Builder $query): Builder
    {
        return $query->where('context', ContractTypeContext::Employment->value);
    }
}
