<?php

namespace App\Http\Controllers\Ftth;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ftth\AreaCodeRequest;
use App\Models\AreaCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AreaCodeController extends Controller
{
    /**
     * Display the area codes list.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::FtthView->value);

        return Inertia::render('ftth/area-codes', [
            'areas' => AreaCode::query()
                ->withCount('odpOdcs')
                ->orderBy('code')
                ->get()
                ->map(fn (AreaCode $area): array => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'region' => $area->region,
                    'description' => $area->description,
                    'devices_count' => $area->odp_odcs_count,
                ])
                ->all(),
        ]);
    }

    /**
     * Store a newly created area code.
     */
    public function store(AreaCodeRequest $request): RedirectResponse
    {
        AreaCode::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kode area berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given area code.
     */
    public function update(AreaCodeRequest $request, AreaCode $areaCode): RedirectResponse
    {
        $areaCode->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kode area berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given area code.
     */
    public function destroy(AreaCode $areaCode): RedirectResponse
    {
        if ($areaCode->odpOdcs()->exists()) {
            abort(403, __('Kode area masih dipakai oleh ODP/ODC.'));
        }

        $areaCode->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kode area berhasil dihapus.')]);

        return back();
    }
}
