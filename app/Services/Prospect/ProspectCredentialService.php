<?php

namespace App\Services\Prospect;

use App\Models\Prospect;
use Illuminate\Support\Str;

/**
 * Builds PPP secret credentials for an approved prospect.
 *
 * Username : {kode_area}_{nama_lengkap}   (huruf & angka saja, lowercase)
 * Password : {nomor huruf pertama nama}{nama_lengkap} dengan A=1 B=2 C=3 .. Z=26
 *
 * Contoh: area "05", nama "Nadia Putri" -> username "05_nadiaputri",
 * password "14nadiaputri" karena N adalah huruf ke-14.
 */
class ProspectCredentialService
{
    /**
     * Build the username + password pair from the prospect data.
     *
     * @param  string  $areaCode  Kode area calon pelanggan, misal "05".
     * @param  string  $name  Nama lengkap calon pelanggan.
     * @return array{username: string, password: string}
     */
    public static function build(string $areaCode, string $name): array
    {
        $normalized = self::normalize($name);

        return [
            'username' => self::normalize($areaCode).'_'.$normalized,
            'password' => self::letterNumber($normalized).$normalized,
        ];
    }

    /**
     * Build credentials straight from a prospect model.
     *
     * @return array{username: string, password: string}
     */
    public static function forProspect(Prospect $prospect): array
    {
        return self::build(
            (string) $prospect->areaCode?->code,
            $prospect->name,
        );
    }

    /**
     * Map the first letter to its alphabet position (A=1 .. Z=26).
     */
    private static function letterNumber(string $normalized): int
    {
        $first = $normalized !== '' ? $normalized[0] : '';

        if (! preg_match('/[a-z]/', $first)) {
            throw new \RuntimeException(__('Nama harus diawali huruf untuk membuat password otomatis.'));
        }

        return ord($first) - ord('a') + 1;
    }

    /**
     * Keep letters and digits only, lowercased.
     */
    private static function normalize(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/[^a-z0-9]/', '')->toString();
    }
}
