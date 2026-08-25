<?php

use App\Models\BillingSetting;
use App\Services\Billing\ProratedInvoiceEstimator;
use Carbon\Carbon;

beforeEach(function () {
    // Defaults matching the worked example: cycle cut 10, PPN 11%, promo 45%.
    BillingSetting::current()->update([
        'prorate_cut_day' => 10,
        'ppn_percent' => 11,
        'promo_percent' => 45,
    ]);
});

test('prorated estimate matches the worked example (24 Aug 2026, Rp166.000)', function () {
    $estimate = (new ProratedInvoiceEstimator)->estimate(
        166000,
        Carbon::parse('2026-08-24'),
    );

    // Cycle 11 Aug – 10 Sep = 31 days; activation covers 18 of them.
    expect($estimate['period_days'])->toBe(31);
    expect($estimate['active_days'])->toBe(18);
    expect($estimate['period_start'])->toBe('2026-08-24');
    expect($estimate['period_end'])->toBe('2026-09-10');

    expect($estimate['prorata'])->toBe(96387);
    expect($estimate['otc_fee'])->toBe(0);
    expect($estimate['discount'])->toBe(43374);
    expect($estimate['dpp'])->toBe(47759);
    expect($estimate['ppn'])->toBe(5254);
    expect($estimate['total_exact'])->toBe(53013);
    expect($estimate['cash_total'])->toBe(54000);
    expect($estimate['cash_rounding'])->toBe(987);
});

test('changing billing settings changes the estimate', function () {
    BillingSetting::current()->update([
        'prorate_cut_day' => 5,
        'ppn_percent' => 0,
        'promo_percent' => 0,
    ]);

    $estimate = (new ProratedInvoiceEstimator)->estimate(
        310000,
        Carbon::parse('2026-08-05'),
    );

    // Cycle 6 Aug – 5 Sep = 31 days; activation covers all of it from the 5th.
    expect($estimate['period_days'])->toBe(31);
    expect($estimate['active_days'])->toBe(31);
    expect($estimate['period_end'])->toBe('2026-09-05');

    // Full month, no discount, no PPN.
    expect($estimate['prorata'])->toBe(310000);
    expect($estimate['discount'])->toBe(0);
    expect($estimate['total_exact'])->toBe(310000);
    expect($estimate['ppn'])->toBe(0);
});

test('activation early in the cycle prorates from activation day', function () {
    // Activated 5 Aug: cycle 11 Jul – 10 Aug, active only 6 days.
    $estimate = (new ProratedInvoiceEstimator)->estimate(
        310000,
        Carbon::parse('2026-08-05'),
    );

    expect($estimate['period_days'])->toBe(31);
    expect($estimate['active_days'])->toBe(6);
    expect($estimate['period_start'])->toBe('2026-08-05');
    expect($estimate['period_end'])->toBe('2026-08-10');

    // 310.000 / 31 * 6 = 60.000 exactly.
    expect($estimate['prorata'])->toBe(60000);

    expect($estimate['discount'])->toBe(27000);
});

test('totals stay consistent between cash and online variants', function () {
    $estimate = (new ProratedInvoiceEstimator)->estimate(
        166000,
        Carbon::parse('2026-08-24'),
    );

    // DPP + PPN equals the exact online total.
    expect($estimate['dpp'] + $estimate['ppn'])->toBe($estimate['total_exact']);

    // Cash total is a multiple of 1000 and never below the exact total.
    expect($estimate['cash_total'] % 1000)->toBe(0);
    expect($estimate['cash_total'])->toBeGreaterThanOrEqual($estimate['total_exact']);
});
