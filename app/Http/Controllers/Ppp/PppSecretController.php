<?php

namespace App\Http\Controllers\Ppp;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ppp\PppSecretRequest;
use App\Models\MikrotikServer;
use App\Models\PppSecret;
use App\Models\RadiusServer;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PppSecretController extends Controller
{
    /**
     * Display PPP secrets with radius membership state.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::PppView->value);

        $mikrotikServer = MikrotikServer::default();
        $radiusServer = RadiusServer::default();

        $radiusUsers = [];

        if ($radiusServer !== null) {
            try {
                $radiusUsers = collect(RadiusPppService::forServer($radiusServer)->users())
                    ->keyBy('username')
                    ->all();
            } catch (Throwable) {
                $radiusUsers = [];
            }
        }

        $search = trim((string) request('search'));

        $secrets = new LengthAwarePaginator([], 0, 10);

        if ($mikrotikServer !== null) {
            $secrets = DB::table('ppp_secrets')
                ->where('mikrotik_server_id', $mikrotikServer->id)
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%", false)
                        ->orWhereLike('profile', "%{$search}%", false);
                }))
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($secret) => [
                    'id' => $secret->id,
                    'name' => $secret->name,
                    'profile' => $secret->profile,
                    'disabled_on_mikrotik' => (bool) $secret->disabled_on_mikrotik,
                    'radius_enabled' => isset($radiusUsers[$secret->name]),
                    'radius_profile' => $radiusUsers[$secret->name]['profile'] ?? null,
                ]);
        }

        return Inertia::render('ppp/secret', [
            'secrets' => $secrets,
            'filters' => [
                'search' => request('search'),
            ],
            'profiles' => DB::table('ppp_profiles')->orderBy('name')->pluck('name'),
            'mikrotik_server' => $this->present($mikrotikServer),
        ]);
    }

    /**
     * Store a secret: enabled in radius, disabled on mikrotik.
     */
    public function store(PppSecretRequest $request): RedirectResponse
    {
        try {
            PppProvisioningService::make()->createSecret(
                (string) $request->input('name'),
                (string) $request->input('password'),
                (string) $request->input('profile'),
            );

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Secret dibuat: aktif di Radius, disable di Mikrotik.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Update the secret on both systems.
     */
    public function update(PppSecretRequest $request, PppSecret $secret): RedirectResponse
    {
        try {
            PppProvisioningService::make()->updateSecret($secret, [
                'password' => $request->filled('password') ? (string) $request->input('password') : null,
                'profile' => (string) $request->input('profile'),
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Secret diperbarui di Mikrotik & Radius.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Delete the secret from both systems.
     */
    public function destroy(PppSecret $secret): RedirectResponse
    {
        try {
            PppProvisioningService::make()->deleteSecret($secret);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Secret dihapus dari Mikrotik & Radius.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Present the default mikrotik server connectivity.
     *
     * @return array<string, mixed>|null
     */
    private function present(?MikrotikServer $server): ?array
    {
        if ($server === null) {
            return null;
        }

        return [
            'id' => $server->id,
            'name' => $server->name,
        ];
    }
}
