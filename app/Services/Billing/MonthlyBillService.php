<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PsbTicketStatus;
use App\Models\Bill;
use App\Models\BillingSetting;
use App\Models\PsbTicket;
use Illuminate\Support\Facades\DB;

/**
 * Generates the recurring monthly bills of every completed PSB ticket
 * for the current period. Prices are tax inclusive: the PPN is split out
 * from the package price using the configured rate. No promo discount —
 * that only applies to the first (activation) invoice.
 */
class MonthlyBillService
{
    /**
     * Create the missing bills of the current period.
     *
     * @return int Number of bills created.
     */
    public function generateForCurrentPeriod(): int
    {
        $settings = BillingSetting::current();
        $period = now()->format('Y-m');
        $created = 0;

        $tickets = PsbTicket::query()
            ->where('status', PsbTicketStatus::Completed)
            ->whereDoesntHave('bills', fn ($query) => $query->where('period', $period))
            ->with([
                'prospect.package:id,price',
                'pelanggan.package:id,price',
            ])
            ->get();

        foreach ($tickets as $ticket) {
            // The pelanggan's CURRENT package wins: upgrades and downgrades
            // made in customer management flow into the next bills.
            $monthlyPrice = $ticket->billableMonthlyPrice();

            if ($monthlyPrice <= 0) {
                continue;
            }

            DB::transaction(function () use ($ticket, $settings, $period, $monthlyPrice, &$created): void {
                $dpp = (int) floor($monthlyPrice / (1 + ((float) $settings->ppn_percent) / 100));

                Bill::create([
                    'bill_number' => Bill::generateNumber($period),
                    'psb_ticket_id' => $ticket->id,
                    'period' => $period,
                    'monthly_price' => $monthlyPrice,
                    'dpp' => $dpp,
                    'ppn' => $monthlyPrice - $dpp,
                    'total' => $monthlyPrice,
                    'status' => InvoiceStatus::Unpaid->value,
                ]);

                $created++;
            });
        }

        return $created;
    }
}
