<?php

namespace App\Http\Controllers\Billing;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CustomerRequest;
use App\Models\AreaCode;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PppSecret;
use App\Models\RadiusServer;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CustomerController extends Controller
{
    /**
     * Display the billing customers.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::BillingView->value);

        $search = trim((string) request('search'));

        $customers = Customer::query()
            ->with([
                'areaCode:id,code,name',
                'package:id,name,price',
                'pppSecret:id,name,profile',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereLike('name', "%{$search}%", false)
                    ->orWhereLike('whatsapp', "%{$search}%", false)
                    ->orWhereHas('pppSecret', fn ($q) => $q->whereLike('name', "%{$search}%", false));
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'whatsapp' => $customer->whatsapp,
                'address' => $customer->address,
                'area_code_id' => $customer->area_code_id,
                'area_code' => $customer->areaCode?->code,
                'area_name' => $customer->areaCode?->name,
                'package_id' => $customer->package_id,
                'package_name' => $customer->package?->name,
                'package_price' => $customer->package?->price,
                'status' => $customer->status->value,
                'notes' => $customer->notes,
                'secret_username' => $customer->pppSecret?->name,
                'secret_profile' => $customer->pppSecret?->profile,
                'has_secret' => $customer->ppp_secret_id !== null,
            ]);

        return Inertia::render('billing/customers', [
            'customers' => $customers,
            'areas' => $this->areas(),
            'packages' => $this->packages(),
            'filters' => ['search' => request('search')],
        ]);
    }

    /**
     * The fully integrated detail page of a billing customer: identity,
     * service, the PSB ticket chain when converted from a prospect, and
     * every invoice/bill issued through that ticket.
     */
    public function show(Customer $customer): Response
    {
        Gate::authorize(UserPermission::BillingView->value);

        $customer->load([
            'areaCode:id,code,name',
            'package:id,name,price,ppp_profile_id',
            'package.pppProfile:id,name',
            'pppSecret:id,mikrotik_server_id,name,profile,disabled_on_mikrotik',
            'psbTicket:id,ticket_number,status,prospect_id,user_id,isolated_at,finished_at',
            'psbTicket.prospect:id,name,email,whatsapp,address,status,user_id,area_code_id,package_id',
            'psbTicket.assignedTo:id,name',
            'psbTicket.pelanggan:id,name,psb_ticket_id,whatsapp,status',
            'psbTicket.invoice:id,psb_ticket_id,invoice_number,payment_method,bank,va_number,total_exact,cash_total,status,paid_at,created_at',
            'psbTicket.bills:id,psb_ticket_id,bill_number,period,monthly_price,dpp,ppn,total,status,paid_at,reminder_sent_at',
        ]);

        $ticket = $customer->psbTicket;
        $invoice = $ticket?->invoice;

        return Inertia::render('billing/customers-detail', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'whatsapp' => $customer->whatsapp,
                'address' => $customer->address,
                'status' => $customer->status->value,
                'area_code' => $customer->areaCode?->code,
                'area_name' => $customer->areaCode?->name,
                'package_name' => $customer->package?->name,
                'package_price' => $customer->package?->price,
                'ppp_profile' => $customer->package?->pppProfile?->name
                    ?? $customer->pppSecret?->profile,
                'secret_username' => $customer->pppSecret?->name,
                'secret_disabled' => $customer->pppSecret?->disabled_on_mikrotik ?? false,
                'notes' => $customer->notes,
                'converted_from_prospect' => $ticket !== null,
                'pelanggan_id' => $ticket?->pelanggan?->id,
                'pelanggan_name' => $ticket?->pelanggan?->name,
            ],
            'ticket' => $ticket === null ? null : [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status->value,
                'technician' => $ticket->assignedTo?->name,
                'finished_at' => $ticket->finished_at?->toISOString(),
                'isolated' => $ticket->isolated_at !== null,
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
     * Store a new customer. Optionally provisions the PPP account on both
     * systems using the automatic credentials.
     */
    public function store(CustomerRequest $request): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingUpdate->value);

        try {
            DB::transaction(function () use ($request): void {
                $customer = Customer::create([
                    ...$request->safe()->except(['provision']),
                    'status' => CustomerStatus::Active->value,
                ]);

                if ($request->boolean('provision')) {
                    $this->provision($customer);
                }
            });
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer :name berhasil ditambahkan.', ['name' => $request->validated('name')])]);

        return back();
    }

    /**
     * Update the customer profile fields.
     */
    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->safe()->except(['provision']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Data customer berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove a customer without an active PPP account.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingDelete->value);

        if ($customer->ppp_secret_id !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Customer masih memiliki akun PPPoE. Tangguhkan dan hapus akunnya dari PPP Secret terlebih dahulu.')]);

            return back();
        }

        $customer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer berhasil dihapus.')]);

        return back();
    }

    /**
     * Suspend the customer: disable its PPP secret for real.
     */
    public function suspend(Customer $customer): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingUpdate->value);

        try {
            $secret = $this->requiredSecret($customer);

            // Resolved lazily so tests can swap the RouterOS client.
            $mikrotik = app(MikrotikPppService::class);
            $mikrotik->updateSecret($secret->name, ['disabled' => true]);

            $customer->update(['status' => CustomerStatus::Suspended->value]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Akun PPPoE customer ditangguhkan di Mikrotik.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Reactivate a suspended customer.
     */
    public function activate(Customer $customer): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingUpdate->value);

        try {
            $secret = $this->requiredSecret($customer);

            $mikrotik = app(MikrotikPppService::class);
            $mikrotik->updateSecret($secret->name, ['disabled' => false]);

            $customer->update(['status' => CustomerStatus::Active->value]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer diaktifkan kembali.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Upgrade/downgrade the package: really moves the PPP secret to the
     * new package's profile on Mikrotik & Radius.
     */
    public function upgrade(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingUpdate->value);

        $request->validate([
            'package_id' => ['required', Rule::exists('packages', 'id')],
        ]);

        try {
            $newPackage = Package::query()
                ->with('pppProfile')
                ->findOrFail((int) $request->input('package_id'));

            if ($newPackage->pppProfile === null) {
                throw new \RuntimeException(__('Paket baru belum terhubung ke PPP Profile.'));
            }

            $secret = $this->requiredSecret($customer);

            $mikrotik = app(MikrotikPppService::class);
            $mikrotik->updateSecret($secret->name, ['profile' => $newPackage->pppProfile->name]);

            if ($radiusServer = RadiusServer::default()) {
                try {
                    RadiusPppService::forServer($radiusServer)
                        ->upsertUser($secret->name, null, $newPackage->pppProfile->name);
                } catch (Throwable) {
                    //
                }
            }

            $customer->update(['package_id' => $newPackage->id]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Customer pindah ke paket :paket (profil :profil).', ['paket' => $newPackage->name, 'profil' => $newPackage->pppProfile->name]),
            ]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Download the customer's bills as CSV.
     */
    public function downloadBills(Customer $customer): StreamedResponse
    {
        Gate::authorize(UserPermission::BillingView->value);

        $filename = 'tagihan-'.Str::slug($customer->name).'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($customer): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['No', 'Periode', 'Deskripsi', 'DPP', 'PPN', 'Total', 'Status']);

            // Monthly bills of the customer through its PSB ticket (when the
            // customer was converted from a prospect).
            $ticketId = $customer->psb_ticket_id;

            $rows = $ticketId !== null
                ? Bill::query()->where('psb_ticket_id', $ticketId)->orderByDesc('period')->get()
                : collect();

            foreach ($rows as $i => $bill) {
                fputcsv($out, [
                    $i + 1,
                    $bill->period,
                    'Tagihan bulanan',
                    $bill->dpp,
                    $bill->ppn,
                    $bill->total,
                    $bill->status->label(),
                ]);
            }

            fputcsv($out, ['', '', 'Pelanggan: '.$customer->name.' ('.$customer->whatsapp.')']);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Provision a PPP account for a manually created customer.
     *
     * @return array{username: string, password: string}
     */
    private function provision(Customer $customer): array
    {
        $credentials = ProspectCredentialService::build(
            (string) $customer->areaCode?->code,
            $customer->name,
        );

        if (PppSecret::query()->where('name', $credentials['username'])->exists()) {
            throw new \RuntimeException(__('Secret :name sudah ada di PPP Secret.', ['name' => $credentials['username']]));
        }

        $secret = PppProvisioningService::make()->createSecret(
            $credentials['username'],
            $credentials['password'],
            (string) ($customer->package?->pppProfile?->name ?? ''),
            sprintf('customer #%d - %s', $customer->id, $customer->name),
        );

        $customer->update(['ppp_secret_id' => $secret->id]);

        return $credentials;
    }

    private function requiredSecret(Customer $customer): PppSecret
    {
        $secret = $customer->pppSecret;

        if ($secret === null) {
            throw new \RuntimeException(__('Customer belum memiliki akun PPPoE.'));
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
