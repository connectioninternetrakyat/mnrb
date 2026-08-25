<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property string|null $email
 * @property string $whatsapp
 * @property string $address
 * @property int $area_code_id
 * @property int $package_id
 * @property int|null $psb_ticket_id
 * @property int|null $ppp_secret_id
 * @property string $status
 * @property string|null $notes
 */
#[Fillable(['name', 'email', 'whatsapp', 'address', 'area_code_id', 'package_id', 'psb_ticket_id', 'ppp_secret_id', 'status', 'notes'])]
class Customer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
        ];
    }

    /**
     * The billing area of the customer.
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
     * The PPP secret linked to this customer, if provisioned.
     */
    public function pppSecret(): BelongsTo
    {
        return $this->belongsTo(PppSecret::class);
    }

    /**
     * The PSB ticket of the customer, when converted from a prospect.
     */
    public function psbTicket(): BelongsTo
    {
        return $this->belongsTo(PsbTicket::class);
    }
}
