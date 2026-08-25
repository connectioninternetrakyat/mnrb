<?php

namespace App\Models;

use App\Enums\PelangganStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string|null $email
 * @property string $whatsapp
 * @property string $address
 * @property string|null $maps_url
 * @property int $area_code_id
 * @property int $package_id
 * @property int|null $ppp_secret_id
 * @property int|null $psb_ticket_id
 * @property string $status
 * @property Carbon|null $joined_at
 * @property Carbon|null $activated_at
 * @property string|null $notes
 */
#[Fillable(['name', 'email', 'whatsapp', 'address', 'maps_url', 'area_code_id', 'package_id', 'ppp_secret_id', 'psb_ticket_id', 'status', 'joined_at', 'activated_at', 'notes'])]
class Pelanggan extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PelangganStatus::class,
            'joined_at' => 'date',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * The FTTH area of the customer.
     */
    public function areaCode(): BelongsTo
    {
        return $this->belongsTo(AreaCode::class);
    }

    /**
     * The subscribed package.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * The PPP account of the customer, when provisioned.
     */
    public function pppSecret(): BelongsTo
    {
        return $this->belongsTo(PppSecret::class);
    }

    /**
     * The PSB ticket that activated this customer.
     */
    public function psbTicket(): BelongsTo
    {
        return $this->belongsTo(PsbTicket::class);
    }
}
