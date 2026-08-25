<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string $base_url
 * @property string|null $api_key
 * @property string $session
 * @property bool $is_connected
 * @property Carbon|null $last_webhook_at
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error
 */
#[Fillable(['name', 'base_url', 'api_key', 'session', 'is_connected', 'last_error'])]
#[Hidden(['api_key'])]
class WahaServer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_webhook_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'api_key' => 'encrypted',
            'is_connected' => 'boolean',
        ];
    }
}
