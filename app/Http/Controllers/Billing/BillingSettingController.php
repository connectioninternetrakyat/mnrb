<?php

namespace App\Http\Controllers\Billing;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\BillingSettingRequest;
use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettingController extends Controller
{
    /**
     * Display the billing settings form.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::BillingView->value);

        $settings = BillingSetting::current();

        return Inertia::render('billing/settings', [
            'settings' => [
                'prorate_cut_day' => (int) $settings->prorate_cut_day,
                'invoice_day' => (int) $settings->invoice_day,
                'isolir_day' => $settings->isolir_day !== null ? (int) $settings->isolir_day : null,
                'isolir_hour' => $settings->isolir_hour ?? '23:59',
                'isolir_profile' => $settings->isolir_profile ?? 'Isolir',
                'ppn_percent' => (int) $settings->ppn_percent,
                'promo_percent' => (int) $settings->promo_percent,
                'remind_h3' => (bool) $settings->remind_h3,
                'remind_h2' => (bool) $settings->remind_h2,
                'remind_h1' => (bool) $settings->remind_h1,
            ],
        ]);
    }

    /**
     * Update the billing settings.
     */
    public function update(BillingSettingRequest $request): RedirectResponse
    {
        // Unchecked checkboxes are absent from the payload: default to false.
        BillingSetting::current()->update([
            ...$request->validated(),
            'isolir_hour' => $request->validated('isolir_hour') ?? '23:59',
            'isolir_profile' => $request->validated('isolir_profile') ?: 'Isolir',
            'remind_h3' => $request->boolean('remind_h3'),
            'remind_h2' => $request->boolean('remind_h2'),
            'remind_h1' => $request->boolean('remind_h1'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pengaturan tagihan berhasil diperbarui.')]);

        return back();
    }
}
