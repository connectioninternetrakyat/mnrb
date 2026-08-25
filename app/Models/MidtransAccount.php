<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property bool $is_active
 * @property bool $is_production
 * @property string|null $merchant_id
 * @property string|null $server_key
 * @property string|null $client_key
 * @property Carbon|null $last_notified_at
 */
#[Fillable(['name', 'is_active', 'is_production', 'merchant_id', 'server_key', 'client_key'])]
#[Hidden(['server_key'])]
class MidtransAccount extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_notified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_production' => 'boolean',
            'server_key' => 'encrypted',
            'client_key' => 'encrypted',
        ];
    }
}
