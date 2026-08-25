<?php

namespace App\Http\Controllers\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\PppSecret;
use App\Models\RadiusServer;
use App\Services\Billing\MonthlyBillService;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectCredentialService;
use App\Services\Waha\TemplateRenderer;
use App\Services\Waha\WahaSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    /**
     * Display the monthly bills.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::BillingView->value);

        $search = trim((string) request('search'));
        $status = InvoiceStatus::tryFrom((string) request('status'));

        $bills = Bill::query()
            ->with([
                'psbTicket:id,ticket_number,prospect_id',
                'psbTicket.prospect:id,name,whatsapp,area_code_id,package_id',
                'psbTicket.prospect.areaCode:id,code,name',
                'psbTicket.prospect.package:id,name,price',
                // Managed customer record wins for display data.
                'psbTicket.pelanggan:id,name,whatsapp,area_code_id,package_id,psb_ticket_id',
                'psbTicket.pelanggan.areaCode:id,code,name',
                'psbTicket.pelanggan.package:id,name,price',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereLike('bill_number', "%{$search}%", false)
                    ->orWhereHas('psbTicket.prospect', fn ($q) => $q->whereLike('name', "%{$search}%", false))
                    ->orWhereHas('psbTicket.pelanggan', fn ($q) => $q->whereLike('name', "%{$search}%", false));
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('period')
            ->orderBy('bill_number')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Bill $bill): array => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'period' => $bill->period,
                'total' => $bill->total,
                'dpp' => $bill->dpp,
                'ppn' => $bill->ppn,
                'status' => $bill->status->value,
                'paid_at' => $bill->paid_at?->toISOString(),
                'customer_name' => $bill->psbTicket?->customerName(),
                'customer_whatsapp' => $bill->psbTicket?->contactWhatsapp(),
                'area_code' => $bill->psbTicket?->customerAreaCode(),
                'package_name' => $bill->psbTicket?->customerPackageName(),
                'ticket_id' => $bill->psbTicket?->id,
                'ticket_number' => $bill->psbTicket?->ticket_number,
            ]);

        return Inertia::render('billing/bills', [
            'bills' => $bills,
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
            ],
        ]);
    }

    /**
     * Generate the missing bills of the current period.
     */
    public function generate(): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingUpdate->value);

        $created = (new MonthlyBillService)->generateForCurrentPeriod();

        if ($created === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Tidak ada tagihan baru. Semua pelanggan sudah ditagih untuk periode ini.')]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __(':count tagihan bulan ini berhasil dibuat.', ['count' => $created])]);
        }

        return back();
    }

    /**
     * Manually confirm a bill as paid.
     */
    public function markPaid(Bill $bill): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingUpdate->value);

        if ($bill->status === InvoiceStatus::Paid) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Tagihan :number sudah lunas.', ['number' => $bill->bill_number])]);

            return back();
        }

        $bill->markAsPaid();

        // When the customer settles up, lift the isolation automatically.
        $this->liftIsolationIfSettled($bill);
        $this->notifyPayment($bill);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tagihan :number ditandai lunas.', ['number' => $bill->bill_number])]);

        return back();
    }

    /**
     * Send the payment confirmation through WhatsApp using the pembayaran
     * template (silent failure: notification must not block billing).
     */
    private function notifyPayment(Bill $bill): void
    {
        try {
            $ticket = $bill->psbTicket;

            if ($ticket === null || $bill->paid_at === null) {
                return;
            }

            $whatsapp = $ticket->contactWhatsapp();

            if ($whatsapp === null) {
                return;
            }

            $message = TemplateRenderer::renderSlug('pembayaran', '', [
                'nama' => $ticket->customerName() ?? '',
                'tagihan' => $bill->bill_number,
                'periode' => $bill->period,
                'total' => 'Rp '.number_format((float) $bill->total, 0, ',', '.'),
                'metode' => ucfirst((string) ($bill->payment_method ?? '')),
            ]);

            if ($message !== '') {
                app(WahaSender::class)->send($whatsapp, $message);
            }
        } catch (Throwable $e) {
            Log::warning('Gagal kirim konfirmasi pembayaran: '.$e->getMessage());
        }
    }

    /**
     * Re-enable the customer's PPP secret once no unpaid overdue bills remain.
     */
    private function liftIsolationIfSettled(Bill $bill): void
    {
        $ticket = $bill->psbTicket;

        if ($ticket?->isolated_at === null) {
            return;
        }

        $stillOverdue = $ticket->bills()
            ->where('status', InvoiceStatus::Unpaid->value)
            ->where('period', '<=', now()->format('Y-m'))
            ->exists();

        if ($stillOverdue) {
            return;
        }

        try {
            /** @var MikrotikPppService $mikrotik */
            $mikrotik = app(MikrotikPppService::class);

            $prospect = $ticket->prospect()->firstOrFail();
            $username = ProspectCredentialService::forProspect($prospect)['username'];

            $secret = PppSecret::query()->where('name', $username)->first();

            if ($secret !== null) {
                // Restore the original profile on Mikrotik + Radius.
                $mikrotik->updateSecret($secret->name, ['profile' => $secret->profile]);

                if ($radiusServer = RadiusServer::default()) {
                    try {
                        RadiusPppService::forServer($radiusServer)
                            ->upsertUser($secret->name, null, $secret->profile);
                    } catch (Throwable) {
                        //
                    }
                }
            }

            $ticket->update(['isolated_at' => null, 'isolated_profile' => null]);
        } catch (Throwable $e) {
            Log::warning('Gagal membuka isolir '.$ticket->ticket_number.': '.$e->getMessage());
        }
    }
}
