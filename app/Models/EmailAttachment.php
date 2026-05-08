<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email_id',
        'filename',
        'path',
        'mime_type',
        'size',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
