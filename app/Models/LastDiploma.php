<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LastDiploma extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'order'];

    public function learners(): HasMany
    {
        return $this->hasMany(Learner::class);
    }
}
