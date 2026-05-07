<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetenceBlock extends Model
{
    use HasUuids;

    protected $fillable = ['referentiel_id', 'name', 'description', 'order'];

    public function referentiel(): BelongsTo
    {
        return $this->belongsTo(Referentiel::class);
    }

    public function competences(): HasMany
    {
        return $this->hasMany(Competence::class)->orderBy('order');
    }
}
