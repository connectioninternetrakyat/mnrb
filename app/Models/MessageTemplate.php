<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $slug
 * @property string $name
 * @property string $body
 * @property string|null $variables_hint
 */
#[Fillable(['slug', 'name', 'body', 'variables_hint'])]
class MessageTemplate extends Model
{
    public const SLUGS = ['psb', 'pembayaran', 'gangguan', 'isolir', 'selesai'];

    /**
     * Render a template body by replacing {{variables}}.
     *
     * @param  array<string, string|int|float|null>  $vars
     */
    public static function render(string $body, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $matches): string => (string) ($vars[strtolower($matches[1])] ?? ''),
            $body,
        );
    }

    /**
     * Fetch a template body by slug with a graceful fallback.
     */
    public static function bodyFor(string $slug, string $fallback): string
    {
        $body = static::query()->where('slug', $slug)->value('body');

        return is_string($body) && $body !== '' ? $body : $fallback;
    }
}
