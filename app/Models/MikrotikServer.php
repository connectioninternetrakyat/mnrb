<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string|null $password
 * @property bool $use_tls
 * @property int $timeout
 * @property bool $is_connected
 * @property bool $is_default
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error
 */
#[Fillable(['name', 'host', 'port', 'username', 'password', 'use_tls', 'timeout', 'is_connected', 'is_default', 'last_error'])]
#[Hidden(['password'])]
class MikrotikServer extends Model
{
    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'port' => 8728,
        'timeout' => 5,
        'use_tls' => false,
        'is_connected' => false,
    ];

    /**
     * Get the default server, creating nothing when missing.
     */
    public static function default(): ?self
    {
        return static::query()->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'port' => 'integer',
            'timeout' => 'integer',
            'password' => 'encrypted',
            'use_tls' => 'boolean',
            'is_connected' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
