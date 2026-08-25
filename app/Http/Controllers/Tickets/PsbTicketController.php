<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PelangganStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\PsbCancelRequest;
use App\Http\Requests\Tickets\PsbFinishRequest;
use App\Http\Requests\Tickets\PsbProgressRequest;
use App\Models\Invoice;
use App\Models\MidtransAccount;
use App\Models\PppSecret;
use App\Models\Prospect;
use App\Models\PsbTicket;
use App\Models\RadiusServer;
use App\Services\Billing\MidtransVaService;
use App\Services\Billing\ProratedInvoiceEstimator;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectCredentialService;
use App\Services\Waha\TemplateRenderer;
use App\Services\Waha\WahaSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PsbTicketController extends Controller
{
    /**
     * Non-privileged users may only touch tickets assigned to them or
     * brought in by them (via the prospect).
     */
    private function authorizeOwnership(PsbTicket $ticket): void
    {
        $user = request()->user();

        if ($user->seesAllData() || $ticket->user_id === $user->id) {
            return;
        }

        if ($ticket->prospect !== null && $ticket->prospect->user_id === $user->id) {
            return;
        }

        abort(403, __('Anda hanya dapat mengakses tiket milik Anda sendiri.'));
    }

    /**
     * Display the PSB tickets.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::TicketsView->value);

        $user = $request->user();
        $search = trim((string) request('search'));
        $status = PsbTicketStatus::tryFrom((string) request('status'));

        // Teknisi & marketing only see tickets assigned to / brought by them.
        $tickets = PsbTicket::query()
            ->when(! $user->seesAllData(), fn ($query) => $query->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('prospect', fn ($q) => $q->where('user_id', $user->id));
            }))
            ->with([
                'prospect:id,name,whatsapp,address,area_code_id,package_id',
                'prospect.areaCode:id,code,name',
                'prospect.package:id,name,price',
                'assignedTo:id,name',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereLike('ticket_number', "%{$search}%", false)
                    ->orWhereHas('prospect', fn ($q) => $q->whereLike('name', "%{$search}%", false))
                    ->orWhereHas('assignedTo', fn ($q) => $q->whereLike('name', "%{$search}%", false));
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (PsbTicket $ticket): array => $this->shapeTicket($ticket));

        return Inertia::render('tickets/psb/index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
            ],
        ]);
    }

    /**
     * Display the ticket detail with prospect + secret info.
     */
    public function show(Request $request, PsbTicket $ticket): Response
    {
        Gate::authorize(UserPermission::TicketsView->value);
        $this->authorizeOwnership($ticket);

        return Inertia::render('tickets/psb/detail', [
            'ticket' => $this->shapeDetail($ticket),
        ]);
    }

    /**
     * Display the progress page of the ticket.
     */
    public function progress(Request $request, PsbTicket $ticket): Response
    {
        Gate::authorize(UserPermission::TicketsUpdate->value);
        $this->authorizeOwnership($ticket);

        if (! $ticket->isOpen()) {
            return $this->redirectClosed($ticket);
        }

        return Inertia::render('tickets/psb/progress', [
            'ticket' => $this->shapeDetail($ticket),
        ]);
    }

    /**
     * Mark the installation as started.
     */
    public function storeProgress(PsbProgressRequest $request, PsbTicket $ticket): RedirectResponse
    {
        $this->authorizeOwnership($ticket);
        if (! $ticket->isOpen()) {
            return $this->redirectClosed($ticket);
        }

        $ticket->update([
            'started_at' => $ticket->started_at ?? now(),
            'status' => PsbTicketStatus::InProgress->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tiket :number kini dikerjakan.', ['number' => $ticket->ticket_number])]);

        return to_route('tickets.psb.show', $ticket);
    }

    /**
     * Display the close / finishing page of the ticket.
     */
    public function finish(Request $request, PsbTicket $ticket): Response
    {
        Gate::authorize(UserPermission::TicketsUpdate->value);
        $this->authorizeOwnership($ticket);

        if ($ticket->status !== PsbTicketStatus::InProgress) {
            return $this->redirectClosed($ticket);
        }

        $ticket->loadMissing('prospect.package');

        return Inertia::render('tickets/psb/finish', [
            'ticket' => $this->shapeDetail($ticket),
            'estimate' => (new ProratedInvoiceEstimator)->estimate(
                (int) ($ticket->prospect?->package?->price ?? 0),
                now(),
            ),
        ]);
    }

    /**
     * Close the ticket as finished, issue its activation invoice and set
     * the payment status: cash is paid immediately, transfer waits for the
     * automatic verification of the virtual account payment.
     */
    public function storeFinish(PsbFinishRequest $request, PsbTicket $ticket): RedirectResponse
    {
        $this->authorizeOwnership($ticket);
        if ($ticket->status !== PsbTicketStatus::InProgress) {
            return $this->redirectClosed($ticket);
        }

        $paymentMethod = $request->validated('payment_method');

        $ticket->update([
            'finish_note' => $request->validated('finish_note'),
            'payment_method' => $paymentMethod,
            'check_pppoe_account' => $request->boolean('check_pppoe_account'),
            'check_customer_active' => $request->boolean('check_customer_active'),
            'check_vlan_tr069' => $request->boolean('check_vlan_tr069'),
            'finished_at' => now(),
            'status' => PsbTicketStatus::Completed->value,
        ]);

        // Issue the activation invoice: cash = lunas instantly, transfer =
        // menunggu pembayaran until the VA payment is verified.
        $invoice = $this->issueInvoice($ticket, $paymentMethod, (string) $request->validated('bank', ''));

        // Real gateway integration: transfer invoices get a REAL Midtrans
        // virtual account. Failure keeps the offline fallback VA so the
        // flow never breaks.
        if ($paymentMethod === 'transfer') {
            $this->attachMidtransVa($invoice, (string) $request->validated('bank', ''));
        }

        // Customer notice: pemasangan selesai + ringkasan tagihan via WA.
        $this->notifyCompletion($ticket, $invoice);

        // Real integration: activation flows back into the customer
        // registries (FTTH pelanggan + billing customer).
        $this->syncCustomerRegistries($ticket, activated: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tiket :number selesai.', ['number' => $ticket->ticket_number])]);

        return to_route('tickets.psb.show', $ticket);
    }

    /**
     * Push the ticket lifecycle into both customer registries: the FTTH
     * pelanggan record and the billing customer record. Activation stamps
     * activated_at; cancellation stops the phantom active customer.
     */
    private function syncCustomerRegistries(PsbTicket $ticket, bool $activated): void
    {
        $ticket->pelanggan()->update([
            'status' => $activated
                ? PelangganStatus::Active->value
                : PelangganStatus::Stopped->value,
            ...($activated ? ['activated_at' => now()] : []),
        ]);

        $ticket->billingCustomer()->update([
            'status' => $activated
                ? CustomerStatus::Active->value
                : CustomerStatus::Suspended->value,
        ]);
    }

    /**
     * WhatsApp the completion notice to the prospect when a ticket is
     * closed: pemasangan selesai + ringkasan tagihan aktivasi.
     */
    private function notifyCompletion(PsbTicket $ticket, Invoice $invoice): void
    {
        try {
            $whatsapp = $ticket->contactWhatsapp();

            if ($whatsapp === null) {
                return;
            }

            $prospect = $ticket->prospect;
            $total = $invoice->payment_method === 'tunai'
                ? (float) $invoice->cash_total
                : (float) $invoice->total_exact;

            $message = TemplateRenderer::renderSlug('selesai', '', [
                'nama' => $prospect?->name ?? '',
                'tiket' => $ticket->ticket_number,
                'paket' => $prospect?->package?->name ?? '',
                'teknisi' => $ticket->assignedTo?->name ?? '-',
                'tanggal' => optional($ticket->finished_at ?? now())
                    ->locale('id')->isoFormat('dddd, D MMMM YYYY HH:mm'),
                'invoice' => $invoice->invoice_number,
                'total' => 'Rp '.number_format($total, 0, ',', '.'),
                'metode' => ucfirst((string) ($invoice->payment_method ?? '')),
            ]);

            if ($message !== '') {
                app(WahaSender::class)->send($whatsapp, $message);
            }
        } catch (Throwable $e) {
            // WAHA not configured or unreachable: the ticket stays closed.
            Log::warning('Gagal kirim notifikasi penyelesaian PSB: '.$e->getMessage(), [
                'tiket' => $ticket->ticket_number,
            ]);
        }
    }

    /**
     * Replace the fallback VA of a transfer invoice with a REAL Midtrans
     * virtual account charged through the active account.
     */
    private function attachMidtransVa(Invoice $invoice, string $bank): void
    {
        $account = MidtransAccount::query()
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if ($account === null) {
            Log::warning('Midtrans VA dilewati: tidak ada akun Midtrans aktif.', [
                'invoice' => $invoice->invoice_number,
            ]);

            return;
        }

        try {
            $va = app(MidtransVaService::class)->charge(
                $account,
                $invoice,
                $bank,
                (string) $invoice->psbTicket?->prospect?->name,
                $invoice->psbTicket?->prospect?->whatsapp,
            );

            $invoice->update(['va_number' => $va['va_number']]);
        } catch (Throwable $e) {
            // Offline fallback VA stays in place; webhook still completes it.
            Log::warning('Midtrans VA gagal: '.$e->getMessage(), [
                'invoice' => $invoice->invoice_number,
            ]);
        }
    }

    /**
     * Create the activation invoice of a finished ticket.
     */
    private function issueInvoice(PsbTicket $ticket, string $paymentMethod, string $bank): Invoice
    {
        $ticket->loadMissing('prospect.package');

        $estimate = (new ProratedInvoiceEstimator)->estimate(
            (int) ($ticket->prospect?->package?->price ?? 0),
            now(),
        );

        $isCash = $paymentMethod === 'tunai';

        return $ticket->invoice()->create([
            'invoice_number' => Invoice::generateNumber(),
            'payment_method' => $paymentMethod,
            'bank' => ! $isCash && $bank !== '' ? $bank : null,
            'va_number' => ! $isCash && $bank !== ''
                ? Invoice::makeVaNumber($bank, $ticket->prospect?->whatsapp)
                : null,
            'monthly_price' => $estimate['monthly_price'],
            'period_days' => $estimate['period_days'],
            'active_days' => $estimate['active_days'],
            'prorata' => $estimate['prorata'],
            'otc_fee' => $estimate['otc_fee'],
            'promo_percent' => $estimate['promo_percent'],
            'discount' => $estimate['discount'],
            'dpp' => $estimate['dpp'],
            'ppn' => $estimate['ppn'],
            'total_exact' => $estimate['total_exact'],
            'cash_total' => $estimate['cash_total'],
            'status' => $isCash ? InvoiceStatus::Paid->value : InvoiceStatus::Unpaid->value,
            'paid_at' => $isCash ? now() : null,
        ]);
    }

    /**
     * Cancel the ticket. A reason is mandatory.
     */
    public function cancel(PsbCancelRequest $request, PsbTicket $ticket): RedirectResponse
    {
        $this->authorizeOwnership($ticket);
        if (! $ticket->isOpen()) {
            return $this->redirectClosed($ticket);
        }

        $ticket->update([
            'cancel_reason' => $request->validated('cancel_reason'),
            'cancelled_at' => now(),
            'status' => PsbTicketStatus::Cancelled->value,
        ]);

        // Real integration: a cancelled installation must not leave a
        // phantom active customer behind in either registry.
        $this->syncCustomerRegistries($ticket, activated: false);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tiket :number dibatalkan.', ['number' => $ticket->ticket_number])]);

        return to_route('tickets.psb.show', $ticket);
    }

    /**
     * Redirect back to the detail page with a warning for closed tickets.
     */
    private function redirectClosed(PsbTicket $ticket): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __('Tiket :number sudah diproses (selesai/dibatalkan).', ['number' => $ticket->ticket_number])]);

        return to_route('tickets.psb.show', $ticket);
    }

    /**
     * Shape a ticket for list views.
     *
     * @return array<string, mixed>
     */
    private function shapeTicket(PsbTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'scheduled_at' => $ticket->scheduled_at?->toISOString(),
            'status' => $ticket->status->value,
            'assigned_to' => $ticket->assignedTo?->name,
            'prospect_name' => $ticket->prospect?->name,
            'prospect_whatsapp' => $ticket->prospect?->whatsapp,
            'prospect_address' => $ticket->prospect?->address,
            'area_code' => $ticket->prospect?->areaCode?->code,
            'package_name' => $ticket->prospect?->package?->name,
        ];
    }

    /**
     * Shape a ticket for the detail/progress/finish pages, including the
     * PPP secret created from the prospect approval.
     *
     * @return array<string, mixed>
     */
    private function shapeDetail(PsbTicket $ticket): array
    {
        $prospect = $ticket->prospect()->with([
            'areaCode:id,code,name',
            'package:id,name,price,ppp_profile_id',
            'package.pppProfile:id,name,rate_limit',
            'broughtBy:id,name',
        ])->firstOrFail();

        $credentials = ProspectCredentialService::forProspect($prospect);

        $secret = PppSecret::query()
            ->where('name', $credentials['username'])
            ->first(['id', 'name', 'profile', 'disabled_on_mikrotik', 'password']);

        return [
            ...$this->shapeTicket($ticket),
            'created_at' => $ticket->created_at?->toISOString(),
            'started_at' => $ticket->started_at?->toISOString(),
            'finished_at' => $ticket->finished_at?->toISOString(),
            'cancelled_at' => $ticket->cancelled_at?->toISOString(),
            'progress_note' => $ticket->progress_note,
            'finish_note' => $ticket->finish_note,
            'cancel_reason' => $ticket->cancel_reason,
            'payment_method' => $ticket->payment_method,
            'check_pppoe_account' => $ticket->check_pppoe_account,
            'check_customer_active' => $ticket->check_customer_active,
            'check_vlan_tr069' => $ticket->check_vlan_tr069,
            'invoice' => $ticket->invoice ? [
                'invoice_number' => $ticket->invoice->invoice_number,
                'status' => $ticket->invoice->status->value,
                'payment_method' => $ticket->invoice->payment_method,
                'bank' => $ticket->invoice->bank,
                'va_number' => $ticket->invoice->va_number,
                'total_exact' => $ticket->invoice->total_exact,
                'cash_total' => $ticket->invoice->cash_total,
                'paid_at' => $ticket->invoice->paid_at?->toISOString(),
            ] : null,
            'check_pppoe_account' => $ticket->check_pppoe_account,
            'check_customer_active' => $ticket->check_customer_active,
            'check_vlan_tr069' => $ticket->check_vlan_tr069,
            'prospect_id' => $prospect->id,
            'prospect_email' => $prospect->email,
            'area_name' => $prospect->areaCode?->name,
            'package_price' => $prospect->package?->price,
            'profile_name' => $prospect->package?->pppProfile?->name,
            'brought_by' => $prospect->broughtBy?->name,
            'maps_url' => $prospect->maps_url,
            'latitude' => $prospect->latitude,
            'longitude' => $prospect->longitude,
            'note' => $prospect->note,
            'secret_username' => $secret?->name ?? $credentials['username'],
            'secret_password' => $secret?->password ?? $credentials['password'],
            'secret_profile' => $secret?->profile,
            'radius_session' => $this->radiusSession($credentials['username']),
        ];
    }

    /**
     * Live RADIUS session of the username; null when radius is unreachable.
     *
     * @return array{online: bool, framed_ip: string|null, mac: string|null, nas_ip: string|null, uptime_seconds: int, started_at: string|null}|null
     */
    private function radiusSession(string $username): ?array
    {
        try {
            $server = RadiusServer::default();

            if ($server === null) {
                return null;
            }

            return RadiusPppService::forServer($server)->activeSession($username);
        } catch (Throwable) {
            return null;
        }
    }
}
