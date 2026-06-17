<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppMessage extends Model
{
    use SoftDeletes;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'phone',
        'recipient_name',
        'message',
        'direction',
        'provider',
        'status',
        'external_id',
        'error',
        'formation_name',
        'project_name',
        'learner_id',
        'broadcast_id',
        'read_at',
        'attachment_path',
        'attachment_mimetype',
        'attachment_filename',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
