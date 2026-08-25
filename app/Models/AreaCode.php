<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property string|null $region
 * @property string|null $description
 */
#[Fillable(['code', 'name', 'region', 'description'])]
class AreaCode extends Model
{
    /**
     * Get the ODP/ODC devices registered in this area.
     */
    public function odpOdcs(): HasMany
    {
        return $this->hasMany(OdpOdc::class);
    }
}
