<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row holding the billing configuration of the panel.
 *
 * @property int $prorate_cut_day
 * @property int $invoice_day
 * @property int|null $isolir_day
 * @property string $isolir_hour
 * @property float $ppn_percent
 * @property int $promo_percent
 * @property bool $remind_h3
 * @property bool $remind_h2
 * @property bool $remind_h1
 */
#[Fillable(['prorate_cut_day', 'invoice_day', 'isolir_day', 'isolir_hour', 'isolir_profile', 'ppn_percent', 'promo_percent', 'remind_h3', 'remind_h2', 'remind_h1', 'last_generated_period'])]
class BillingSetting extends Model
{
    /**
     * The single settings row, created with defaults when missing.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'prorate_cut_day' => 10,
            'invoice_day' => 11,
            'isolir_day' => null,
            'isolir_hour' => '23:59',
            'ppn_percent' => 11,
            'promo_percent' => 0,
            'remind_h3' => true,
            'remind_h2' => true,
            'remind_h1' => true,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ppn_percent' => 'float',
            'remind_h3' => 'boolean',
            'remind_h2' => 'boolean',
            'remind_h1' => 'boolean',
        ];
    }
}
