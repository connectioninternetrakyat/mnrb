<?php

namespace App\Http\Controllers\Ppp;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ppp\PppProfileRequest;
use App\Models\MikrotikServer;
use App\Models\PppProfile;
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

class PppProfileController extends Controller
{
    /**
     * Display the PPP profiles with their radius sync state.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::PppView->value);

        [$mikrotikServer, $radiusServer] = $this->defaultServers();

        $provisioning = PppProvisioningService::make();

        $radiusGroups = [];

        if ($radiusServer !== null) {
            try {
                $radiusGroups = RadiusPppService::forServer($radiusServer)->groups();
            } catch (Throwable) {
                $radiusGroups = [];
            }
        }

        $search = trim((string) request('search'));

        $profiles = new LengthAwarePaginator([], 0, 10);

        if ($mikrotikServer !== null) {
            $profiles = DB::table('ppp_profiles')
                ->where('mikrotik_server_id', $mikrotikServer->id)
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%", false)
                        ->orWhereLike('remote_address', "%{$search}%", false)
                        ->orWhereLike('rate_limit', "%{$search}%", false);
                }))
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($profile): array => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'local_address' => $profile->local_address,
                    'remote_address' => $profile->remote_address,
                    'dns_server' => $profile->dns_server,
                    'rate_limit' => $profile->rate_limit,
                    'radius_synced' => array_key_exists($profile->name, $radiusGroups),
                ]);
        }

        return Inertia::render('ppp/profile', [
            'profiles' => $profiles,
            'filters' => [
                'search' => request('search'),
            ],
            'mikrotik_server' => $mikrotikServer !== null
                ? ['id' => $mikrotikServer->id, 'name' => $mikrotikServer->name]
                : null,
            'radius_server' => $radiusServer !== null
                ? ['id' => $radiusServer->id, 'name' => $radiusServer->name]
                : null,
        ]);
    }

    /**
     * Store a new profile on both systems.
     */
    public function store(PppProfileRequest $request): RedirectResponse
    {
        try {
            PppProvisioningService::make()->createProfile($request->validated());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('PPP Profile tersinkron di Mikrotik & Radius.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Update the profile on both systems.
     */
    public function update(PppProfileRequest $request, PppProfile $profile): RedirectResponse
    {
        try {
            PppProvisioningService::make()->updateProfile($profile, $request->validated());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('PPP Profile diperbarui di Mikrotik & Radius.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Delete the profile from both systems.
     */
    public function destroy(PppProfile $profile): RedirectResponse
    {
        try {
            PppProvisioningService::make()->deleteProfile($profile);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('PPP Profile dihapus dari Mikrotik & Radius.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * One-time manual import of existing Mikrotik profiles into radius.
     */
    public function syncFromMikrotik(): RedirectResponse
    {
        try {
            $count = PppProvisioningService::make()->importProfilesFromMikrotik();

            Inertia::flash('toast', ['type' => 'success', 'message' => __(':count profil dari Mikrotik tersinkron ke Radius.', ['count' => $count])]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Resolve the default mikrotik + radius servers.
     *
     * @return array{0: ?MikrotikServer, 1: ?RadiusServer}
     */
    private function defaultServers(): array
    {
        return [
            MikrotikServer::default(),
            RadiusServer::default(),
        ];
    }
}
