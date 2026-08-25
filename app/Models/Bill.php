<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $bill_number
 * @property int $psb_ticket_id
 * @property string $period
 * @property int $monthly_price
 * @property int $dpp
 * @property int $ppn
 * @property int $total
 * @property string $status
 * @property Carbon|null $paid_at
 */
#[Fillable(['bill_number', 'psb_ticket_id', 'period', 'monthly_price', 'dpp', 'ppn', 'total', 'status', 'paid_at', 'reminder_sent_at'])]
class Bill extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * Generate the next bill number for a period, e.g. TAG-202608-0001.
     */
    public static function generateNumber(string $period): string
    {
        $prefix = 'TAG-'.str_replace('-', '', $period);

        $count = static::query()
            ->where('bill_number', 'like', "{$prefix}-%")
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    /**
     * Mark the bill as paid.
     */
    public function markAsPaid(): void
    {
        if ($this->status === InvoiceStatus::Paid) {
            return;
        }

        $this->update([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => now(),
        ]);
    }

    /**
     * The PSB ticket (customer) this monthly bill belongs to.
     */
    public function psbTicket(): BelongsTo
    {
        return $this->belongsTo(PsbTicket::class);
    }
}
