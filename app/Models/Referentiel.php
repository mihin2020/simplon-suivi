<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referentiel extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description'];

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CompetenceBlock::class)->orderBy('order');
    }
}
