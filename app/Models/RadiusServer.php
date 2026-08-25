<?php

namespace App\Models;

use App\Enums\RadiusDriver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string $server_host
 * @property int $auth_port
 * @property string|null $shared_secret
 * @property int $timeout
 * @property RadiusDriver $db_driver
 * @property string|null $db_host
 * @property int|null $db_port
 * @property string|null $db_database
 * @property string|null $db_username
 * @property string|null $db_password
 * @property bool $is_connected
 * @property bool $is_default
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error
 */
#[Fillable(['name', 'server_host', 'auth_port', 'shared_secret', 'timeout', 'db_driver', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'is_connected', 'is_default', 'last_error'])]
#[Hidden(['shared_secret', 'db_password'])]
class RadiusServer extends Model
{
    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'auth_port' => 1812,
        'timeout' => 5,
        'db_driver' => 'mysql',
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
            'auth_port' => 'integer',
            'timeout' => 'integer',
            'shared_secret' => 'encrypted',
            'db_driver' => RadiusDriver::class,
            'db_port' => 'integer',
            'db_password' => 'encrypted',
            'is_connected' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
