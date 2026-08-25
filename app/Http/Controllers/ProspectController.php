<?php

namespace App\Http\Controllers;

use App\Enums\ProspectStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Http\Requests\Prospects\ApproveProspectRequest;
use App\Http\Requests\Prospects\ProspectRequest;
use App\Models\AreaCode;
use App\Models\Prospect;
use App\Services\Prospect\ProspectApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class ProspectController extends Controller
{
    /**
     * Display the prospects with verification status.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::ProspectsView->value);

        $user = request()->user();
        $search = trim((string) request('search'));
        $status = ProspectStatus::tryFrom((string) request('status'));

        // Marketing & teknisi only see the prospects they brought in.
        $prospects = Prospect::query()
            ->when(! $user->seesAllData(), fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'areaCode:id,code,name',
                'package:id,name,price',
                'package.pppProfile:id,name,rate_limit',
                'broughtBy:id,name',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereLike('name', "%{$search}%", false)
                    ->orWhereLike('whatsapp', "%{$search}%", false)
                    ->orWhereLike('email', "%{$search}%", false)
                    ->orWhereLike('address', "%{$search}%", false);
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Prospect $prospect): array => [
                'id' => $prospect->id,
                'name' => $prospect->name,
                'email' => $prospect->email,
                'whatsapp' => $prospect->whatsapp,
                'address' => $prospect->address,
                'area_code_id' => $prospect->area_code_id,
                'area_code' => $prospect->areaCode?->code,
                'area_name' => $prospect->areaCode?->name,
                'package_id' => $prospect->package_id,
                'package_name' => $prospect->package?->name,
                'package_price' => $prospect->package?->price,
                'profile_name' => $prospect->package?->pppProfile?->name,
                'rate_limit' => $prospect->package?->pppProfile?->rate_limit,
                'brought_by' => $prospect->broughtBy?->name,
                'maps_url' => $prospect->maps_url,
                'latitude' => $prospect->latitude,
                'longitude' => $prospect->longitude,
                'status' => $prospect->status->value,
                'approved_at' => $prospect->approved_at?->toISOString(),
                'note' => $prospect->note,
            ]);

        return Inertia::render('prospects/index', [
            'prospects' => $prospects,
            'areas' => $this->areas(),
            'packages' => $this->packages(),
            'technicians' => $this->technicians(),
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
            ],
        ]);
    }

    /**
     * Store a new prospect submitted by marketing / technician.
     */
    public function store(ProspectRequest $request): RedirectResponse
    {
        Prospect::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => ProspectStatus::Pending->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prospect berhasil disimpan dan menunggu verifikasi.')]);

        return back();
    }

    /**
     * Update a prospect that is still waiting for verification.
     */
    public function update(ProspectRequest $request, Prospect $prospect): RedirectResponse
    {
        if (! $prospect->isPending()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Hanya prospect berstatus menunggu verifikasi yang dapat diubah.')]);

            return back();
        }

        // The original marketing / pembawa stays untouched.
        $prospect->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Data prospect berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove a prospect that has not been approved.
     */
    public function destroy(Prospect $prospect): RedirectResponse
    {
        Gate::authorize(UserPermission::ProspectsDelete->value);

        if ($prospect->status === ProspectStatus::Approved) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Prospect yang sudah disetujui tidak dapat dihapus.')]);

            return back();
        }

        $prospect->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prospect berhasil dihapus.')]);

        return back();
    }

    /**
     * Approve the prospect, provision its PPP secret on Mikrotik & Radius
     * and create the PSB installation ticket.
     */
    public function approve(ApproveProspectRequest $request, Prospect $prospect): RedirectResponse
    {
        Gate::authorize(UserPermission::ProspectsApprove->value);

        try {
            $credentials = $this->approveProspect($prospect, [
                'user_id' => (int) $request->validated('user_id'),
                'scheduled_at' => (string) $request->validated('scheduled_at'),
            ]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Prospect disetujui. Secret :username dibuat & tiket PSB terjadwal.', ['username' => $credentials['username']]),
            ]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Reject the prospect.
     */
    public function reject(Prospect $prospect): RedirectResponse
    {
        Gate::authorize(UserPermission::ProspectsApprove->value);

        if (! $prospect->isPending()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Hanya prospect berstatus menunggu verifikasi yang dapat diproses.')]);

            return back();
        }

        $prospect->update(['status' => ProspectStatus::Rejected->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prospect ditolak.')]);

        return back();
    }

    /**
     * Approve flow: build credentials, sync them to both systems for real,
     * then mark the prospect as approved and schedule the PSB ticket.
     *
     * @param  array{user_id: int, scheduled_at: string}  $schedule
     * @return array{username: string, password: string}
     */
    private function approveProspect(Prospect $prospect, array $schedule): array
    {
        return (new ProspectApprovalService)->approve($prospect, $schedule);
    }

    /**
     * Shape technicians (users with the Teknisi role) for the approve dialog.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function technicians(): array
    {
        $role = Role::query()->where('name', UserRole::Teknisi->value)->first();

        if ($role === null) {
            return [];
        }

        return $role->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($user): array => [
                'id' => $user->id,
                'name' => $user->name,
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

    /**
     * Shape packages (+ their real PPP profile) for select inputs.
     *
     * @return array<int, array{id: int, name: string, price: int, profile_name: string|null, rate_limit: string|null}>
     */
    private function packages(): array
    {
        return DB::table('packages')
            ->join('ppp_profiles', 'ppp_profiles.id', '=', 'packages.ppp_profile_id')
            ->orderBy('packages.name')
            ->get([
                'packages.id',
                'packages.name',
                'packages.price',
                'ppp_profiles.name as profile_name',
                'ppp_profiles.rate_limit as rate_limit',
            ])
            ->map(fn ($package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => (int) $package->price,
                'profile_name' => $package->profile_name,
                'rate_limit' => $package->rate_limit,
            ])
            ->all();
    }
}
