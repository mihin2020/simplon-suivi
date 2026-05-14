<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected $casts = ['is_encrypted' => 'boolean'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->is_encrypted
            ? Crypt::decryptString($setting->value)
            : $setting->value;
    }

    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value'        => $encrypt ? Crypt::encryptString((string) $value) : (string) $value,
                'is_encrypted' => $encrypt,
            ],
        );
    }
}
