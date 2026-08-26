<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value', 'updated_by'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, string $value, ?int $updatedBy = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $updatedBy]);
    }
}
