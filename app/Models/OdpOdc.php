<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $code
 * @property string $name
 * @property int $area_code_id
 * @property string|null $region
 * @property int|null $port
 * @property string|null $splitter_ratio
 * @property string|null $maps_url
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $description
 */
#[Fillable(['code', 'name', 'area_code_id', 'region', 'port', 'splitter_ratio', 'maps_url', 'latitude', 'longitude', 'description'])]
class OdpOdc extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Get the area code this device belongs to.
     */
    public function areaCode(): BelongsTo
    {
        return $this->belongsTo(AreaCode::class);
    }
}
