<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgeRange extends Model
{
    protected $fillable = ['name', 'age_min', 'age_max', 'order'];

    protected $casts = [
        'age_min' => 'integer',
        'age_max' => 'integer',
        'order' => 'integer',
    ];

    public function learners(): HasMany
    {
        return $this->hasMany(Learner::class);
    }

    /**
     * Trouver la tranche d'âge correspondant à un âge donné
     */
    public static function findByAge(int $age): ?self
    {
        return self::where('age_min', '<=', $age)
            ->where('age_max', '>=', $age)
            ->first();
    }
}
