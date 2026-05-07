<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerProfile extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function trainers(): HasMany
    {
        return $this->hasMany(Trainer::class, 'profile_id');
    }
}
