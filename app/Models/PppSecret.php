<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $mikrotik_server_id
 * @property string|null $mikrotik_id
 * @property string $name
 * @property string $password
 * @property string $profile
 * @property bool $disabled_on_mikrotik
 * @property string|null $comment
 */
#[Fillable(['mikrotik_server_id', 'mikrotik_id', 'name', 'password', 'profile', 'disabled_on_mikrotik', 'comment'])]
#[Hidden(['password'])]
class PppSecret extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'disabled_on_mikrotik' => 'boolean',
        ];
    }

    /**
     * Get the Mikrotik server this secret lives on.
     */
    public function mikrotikServer(): BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }
}
