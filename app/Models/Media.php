<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'medias';

    protected $fillable = [
        'formation_id',
        'title',
        'description',
        'type',
        'album',
        'cloudinary_public_id',
        'url',
        'thumbnail_url',
        'file_size',
        'width',
        'height',
        'duration',
        'format',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
    ];

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scope pour filtrer par album
    public function scopeInAlbum($query, ?string $album)
    {
        if ($album === null || $album === 'all') {
            return $query;
        }
        return $query->where('album', $album);
    }

    // Scope pour regrouper les albums d'une formation
    public function scopeAlbums($query)
    {
        return $query->select('album')
            ->distinct()
            ->whereNotNull('album')
            ->orderBy('album');
    }

    // Formatter la taille en KB/MB/GB
    public function getFormattedSizeAttribute(): string
    {
        $size = $this->file_size;
        
        if ($size >= 1048576) {
            return round($size / 1048576, 2) . ' GB';
        } elseif ($size >= 1024) {
            return round($size / 1024, 2) . ' MB';
        }
        
        return $size . ' KB';
    }

    // Durée formatée pour vidéos
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
