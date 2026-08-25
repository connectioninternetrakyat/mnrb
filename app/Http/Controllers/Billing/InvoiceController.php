<?php

namespace App\Http\Controllers\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Waha\TemplateRenderer;
use App\Services\Waha\WahaSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /**
     * Display the activation invoices.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::BillingView->value);

        $search = trim((string) request('search'));
        $status = InvoiceStatus::tryFrom((string) request('status'));

        $invoices = Invoice::query()
            ->with([
                'psbTicket:id,ticket_number,prospect_id',
                'psbTicket.prospect:id,name,whatsapp,area_code_id,package_id',
                'psbTicket.prospect.package:id,name',
                // Managed customer record wins for display data.
                'psbTicket.pelanggan:id,name,whatsapp,package_id,psb_ticket_id',
                'psbTicket.pelanggan.package:id,name',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereLike('invoice_number', "%{$search}%", false)
                    ->orWhereLike('va_number', "%{$search}%", false)
                    ->orWhereHas('psbTicket.prospect', fn ($q) => $q->whereLike('name', "%{$search}%", false))
                    ->orWhereHas('psbTicket.pelanggan', fn ($q) => $q->whereLike('name', "%{$search}%", false));
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status->value,
                'payment_method' => $invoice->payment_method,
                'bank' => $invoice->bank,
                'va_number' => $invoice->va_number,
                'total_exact' => $invoice->total_exact,
                'cash_total' => $invoice->cash_total,
                'paid_at' => $invoice->paid_at?->toISOString(),
                'created_at' => $invoice->created_at?->toISOString(),
                'ticket_id' => $invoice->psbTicket?->id,
                'ticket_number' => $invoice->psbTicket?->ticket_number,
                'customer_name' => $invoice->psbTicket?->customerName(),
                'customer_whatsapp' => $invoice->psbTicket?->contactWhatsapp(),
                'package_name' => $invoice->psbTicket?->customerPackageName(),
            ]);

        return Inertia::render('billing/invoices', [
            'invoices' => $invoices,
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
            ],
        ]);
    }

    /**
     * Manually confirm an unpaid invoice as paid (e.g. direct transfer).
     */
    public function markPaid(Invoice $invoice): RedirectResponse
    {
        Gate::authorize(UserPermission::BillingUpdate->value);

        if ($invoice->status === InvoiceStatus::Paid) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Invoice :number sudah lunas.', ['number' => $invoice->invoice_number])]);

            return back();
        }

        $invoice->markAsPaid();
        $this->notifyInvoicePayment($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number ditandai lunas.', ['number' => $invoice->invoice_number])]);

        return back();
    }

    /**
     * Send the payment confirmation of an activation invoice.
     */
    private function notifyInvoicePayment(Invoice $invoice): void
    {
        try {
            $ticket = $invoice->psbTicket;
            $whatsapp = $ticket?->contactWhatsapp();

            if ($whatsapp === null) {
                return;
            }

            $message = TemplateRenderer::renderSlug('pembayaran', '', [
                'nama' => $ticket->customerName() ?? '',
                'tagihan' => $invoice->invoice_number,
                'periode' => 'Aktivasi',
                'total' => 'Rp '.number_format((float) $invoice->total_exact, 0, ',', '.'),
                'metode' => ucfirst($invoice->payment_method),
            ]);

            if ($message !== '') {
                app(WahaSender::class)->send($whatsapp, $message);
            }
        } catch (Throwable $e) {
            Log::warning('Gagal kirim konfirmasi invoice: '.$e->getMessage());
        }
    }
}
