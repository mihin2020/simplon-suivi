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
        'refunded_at',
        'refund_amount',
        'refund_motif',
        'refunded_by',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'paid_at'        => 'date',
        'refunded_at'    => 'datetime',
        'status'         => PaymentStatus::class,
        'payment_method' => PaymentMethod::class,
        'amount'         => 'integer',
        'refund_amount'  => 'integer',
    ];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
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
