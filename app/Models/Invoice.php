<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $invoice_number
 * @property int $psb_ticket_id
 * @property string $payment_method
 * @property string|null $bank
 * @property string|null $va_number
 * @property int $total_exact
 * @property int $cash_total
 * @property string $status
 * @property Carbon|null $paid_at
 */
#[Fillable(['invoice_number', 'psb_ticket_id', 'payment_method', 'bank', 'va_number', 'monthly_price', 'period_days', 'active_days', 'prorata', 'otc_fee', 'promo_percent', 'discount', 'dpp', 'ppn', 'total_exact', 'cash_total', 'status', 'paid_at'])]
class Invoice extends Model
{
    /**
     * Virtual account prefixes per supported bank.
     */
    public const BANK_PREFIXES = [
        'bri' => '888',
        'bni' => '988',
        'bca' => '8077',
        'mandiri' => '895',
    ];

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
        ];
    }

    /**
     * Generate the next invoice number, e.g. INV-20260824-0001.
     */
    public static function generateNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');

        $count = static::query()
            ->where('invoice_number', 'like', "{$prefix}-%")
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    /**
     * Build the 16-digit virtual account number of a bank for the given
     * customer WhatsApp (08-format segment padded to fit).
     */
    public static function makeVaNumber(string $bank, ?string $whatsapp): ?string
    {
        $bank = strtolower($bank);

        if (! isset(self::BANK_PREFIXES[$bank])) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', (string) $whatsapp);

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        $prefix = self::BANK_PREFIXES[$bank];

        return $prefix.substr($digits.str_repeat('0', 16), 0, 16 - strlen($prefix));
    }

    /**
     * Mark the invoice as paid.
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
     * The PSB ticket this invoice bills.
     */
    public function psbTicket(): BelongsTo
    {
        return $this->belongsTo(PsbTicket::class);
    }
}
