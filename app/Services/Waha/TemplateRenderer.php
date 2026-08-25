<?php

namespace App\Services\Waha;

use App\Models\MessageTemplate;
use App\Models\Pelanggan;

/**
 * Builds customer variables and renders WhatsApp template bodies.
 *
 * Every variable comes from real platform data: the pelanggan record,
 * its package/area relations, tickets and billing rows.
 */
class TemplateRenderer
{
    /**
     * Base variables available for every message of a pelanggan.
     *
     * @return array<string, string>
     */
    public static function varsFor(Pelanggan $pelanggan): array
    {
        return [
            'nama' => $pelanggan->name,
            'whatsapp' => $pelanggan->whatsapp,
            'alamat' => $pelanggan->address,
            'area' => $pelanggan->areaCode?->code !== null
                ? trim($pelanggan->areaCode?->code.' - '.$pelanggan->areaCode?->name)
                : '',
            'paket' => $pelanggan->package?->name ?? '',
            'harga' => 'Rp '.number_format((float) ($pelanggan->package?->price ?? 0), 0, ',', '.'),
            'username' => $pelanggan->pppSecret?->name ?? '',
        ];
    }

    /**
     * Render a body with {{variables}} replaced.
     *
     * @param  array<string, string|int|float|null>  $vars
     */
    public static function render(string $body, array $vars): string
    {
        return MessageTemplate::render($body, $vars);
    }

    /**
     * Convenience: render the stored template for a slug, falling back to
     * the provided default text when the template is missing.
     *
     * @param  array<string, string|int|float|null>  $vars
     */
    public static function renderSlug(string $slug, string $fallback, array $vars): string
    {
        return static::render(MessageTemplate::bodyFor($slug, $fallback), $vars);
    }
}
