<?php

namespace App\Http\Controllers\Ftth;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Services\GoogleMapsUrlResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MapsUrlResolverController extends Controller
{
    /**
     * Resolve a Google Maps URL / short link into coordinates.
     */
    public function __invoke(Request $request, GoogleMapsUrlResolver $resolver): JsonResponse
    {
        // Shared utility endpoint: FTTH editors and prospect inputters both
        // use the "Ambil Lokasi" button.
        if (! $request->user()->canAny([UserPermission::MapsResolve->value, UserPermission::FtthUpdate->value])) {
            abort(403, __('Anda tidak memiliki izin mengambil koordinat lokasi.'));
        }

        $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $coordinates = $resolver->resolve((string) $request->input('url'));
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'url' => $e->getMessage(),
            ]);
        }

        return response()->json($coordinates);
    }
}
