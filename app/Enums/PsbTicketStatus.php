<?php

namespace App\Enums;

enum PsbTicketStatus: string
{
    case Scheduled = 'terjadwal';
    case InProgress = 'dikerjakan';
    case Completed = 'selesai';
    case Cancelled = 'dibatalkan';

    /**
     * Get the display label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Terjadwal'),
            self::InProgress => __('Dikerjakan'),
            self::Completed => __('Selesai'),
            self::Cancelled => __('Dibatalkan'),
        };
    }
}
