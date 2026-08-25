<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleMapsUrlResolver
{
    /**
     * Maximum number of redirects to follow when resolving short links.
     */
    private const MAX_REDIRECTS = 5;

    /**
     * Resolve a Google Maps URL (including maps.app.goo.gl short links)
     * into precise coordinates.
     *
     * @return array{latitude: float, longitude: float}
     */
    public function resolve(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException(__('URL Google Maps kosong.'));
        }

        $currentUrl = $url;
        $visited = [];

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (isset($visited[$currentUrl])) {
                break;
            }

            $visited[$currentUrl] = true;

            // Try to read the coordinates straight from the URL itself.
            if ($coordinates = $this->extract($currentUrl)) {
                return $coordinates;
            }

            // Short links (maps.app.goo.gl) answer with a redirect chain;
            // follow it and inspect each target URL.
            try {
                $response = Http::withoutRedirecting()
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; MNRB/1.0)',
                        'Accept' => 'text/html',
                    ])
                    ->timeout(10)
                    ->get($currentUrl);
            } catch (\Throwable $e) {
                throw new RuntimeException(__('Tidak dapat mengakses URL: :reason', ['reason' => $e->getMessage()]));
            }

            if (! $response->successful() && ! $response->redirect()) {
                throw new RuntimeException(__('Google Maps merespons dengan status :status.', ['status' => $response->status()]));
            }

            $location = (string) $response->header('Location');

            if ($response->redirect() && $location !== '') {
                $currentUrl = $this->absoluteUrl($location, $currentUrl);

                continue;
            }

            // Final page: search the HTML body for embedded coordinates
            // (meta tags, data attributes or inline scripts).
            if ($coordinates = $this->extract((string) $response->body())) {
                return $coordinates;
            }

            break;
        }

        throw new RuntimeException(__('Koordinat tidak ditemukan pada URL tersebut. Tempel URL dari menu "Bagikan" Google Maps.'));
    }

    /**
     * Extract latitude/longitude from any known Google Maps URL shape.
     *
     * Note: Google separates coordinates with a comma and often a literal
     * "+" (URL-encoded space) right before the longitude, e.g.
     * "/maps/search/-7.154157,+108.333915".
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function extract(string $subject): ?array
    {
        // A coordinate pair separated by comma/encoded-comma/plus,
        // tolerating an extra "+" before the longitude.
        $separator = '(?:%2C|,|\+)\+?';

        $patterns = [
            // Place page: .../@-7.154157,108.333915,17z
            '/@(-?\d{1,3}\.\d+)'.$separator.'(-?\d{1,3}\.\d+)/',
            // Search path: /maps/search/-7.154157,+108.333915
            '#maps/(?:search|place)/(-?\d{1,3}\.\d+)'.$separator.'(-?\d{1,3}\.\d+)#i',
            // Query param: q=-7.154157%2C108.333915 or ?query=-7.15,+108.33
            '/[?&](?:q|query|center|ll)=(-?\d{1,3}\.\d+)'.$separator.'(-?\d{1,3}\.\d+)/i',
            // Directions internals: !3d-7.154157!4d108.333915
            '/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/',
            // Generic coordinate pair anywhere in a URL-ish string.
            '/(?:^|[\/=,&])(-?\d{1,2}\.\d{4,})'.$separator.'(-?\d{1,3}\.\d{4,})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject, $matches) === 1) {
                $latitude = (float) $matches[1];
                $longitude = (float) $matches[2];

                if ($this->isValidCoordinatePair($latitude, $longitude)) {
                    return ['latitude' => $latitude, 'longitude' => $longitude];
                }
            }
        }

        return null;
    }

    /**
     * Turn a possibly relative Location header into an absolute URL.
     */
    private function absoluteUrl(string $location, string $baseUrl): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($baseUrl);
        $host = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $host.'/'.ltrim($location, '/');
    }

    /**
     * Ensure the pair is inside plausible geographic bounds.
     */
    private function isValidCoordinatePair(float $latitude, float $longitude): bool
    {
        return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
    }
}
