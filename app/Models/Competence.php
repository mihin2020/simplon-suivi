<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Competence extends Model
{
    use HasUuids;

    protected $fillable = ['competence_block_id', 'name', 'description', 'order'];

    public function block(): BelongsTo
    {
        return $this->belongsTo(CompetenceBlock::class, 'competence_block_id');
    }
}
