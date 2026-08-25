<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string $base_url
 * @property string|null $username
 * @property string|null $password
 * @property bool $is_connected
 * @property bool $is_default
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error
 */
#[Fillable(['name', 'base_url', 'username', 'password', 'is_default', 'is_connected', 'last_error'])]
#[Hidden(['password'])]
class GenieacsServer extends Model
{
    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_connected' => false,
        'is_default' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'password' => 'encrypted',
            'is_connected' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the default server, returning null when missing.
     */
    public static function default(): ?self
    {
        return static::query()->orderByDesc('is_default')->orderBy('id')->first();
    }
}
