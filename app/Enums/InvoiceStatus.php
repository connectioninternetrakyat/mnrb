<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'menunggu_pembayaran';
    case Paid = 'lunas';

    /**
     * Get the display label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Menunggu Pembayaran'),
            self::Paid => __('Lunas'),
        };
    }
}
