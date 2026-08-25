<?php

namespace App\Enums;

enum PelangganStatus: string
{
    case Active = 'aktif';
    case Stopped = 'berhenti';

    /**
     * Get the display label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => __('Aktif'),
            self::Stopped => __('Berhenti'),
        };
    }
}
