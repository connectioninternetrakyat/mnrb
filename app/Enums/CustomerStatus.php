<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Active = 'aktif';
    case Suspended = 'ditangguhkan';

    /**
     * Get the display label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => __('Aktif'),
            self::Suspended => __('Ditangguhkan'),
        };
    }
}
