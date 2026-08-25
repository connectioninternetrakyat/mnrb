<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $ppp_profile_id
 * @property string $name
 * @property int $price
 * @property string|null $description
 */
#[Fillable(['name', 'ppp_profile_id', 'price', 'description'])]
class Package extends Model
{
    /**
     * Get the PPP profile this package bills against.
     */
    public function pppProfile(): BelongsTo
    {
        return $this->belongsTo(PppProfile::class);
    }
}
