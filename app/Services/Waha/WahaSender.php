<?php

namespace App\Services\Waha;

use App\Models\WahaServer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends WhatsApp messages through the first connected WAHA server.
 */
class WahaSender
{
    /**
     * Send a WhatsApp text message to a phone number.
     *
     * @param  string  $phone  Nomor tujuan, format bebas (08/62/+62).
     */
    public function send(string $phone, string $message): bool
    {
        $server = WahaServer::query()
            ->where('is_connected', true)
            ->orderByDesc('last_checked_at')
            ->first();

        if ($server === null || $server->api_key === null || $server->api_key === '') {
            Log::warning('WAHA send skipped: no connected server.');

            return false;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['X-API-Key' => $server->api_key])
                ->post(rtrim($server->base_url, '/').'/api/sendText', [
                    'session' => $server->session,
                    'chatId' => $this->chatId($phone).'@c.us',
                    'text' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning('WAHA send failed.', [
                    'server' => $server->name,
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('WAHA send error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Normalize a phone number into the WAHA chat id part (628...).
     */
    private function chatId(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
