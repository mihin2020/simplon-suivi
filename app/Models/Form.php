<?php

namespace App\Models;

use App\Enums\FormStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'project_id',
        'formation_id',
        'created_by',
        'title',
        'description',
        'header_image_path',
        'header_image_original_name',
        'status',
        'public_token',
        'settings',
        'published_at',
        'closed_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'settings' => 'array',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FormStatus::Published);
    }

    public function acceptsResponses(): bool
    {
        if (! $this->status->acceptsResponses()) {
            return false;
        }

        if ($this->hasExpired()) {
            return false;
        }

        $settings = $this->settings ?? [];

        if (($settings['accept_responses'] ?? true) === false) {
            return false;
        }

        if (! empty($settings['max_responses'])) {
            $count = $this->responses()->count();
            if ($count >= (int) $settings['max_responses']) {
                return false;
            }
        }

        return true;
    }

    public function hasExpired(): bool
    {
        $closesAt = $this->settings['closes_at'] ?? null;

        if (empty($closesAt)) {
            return false;
        }

        return now()->greaterThan($closesAt);
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status === FormStatus::Draft || $this->status === FormStatus::Archived) {
            return false;
        }

        if ($this->trashed()) {
            return false;
        }

        return ! $this->hasExpired();
    }

    public function publicUrl(): string
    {
        return '/f/'.$this->public_token;
    }

    public function headerImageUrl(): ?string
    {
        if (! $this->header_image_path) {
            return null;
        }

        return '/storage/'.$this->header_image_path;
    }

    public function hasLongPublicToken(): bool
    {
        return strlen($this->public_token) > 10;
    }

    /**
     * Generate a unique short public token (8 chars, URL-safe alphanumeric).
     */
    public static function generatePublicToken(int $length = 8): string
    {
        do {
            $token = Str::lower(Str::random($length));
        } while (static::withTrashed()->where('public_token', $token)->exists());

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(): array
    {
        return [
            'accept_responses' => true,
            'one_per_email' => true,
            'closes_at' => null,
            'max_responses' => null,
            'confirmation_message' => '',
            'show_progress' => true,
            'collect_email' => true,
        ];
    }
}
