<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationLevel extends Model
{
    protected $fillable = ['name', 'order'];

    public function learners(): HasMany
    {
        return $this->hasMany(Learner::class);
    }
}
