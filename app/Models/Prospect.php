<?php

namespace App\Models;

use App\Enums\ProspectStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string $email
 * @property string $whatsapp
 * @property string $address
 * @property int $area_code_id
 * @property int $package_id
 * @property int|null $user_id
 * @property string|null $maps_url
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $status
 * @property Carbon|null $approved_at
 * @property string|null $note
 */
#[Fillable(['name', 'email', 'whatsapp', 'address', 'area_code_id', 'package_id', 'user_id', 'maps_url', 'latitude', 'longitude', 'status', 'approved_at', 'note'])]
class Prospect extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProspectStatus::class,
            'approved_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Whether the prospect is still waiting for verification.
     */
    public function isPending(): bool
    {
        return $this->status === ProspectStatus::Pending;
    }

    /**
     * The area code this prospect belongs to.
     */
    public function areaCode(): BelongsTo
    {
        return $this->belongsTo(AreaCode::class);
    }

    /**
     * The package (and its PPP profile) the prospect subscribes to.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * The marketing / technician user who brought the prospect in.
     */
    public function broughtBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
