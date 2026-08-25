<?php

namespace App\Http\Controllers\Pelanggan;

use App\Enums\InvoiceStatus;
use App\Enums\PelangganStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pelanggan\PelangganRequest;
use App\Models\AreaCode;
use App\Models\Bill;
use App\Models\Package;
use App\Models\Pelanggan;
use App\Models\PppSecret;
use App\Models\RadiusServer;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\RadiusPppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PelangganController extends Controller
{
    /**
     * Display the FTTH customers.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::PelangganView->value);

        $search = trim((string) request('search'));
        $status = PelangganStatus::tryFrom((string) request('status'));

        $pelanggans = Pelanggan::query()
            ->with([
                'areaCode:id,code,name',
                'package:id,name,price',
                'pppSecret:id,name,profile,disabled_on_mikrotik',
                // Real integration: the ticket chain carries the prospect
                // origin, the activation invoice and the recurring bills.
                'psbTicket:id,ticket_number,status,prospect_id,isolated_at',
                'psbTicket.prospect:id,name,whatsapp',
                'psbTicket.invoice:id,psb_ticket_id,invoice_number,status,cash_total,total_exact,paid_at',
                'psbTicket.bills:id,psb_ticket_id,bill_number,period,total,status',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereLike('name', "%{$search}%", false)
                    ->orWhereLike('whatsapp', "%{$search}%", false)
                    ->orWhereHas('pppSecret', fn ($q) => $q->whereLike('name', "%{$search}%", false))
                    ->orWhereHas('psbTicket', fn ($q) => $q->whereLike('ticket_number', "%{$search}%", false));
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Pelanggan $pelanggan): array => [
                'id' => $pelanggan->id,
                'name' => $pelanggan->name,
                'email' => $pelanggan->email,
                'whatsapp' => $pelanggan->whatsapp,
                'address' => $pelanggan->address,
                'maps_url' => $pelanggan->maps_url,
                'area_code_id' => $pelanggan->area_code_id,
                'area_code' => $pelanggan->areaCode?->code,
                'area_name' => $pelanggan->areaCode?->name,
                'package_id' => $pelanggan->package_id,
                'package_name' => $pelanggan->package?->name,
                'package_price' => $pelanggan->package?->price,
                'status' => $pelanggan->status->value,
                'joined_at' => $pelanggan->joined_at?->toISOString(),
                'activated_at' => $pelanggan->activated_at?->toISOString(),
                'notes' => $pelanggan->notes,
                'secret_username' => $pelanggan->pppSecret?->name,
                'secret_profile' => $pelanggan->pppSecret?->profile,
                'secret_disabled' => $pelanggan->pppSecret?->disabled_on_mikrotik ?? false,
                // Integration data (null for manual customers).
                'source' => $pelanggan->psbTicket !== null ? 'prospek' : 'manual',
                'ticket_number' => $pelanggan->psbTicket?->ticket_number,
                'ticket_status' => $pelanggan->psbTicket?->status?->value,
                'prospect_name' => $pelanggan->psbTicket?->prospect?->name,
                'isolated' => $pelanggan->psbTicket?->isolated_at !== null,
                'invoice_number' => $pelanggan->psbTicket?->invoice?->invoice_number,
                'invoice_status' => $pelanggan->psbTicket?->invoice?->status?->value,
                'invoice_total' => (int) ($pelanggan->psbTicket?->invoice?->cash_total
                    ?: $pelanggan->psbTicket?->invoice?->total_exact
                    ?: 0),
                'bills_count' => $pelanggan->psbTicket?->bills->count() ?? 0,
                'bills_unpaid' => $pelanggan->psbTicket?->bills
                    ->filter(fn (Bill $bill): bool => $bill->status === InvoiceStatus::Unpaid)
                    ->count() ?? 0,
                'outstanding' => (int) $pelanggan->psbTicket?->bills
                    ->filter(fn (Bill $bill): bool => $bill->status === InvoiceStatus::Unpaid)
                    ->sum('total'),
            ]);

        return Inertia::render('pelanggan/index', [
            'pelanggans' => $pelanggans,
            'areas' => $this->areas(),
            'packages' => $this->packages(),
            // Secrets not yet owned by any pelanggan: candidates for linking.
            'availableSecrets' => $this->availableSecrets(),
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
            ],
        ]);
    }

    /**
     * The fully integrated detail page of a pelanggan: identity, service,
     * the prospect/ticket origin chain, activation invoice and the
     * recurring bills.
     */
    public function show(Pelanggan $pelanggan): Response
    {
        Gate::authorize(UserPermission::PelangganView->value);

        $pelanggan->load([
            'areaCode:id,code,name',
            'package:id,name,price,ppp_profile_id',
            'package.pppProfile:id,name',
            'pppSecret:id,mikrotik_server_id,name,profile,disabled_on_mikrotik',
            'psbTicket:id,ticket_number,status,prospect_id,user_id,scheduled_at,started_at,finished_at,cancelled_at,isolated_at,isolated_profile',
            'psbTicket.prospect:id,name,email,whatsapp,address,status,user_id,area_code_id,package_id',
            'psbTicket.assignedTo:id,name',
            'psbTicket.invoice:id,psb_ticket_id,invoice_number,payment_method,bank,va_number,total_exact,cash_total,status,paid_at,created_at',
            'psbTicket.bills:id,psb_ticket_id,bill_number,period,monthly_price,dpp,ppn,total,status,paid_at,reminder_sent_at',
        ]);

        $ticket = $pelanggan->psbTicket;
        $invoice = $ticket?->invoice;

        return Inertia::render('pelanggan/detail', [
            'pelanggan' => [
                'id' => $pelanggan->id,
                'name' => $pelanggan->name,
                'email' => $pelanggan->email,
                'whatsapp' => $pelanggan->whatsapp,
                'address' => $pelanggan->address,
                'maps_url' => $pelanggan->maps_url,
                'status' => $pelanggan->status->value,
                'area_code' => $pelanggan->areaCode?->code,
                'area_name' => $pelanggan->areaCode?->name,
                'package_name' => $pelanggan->package?->name,
                'package_price' => $pelanggan->package?->price,
                'ppp_profile' => $pelanggan->package?->pppProfile?->name
                    ?? $pelanggan->pppSecret?->profile,
                'secret_username' => $pelanggan->pppSecret?->name,
                'secret_profile' => $pelanggan->pppSecret?->profile,
                'secret_disabled' => $pelanggan->pppSecret?->disabled_on_mikrotik ?? false,
                'joined_at' => $pelanggan->joined_at?->toISOString(),
                'activated_at' => $pelanggan->activated_at?->toISOString(),
                'notes' => $pelanggan->notes,
            ],
            'ticket' => $ticket === null ? null : [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status->value,
                'technician' => $ticket->assignedTo?->name,
                'scheduled_at' => $ticket->scheduled_at?->toISOString(),
                'started_at' => $ticket->started_at?->toISOString(),
                'finished_at' => $ticket->finished_at?->toISOString(),
                'cancelled_at' => $ticket->cancelled_at?->toISOString(),
                'isolated' => $ticket->isolated_at !== null,
                'isolated_profile' => $ticket->isolated_profile,
            ],
            'prospect' => $ticket?->prospect === null ? null : [
                'id' => $ticket->prospect->id,
                'name' => $ticket->prospect->name,
                'email' => $ticket->prospect->email,
                'whatsapp' => $ticket->prospect->whatsapp,
                'address' => $ticket->prospect->address,
                'status' => $ticket->prospect->status->value,
            ],
            'invoice' => $invoice === null ? null : [
                'invoice_number' => $invoice->invoice_number,
                'payment_method' => $invoice->payment_method,
                'bank' => $invoice->bank,
                'va_number' => $invoice->va_number,
                'status' => $invoice->status->value,
                'total' => (int) ($invoice->cash_total ?: $invoice->total_exact),
                'paid_at' => $invoice->paid_at?->toISOString(),
                'created_at' => $invoice->created_at?->toISOString(),
            ],
            'bills' => $ticket?->bills
                ->sortByDesc('period')
                ->values()
                ->map(fn (Bill $bill): array => [
                    'bill_number' => $bill->bill_number,
                    'period' => $bill->period,
                    'monthly_price' => $bill->monthly_price,
                    'dpp' => $bill->dpp,
                    'ppn' => $bill->ppn,
                    'total' => $bill->total,
                    'status' => $bill->status->value,
                    'paid_at' => $bill->paid_at?->toISOString(),
                ])
                ->all() ?? [],
            'summary' => [
                'outstanding' => (int) $ticket?->bills
                    ->filter(fn (Bill $bill): bool => $bill->status === InvoiceStatus::Unpaid)
                    ->sum('total'),
                'unpaid_count' => $ticket?->bills
                    ->filter(fn (Bill $bill): bool => $bill->status === InvoiceStatus::Unpaid)
                    ->count() ?? 0,
                'bills_count' => $ticket?->bills->count() ?? 0,
            ],
        ]);
    }

    /**
     * Store a new FTTH customer, optionally linking an existing PPP secret.
     */
    public function store(PelangganRequest $request): RedirectResponse
    {
        Gate::authorize(UserPermission::PelangganUpdate->value);

        Pelanggan::create([
            ...$request->validated(),
            'joined_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pelanggan berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the pelanggan profile.
     */
    public function update(PelangganRequest $request, Pelanggan $pelanggan): RedirectResponse
    {
        $pelanggan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Data pelanggan berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove a customer without a linked PPP account.
     */
    public function destroy(Pelanggan $pelanggan): RedirectResponse
    {
        Gate::authorize(UserPermission::PelangganDelete->value);

        if ($pelanggan->ppp_secret_id !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Pelanggan masih terhubung akun PPPoE. Ubah statusnya menjadi berhenti dan lepaskan akunnya dahulu.')]);

            return back();
        }

        $pelanggan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pelanggan berhasil dihapus.')]);

        return back();
    }

    /**
     * Stop the service: disable the PPP secret for real on Mikrotik.
     */
    public function stop(Pelanggan $pelanggan): RedirectResponse
    {
        Gate::authorize(UserPermission::PelangganUpdate->value);

        try {
            $secret = $this->requiredSecret($pelanggan);
            $mikrotik = app(MikrotikPppService::class);

            $mikrotik->updateSecret($secret->name, ['disabled' => true]);

            if ($radiusServer = RadiusServer::default()) {
                try {
                    RadiusPppService::forServer($radiusServer)
                        ->deleteUser($secret->name);
                } catch (Throwable) {
                    //
                }
            }

            $pelanggan->update(['status' => PelangganStatus::Stopped->value]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Layanan pelanggan dihentikan (akun dinonaktifkan).')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Reactivate the service of a stopped customer.
     */
    public function activate(Pelanggan $pelanggan): RedirectResponse
    {
        Gate::authorize(UserPermission::PelangganUpdate->value);

        try {
            $secret = $this->requiredSecret($pelanggan);
            $mikrotik = app(MikrotikPppService::class);

            $mikrotik->updateSecret($secret->name, [
                'disabled' => false,
                'profile' => (string) ($pelanggan->package?->pppProfile?->name ?? $secret->profile),
            ]);

            if ($radiusServer = RadiusServer::default()) {
                try {
                    RadiusPppService::forServer($radiusServer)
                        ->upsertUser($secret->name, null, (string) ($pelanggan->package?->pppProfile?->name ?? $secret->profile));
                } catch (Throwable) {
                    //
                }
            }

            $pelanggan->update(['status' => PelangganStatus::Active->value]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Layanan pelanggan diaktifkan kembali.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    private function requiredSecret(Pelanggan $pelanggan): PppSecret
    {
        $secret = $pelanggan->pppSecret;

        if ($secret === null) {
            throw new \RuntimeException(__('Pelanggan belum memiliki akun PPPoE.'));
        }

        return $secret;
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
     * Shape packages (+ real PPP profile) for select inputs.
     *
     * @return array<int, array{id: int, name: string, price: int}>
     */
    private function packages(): array
    {
        return Package::query()
            ->orderBy('name')
            ->get(['id', 'name', 'price'])
            ->map(fn (Package $package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => $package->price,
            ])
            ->all();
    }

    /**
     * PPP secrets not linked to any pelanggan yet.
     *
     * @return array<int, array{id: int, name: string, profile: string|null}>
     */
    private function availableSecrets(): array
    {
        return PppSecret::query()
            ->whereNotIn('id', DB::table('pelanggans')->select('ppp_secret_id')->whereNotNull('ppp_secret_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'profile'])
            ->map(fn (PppSecret $secret): array => [
                'id' => $secret->id,
                'name' => $secret->name,
                'profile' => $secret->profile,
            ])
            ->all();
    }
}
