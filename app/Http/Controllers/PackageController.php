<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\Packages\PackageRequest;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\RadiusServer;
use App\Services\Ppp\RadiusPppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PackageController extends Controller
{
    /**
     * Display packages & prices bound to real PPP profiles.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::PackagesView->value);

        $mikrotikServer = MikrotikServer::default();
        $radiusServer = RadiusServer::default();

        $radiusGroups = [];

        if ($radiusServer !== null) {
            try {
                $radiusGroups = RadiusPppService::forServer($radiusServer)->groups();
            } catch (Throwable) {
                $radiusGroups = [];
            }
        }

        $search = trim((string) request('search'));

        $packages = new LengthAwarePaginator([], 0, 10);
        $profiles = [];

        if ($mikrotikServer !== null) {
            $packages = DB::table('packages')
                ->join('ppp_profiles', 'ppp_profiles.id', '=', 'packages.ppp_profile_id')
                ->where('ppp_profiles.mikrotik_server_id', $mikrotikServer->id)
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->whereLike('packages.name', "%{$search}%", false)
                        ->orWhereLike('ppp_profiles.name', "%{$search}%", false)
                        ->orWhereLike('ppp_profiles.rate_limit', "%{$search}%", false);
                }))
                ->orderBy('packages.name')
                ->select(
                    'packages.id',
                    'packages.name',
                    'packages.ppp_profile_id',
                    'packages.price',
                    'packages.description',
                    'ppp_profiles.name as profile_name',
                    'ppp_profiles.rate_limit as rate_limit',
                    'ppp_profiles.remote_address as remote_address',
                )
                ->paginate(10)
                ->withQueryString();

            $profiles = PppProfile::query()
                ->leftJoin('packages', 'packages.ppp_profile_id', '=', 'ppp_profiles.id')
                ->where('ppp_profiles.mikrotik_server_id', $mikrotikServer->id)
                ->orderBy('ppp_profiles.name')
                ->get([
                    'ppp_profiles.id',
                    'ppp_profiles.name',
                    'ppp_profiles.rate_limit',
                    'ppp_profiles.remote_address',
                    'packages.id as package_id',
                ])
                ->map(fn ($profile): array => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'rate_limit' => $profile->rate_limit,
                    'remote_address' => $profile->remote_address,
                    'radius_synced' => array_key_exists($profile->name, $radiusGroups),
                    'has_package' => $profile->package_id !== null,
                ])
                ->all();
        }

        return Inertia::render('packages/index', [
            'packages' => $packages,
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
     * Store a package linked to an existing PPP profile.
     */
    public function store(PackageRequest $request): RedirectResponse
    {
        Package::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paket :name berhasil dibuat.', ['name' => $request->validated('name')])]);

        return back();
    }

    /**
     * Update the package.
     */
    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $package->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paket :name berhasil diperbarui.', ['name' => $package->name])]);

        return back();
    }

    /**
     * Delete the package (the linked PPP profile is kept).
     */
    public function destroy(Package $package): RedirectResponse
    {
        Gate::authorize(UserPermission::PackagesDelete->value);

        $package->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paket :name berhasil dihapus.', ['name' => $package->name])]);

        return back();
    }
}
