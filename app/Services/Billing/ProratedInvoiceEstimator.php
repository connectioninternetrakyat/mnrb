<?php

namespace App\Services\Billing;

use App\Models\BillingSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Estimates the first (activation) invoice of a package: prorated monthly
 * price until the end of the current billing cycle, plus OTC, promo
 * discount and PPN — with the cash rounding variant.
 *
 * Example: Rp166.000/month, activated 24 Aug (cycle 11 Aug – 10 Sep = 31 days,
 * 18 active days): prorata 96.387, promo 45% -43.374, total exact 53.013
 * (DPP 47.759 + PPN 5.254), cash rounded to 54.000 (+987).
 *
 * The cycle cut-off day, PPN and promo discount come from the billing
 * settings managed in the Billing > Setting page.
 */
class ProratedInvoiceEstimator
{
    /**
     * Build the first-invoice estimate.
     *
     * @param  int  $monthlyPrice  Harga paket per bulan.
     * @param  CarbonInterface  $activationDate  Tanggal aktivasi (hari ini saat finishing).
     * @return array<string, int|string>
     */
    public function estimate(int $monthlyPrice, CarbonInterface $activationDate): array
    {
        $activation = Carbon::parse($activationDate->toDateString());
        $settings = BillingSetting::current();

        $cutDay = max(1, min(28, (int) $settings->prorate_cut_day));
        $ppnPercent = (float) $settings->ppn_percent;
        $promoPercent = (int) $settings->promo_percent;
        $otc = (int) config('billing.otc_fee', 0);

        $cycleEnd = $this->cycleEndFor($activation, $cutDay);
        $cycleStart = $this->cycleStartFor($cycleEnd, $cutDay);

        $periodDays = (int) $cycleStart->diffInDays($cycleEnd) + 1;
        $activeDays = max(1, (int) $cycleStart->max($activation)->diffInDays($cycleEnd) + 1);

        $prorata = (int) floor($monthlyPrice / $periodDays * $activeDays);
        $discount = (int) floor(($prorata + $otc) * $promoPercent / 100);

        // Prices are tax inclusive: extract DPP from the exact total.
        $totalExact = $prorata + $otc - $discount;
        $dpp = (int) floor($totalExact / (1 + $ppnPercent / 100));
        $ppn = $totalExact - $dpp;

        $roundStep = max(0, (int) config('billing.cash_round_step', 1000));
        $cashTotal = $roundStep > 0 ? (int) ceil($totalExact / $roundStep) * $roundStep : $totalExact;

        return [
            'monthly_price' => $monthlyPrice,
            'period_days' => $periodDays,
            'active_days' => $activeDays,
            'prorata' => $prorata,
            'otc_fee' => $otc,
            'promo_percent' => $promoPercent,
            'discount' => $discount,
            'dpp' => $dpp,
            'ppn' => $ppn,
            'total_exact' => $totalExact,
            'cash_total' => $cashTotal,
            'cash_rounding' => $cashTotal - $totalExact,
            'period_start' => $cycleStart->max($activation)->toDateString(),
            'period_end' => $cycleEnd->toDateString(),
        ];
    }

    /**
     * The closing day of the cycle containing the activation date.
     * Activating exactly on the cut-off day starts the fresh cycle instead.
     */
    private function cycleEndFor(Carbon $activation, int $cutDay): Carbon
    {
        if ($activation->day < $cutDay) {
            $day = min($cutDay, $activation->daysInMonth);

            return $activation->copy()->setDay($day);
        }

        $nextMonth = $activation->copy()->addMonthNoOverflow();
        $day = min($cutDay, $nextMonth->daysInMonth);

        return $nextMonth->setDay($day);
    }

    /**
     * The opening day (cut day + 1) of the cycle closing on the given day.
     */
    private function cycleStartFor(Carbon $cycleEnd, int $cutDay): Carbon
    {
        $previousMonth = $cycleEnd->copy()->subMonthsNoOverflow();
        $day = min($cutDay + 1, $previousMonth->daysInMonth);

        return $previousMonth->setDay($day);
    }
}
