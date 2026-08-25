<?php

namespace App\Enums;

enum ProspectStatus: string
{
    case Pending = 'menunggu_verifikasi';
    case Approved = 'disetujui';
    case Rejected = 'ditolak';

    /**
     * Get the display label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Menunggu Verifikasi'),
            self::Approved => __('Disetujui'),
            self::Rejected => __('Ditolak'),
        };
    }
}
