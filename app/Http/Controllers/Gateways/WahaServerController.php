<?php

namespace App\Http\Controllers\Gateways;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gateways\WahaServerRequest;
use App\Models\WahaServer;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WahaServerController extends Controller
{
    /**
     * Display the WAHA servers list.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::GatewaysView->value);

        return Inertia::render('gateways/waha', [
            'servers' => $this->servers(),
            'webhook_url' => $this->webhookUrl(),
        ]);
    }

    /**
     * Store a newly created WAHA server.
     */
    public function store(WahaServerRequest $request): RedirectResponse
    {
        WahaServer::create($this->credentials($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server WAHA berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given WAHA server.
     */
    public function update(WahaServerRequest $request, WahaServer $server): RedirectResponse
    {
        $server->update($this->credentials($request, $server->api_key));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server WAHA berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given WAHA server.
     */
    public function destroy(WahaServer $server): RedirectResponse
    {
        $server->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server WAHA berhasil dihapus.')]);

        return back();
    }

    /**
     * Start the session (when needed) and return the login QR code plus the
     * current session status, straight from the WAHA server.
     */
    public function qr(WahaServer $server): JsonResponse
    {
        Gate::authorize(UserPermission::GatewaysUpdate->value);

        $base = rtrim($server->base_url, '/');
        $headers = ['X-API-Key' => (string) $server->api_key];
        $session = (string) $server->session;

        try {
            // Status first: an already-running session must never pay the
            // cost of booting it again on every poll.
            $statusResponse = $this->sessionStatus($base, $headers, $session);
            $status = $this->wahaStatus($statusResponse);

            // Boot the session only when WAHA does not know it yet or it
            // died in a state that requires an explicit start.
            if ($statusResponse->status() === 404 || in_array($status, ['', 'FAILED', 'STOPPED'], true)) {
                $this->startSession($base, $headers, $session);

                sleep(1); // Give the engine a moment before re-reading.

                $statusResponse = $this->sessionStatus($base, $headers, $session);
                $status = $this->wahaStatus($statusResponse);
            }

            if ($status === 'WORKING') {
                $server->update([
                    'is_connected' => true,
                    'last_checked_at' => now(),
                    'last_error' => null,
                ]);

                return response()->json(['qr' => null, 'status' => 'WORKING']);
            }

            // Try both known endpoints across WAHA versions.
            $qrDataUri = null;

            foreach ([
                '/api/'.$session.'/auth/qr?format=image',
                '/api/'.$session.'/auth/qr',
                '/api/'.$session.'/qr',
            ] as $path) {
                $qrResponse = Http::timeout(8)->connectTimeout(3)
                    ->withHeaders($headers)
                    ->get($base.$path);

                if (! $qrResponse->successful()) {
                    continue;
                }

                $contentType = (string) $qrResponse->header('Content-Type');
                $rawBody = (string) $qrResponse->body();

                if (str_contains($contentType, 'image')) {
                    $qrDataUri = 'data:image/png;base64,'.base64_encode($rawBody);

                    break;
                }

                // JSON variants: data / qr / url field.
                $jsonQr = (string) ($qrResponse->json('data')
                    ?? $qrResponse->json('qr')
                    ?? '');

                if ($jsonQr !== '') {
                    $qrDataUri = str_starts_with($jsonQr, 'data:')
                        ? $jsonQr
                        : 'data:image/png;base64,'.$jsonQr;

                    break;
                }
            }

            if ($qrDataUri === null) {
                return response()->json([
                    'qr' => null,
                    'status' => $status !== '' ? $status : 'SCAN_QR_CODE',
                    'error' => __('QR belum tersedia, mencoba lagi...'),
                ]);
            }

            return response()->json([
                'qr' => $qrDataUri,
                'status' => $status !== '' ? $status : 'SCAN_QR_CODE',
            ]);
        } catch (Throwable $e) {
            $server->update([
                'is_connected' => false,
                'last_checked_at' => now(),
                'last_error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => __('Tidak dapat terhubung ke WAHA: :error', ['error' => $e->getMessage()]),
            ]);
        }
    }

    /**
     * Ping the WAHA server to verify the stored credentials work.
     */
    public function test(WahaServer $server): RedirectResponse
    {
        Gate::authorize(UserPermission::GatewaysUpdate->value);

        if ($server->base_url === '' || $server->api_key === null || $server->api_key === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Lengkapi base URL dan API key terlebih dahulu.')]);

            return back();
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['X-API-Key' => $server->api_key])
                ->get(rtrim($server->base_url, '/').'/ping');

            $server->is_connected = $response->successful();
            $server->last_checked_at = now();
            $server->last_error = $response->successful() ? null : __('Server merespons dengan status :status.', ['status' => $response->status()]);
            $server->save();

            Inertia::flash('toast', $response->successful()
                ? ['type' => 'success', 'message' => __('Berhasil terhubung ke :name.', ['name' => $server->name])]
                : ['type' => 'error', 'message' => __('Server merespons dengan status :status.', ['status' => $response->status()])]);
        } catch (Throwable $e) {
            $server->is_connected = false;
            $server->last_checked_at = now();
            $server->last_error = $e->getMessage();
            $server->save();

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Tidak dapat terhubung ke :name.', ['name' => $server->name])]);
        }

        return back();
    }

    /**
     * Merge validated input with credential-keep-on-blank semantics.
     *
     * @return array<string, mixed>
     */
    private function credentials(WahaServerRequest $request, ?string $currentApiKey = null): array
    {
        $data = $request->safe()->except(['api_key']);

        if ($request->filled('api_key')) {
            $data['api_key'] = (string) $request->input('api_key');
        } elseif ($currentApiKey !== null) {
            $data['api_key'] = $currentApiKey;
        }

        return $data;
    }

    /**
     * Build the webhook URL from the configured APP_URL.
     */
    private function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/webhooks/waha';
    }

    /**
     * Shape all servers for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    private function servers(): array
    {
        return WahaServer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (WahaServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'base_url' => $server->base_url,
                'session' => $server->session,
                'has_api_key' => $server->api_key !== null && $server->api_key !== '',
                'is_connected' => $server->is_connected,
                'last_webhook_at' => $server->last_webhook_at?->toIso8601String(),
                'last_checked_at' => $server->last_checked_at?->toIso8601String(),
                'last_error' => $server->last_error,
            ])
            ->all();
    }

    /**
     * Boot the WAHA session, trying both known route shapes across versions.
     * A broken session (FAILED/STOPPED) needs an explicit stop before the
     * start call is accepted again.
     */
    private function startSession(string $base, array $headers, string $session): void
    {
        foreach (['/api/sessions/'.$session.'/stop', '/api/session/stop'] as $path) {
            $response = Http::timeout(8)->connectTimeout(3)->acceptJson()
                ->withHeaders($headers)
                ->post($base.$path, ['name' => $session]);

            if ($response->status() !== 404) {
                break;
            }
        }

        foreach (['/api/sessions/start', '/api/session/start'] as $path) {
            $response = Http::timeout(8)->connectTimeout(3)->acceptJson()
                ->withHeaders($headers)
                ->post($base.$path, ['name' => $session]);

            if ($response->status() !== 404) {
                return;
            }
        }
    }

    /**
     * Read the session status from either known route shape.
     */
    private function sessionStatus(string $base, array $headers, string $session): HttpClientResponse
    {
        $response = Http::timeout(8)->connectTimeout(3)->acceptJson()
            ->withHeaders($headers)
            ->get($base.'/api/sessions/'.$session);

        if ($response->status() === 404) {
            // Older WAHA versions answer on /api/{session}/status.
            $response = Http::timeout(8)->connectTimeout(3)->acceptJson()
                ->withHeaders($headers)
                ->get($base.'/api/'.$session.'/status');

            if ($response->status() === 404) {
                // Session may still be registering after start().
                sleep(1);

                $response = Http::timeout(8)->connectTimeout(3)->acceptJson()
                    ->withHeaders($headers)
                    ->get($base.'/api/'.$session.'/status');
            }
        }

        return $response;
    }

    /**
     * Read the session status from any known WAHA response shape.
     */
    private function wahaStatus($response): string
    {
        $status = $response->json('status');

        if (is_string($status) && $status !== '') {
            return $status;
        }

        // Some versions answer a plain string body.
        $body = trim((string) $response->body());

        return $body !== '' && strlen($body) < 40 ? strtoupper($body) : '';
    }
}
