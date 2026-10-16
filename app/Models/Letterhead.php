<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letterhead extends Model
{
    use HasUuids, SoftDeletes;

    public const PAGE_WIDTH_MM = 210;

    /** Tallest allowed bands, so the contract body always keeps room on the page. */
    public const MAX_HEADER_HEIGHT_MM = 80;

    public const MAX_FOOTER_HEIGHT_MM = 60;

    protected $fillable = [
        'name',
        'header_image_path',
        'header_height_mm',
        'footer_image_path',
        'footer_height_mm',
    ];

    protected function casts(): array
    {
        return [
            'header_height_mm' => 'float',
            'footer_height_mm' => 'float',
        ];
    }
}
