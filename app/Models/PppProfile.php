<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $mikrotik_server_id
 * @property string|null $mikrotik_id
 * @property string $name
 * @property string|null $local_address
 * @property string|null $remote_address
 * @property string|null $dns_server
 * @property string|null $rate_limit
 * @property string|null $comment
 */
#[Fillable(['mikrotik_server_id', 'mikrotik_id', 'name', 'local_address', 'remote_address', 'dns_server', 'rate_limit', 'comment'])]
class PppProfile extends Model
{
    /**
     * Get the Mikrotik server this profile lives on.
     */
    public function mikrotikServer(): BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }
}
