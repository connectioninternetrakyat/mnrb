<?php

// Billing assumptions of the panel: cycle runs from day 11 to day 10 of the
// next month, prices include PPN, cash payments get rounded up to the step.

return [

    // Day of month the billing cycle closes (period = 11th .. 10th).
    'cycle_cut_day' => 10,

    // PPN applied on top of the DPP extracted from the tax-inclusive total.
    'ppn_percent' => 11,

    // Launching promo discount applied on (prorata + OTC).
    'promo_percent' => 45,

    // Default installation fee (OTC) charged on the first invoice.
    'otc_fee' => 0,

    // Cash payments are rounded up to this multiple.
    'cash_round_step' => 1000,
];
