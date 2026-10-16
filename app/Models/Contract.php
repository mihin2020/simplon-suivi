<?php

namespace App\Models;

use App\Enums\ContractEmailStatus;
use App\Enums\ContractFont;
use App\Enums\ContractLineSpacing;
use App\Enums\ContractStatus;
use App\Enums\ContractTarget;
use App\Enums\FillFieldsStatus;
use App\Enums\LetterheadScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'reference',
        'contract_template_id',
        'contract_project_id',
        'target',
        'subject_type',
        'subject_id',
        'recipient_email',
        'emailed_at',
        'emailed_to',
        'email_count',
        'email_status',
        'title',
        'body_html',
        'signatories',
        'header_html',
        'footer_html',
        'header_height_mm',
        'footer_height_mm',
        'letterhead_scope',
        'letterhead_id',
        'font',
        'line_spacing',
        'data',
        'fill_fields',
        'fill_fields_status',
        'status',
        'version',
        'pdf_path',
        'signed_scan_path',
        'created_by',
        'finalized_at',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'target' => ContractTarget::class,
            'status' => ContractStatus::class,
            'data' => 'array',
            'signatories' => 'array',
            'fill_fields' => 'array',
            'fill_fields_status' => FillFieldsStatus::class,
            'version' => 'integer',
            'header_height_mm' => 'float',
            'footer_height_mm' => 'float',
            'letterhead_scope' => LetterheadScope::class,
            'font' => ContractFont::class,
            'line_spacing' => ContractLineSpacing::class,
            'finalized_at' => 'datetime',
            'emailed_at' => 'datetime',
            'email_count' => 'integer',
            'email_status' => ContractEmailStatus::class,
            'signed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function letterhead(): BelongsTo
    {
        return $this->belongsTo(Letterhead::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ContractProject::class, 'contract_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeTarget(Builder $query, ?string $target): Builder
    {
        if ($target) {
            $query->where('target', $target);
        }

        return $query;
    }

    /**
     * Free-text search on the reference, the title and the recipient's name.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('reference', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhereHasMorph('subject', [Trainer::class, Partner::class, User::class], function (Builder $subject, string $type) use ($like) {
                    match ($type) {
                        Trainer::class => $subject->whereHas('user', fn (Builder $u) => $u->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)),
                        Partner::class => $subject->where('name', 'like', $like),
                        default => $subject->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like),
                    };
                });
        });
    }

    /**
     * Filter on the email sending: "none" = never sent, else a ContractEmailStatus value.
     */
    public function scopeEmailStatus(Builder $query, ?string $status): Builder
    {
        return match (true) {
            $status === 'none' => $query->whereNull('email_status'),
            ContractEmailStatus::tryFrom((string) $status) !== null => $query->where('email_status', $status),
            default => $query,
        };
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }
}
