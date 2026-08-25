<?php

namespace App\Http\Controllers\Ftth;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ftth\OdpOdcRequest;
use App\Models\AreaCode;
use App\Models\OdpOdc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OdpOdcController extends Controller
{
    /**
     * Display the ODP/ODC list.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::FtthView->value);

        return Inertia::render('ftth/odp-odc', [
            'devices' => $this->devices(),
            'areas' => $this->areas(),
        ]);
    }

    /**
     * Store a newly created ODP/ODC.
     */
    public function store(OdpOdcRequest $request): RedirectResponse
    {
        OdpOdc::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ODP/ODC berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given ODP/ODC.
     */
    public function update(OdpOdcRequest $request, OdpOdc $odp): RedirectResponse
    {
        $odp->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ODP/ODC berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given ODP/ODC.
     */
    public function destroy(OdpOdc $odp): RedirectResponse
    {
        $odp->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ODP/ODC berhasil dihapus.')]);

        return back();
    }

    /**
     * Shape all devices for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    private function devices(): array
    {
        return OdpOdc::query()
            ->with('areaCode:id,code,name')
            ->orderBy('code')
            ->get()
            ->map(fn (OdpOdc $odp): array => [
                'id' => $odp->id,
                'code' => $odp->code,
                'name' => $odp->name,
                'area_code_id' => $odp->area_code_id,
                'area_code' => $odp->areaCode?->code,
                'area_name' => $odp->areaCode?->name,
                'region' => $odp->region,
                'port' => $odp->port,
                'splitter_ratio' => $odp->splitter_ratio,
                'maps_url' => $odp->maps_url,
                'latitude' => $odp->latitude,
                'longitude' => $odp->longitude,
                'description' => $odp->description,
            ])
            ->all();
    }

    /**
     * Shape all area codes for select inputs.
     *
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function areas(): array
    {
        return AreaCode::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (AreaCode $area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
            ])
            ->all();
    }
}
