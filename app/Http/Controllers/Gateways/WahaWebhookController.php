<?php

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Models\WahaServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WahaWebhookController extends Controller
{
    /**
     * Handle a WAHA webhook event.
     *
     * WAHA sends the session API key in the X-Api-Key header; the payload
     * must present a key matching one of the active servers.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $providedKey = (string) $request->header('X-Api-Key', '');

        if ($providedKey === '') {
            Log::warning('WAHA webhook rejected: missing API key.');

            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $server = null;

        foreach (WahaServer::query()->orderBy('id')->get() as $candidate) {
            if ((string) $candidate->api_key !== '' && hash_equals((string) $candidate->api_key, $providedKey)) {
                $server = $candidate;
                break;
            }
        }

        if ($server === null) {
            Log::warning('WAHA webhook rejected: no matching server API key.');

            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $server->last_webhook_at = now();
        $server->save();

        Log::info('WAHA webhook received.', [
            'server' => $server->name,
            'event' => $request->input('event'),
            'session' => $request->input('session'),
            'payload' => $request->json()->all(),
        ]);

        return response()->json(['message' => 'Webhook received.']);
    }
}
