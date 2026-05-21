<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'cohort_id',
        'learner_id',
        'amount',
        'installment_number',
        'due_date',
        'paid_at',
        'status',
        'reference',
        'notes',
        'payment_method',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'paid_at'        => 'date',
        'status'         => PaymentStatus::class,
        'payment_method' => PaymentMethod::class,
        'amount'         => 'integer',
    ];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', PaymentStatus::EnAttente)
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopePaid($query)
    {
        return $query->where('status', PaymentStatus::Paye);
    }
}
