<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Billing\BillController;
use App\Http\Controllers\Billing\BillingSettingController;
use App\Http\Controllers\Billing\CustomerController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Ftth\AreaCodeController;
use App\Http\Controllers\Ftth\MapsUrlResolverController;
use App\Http\Controllers\Ftth\OdpOdcController;
use App\Http\Controllers\Ftth\OnuController;
use App\Http\Controllers\Gateways\MidtransAccountController;
use App\Http\Controllers\Gateways\MidtransWebhookController;
use App\Http\Controllers\Gateways\WahaServerController;
use App\Http\Controllers\Gateways\WahaWebhookController;
use App\Http\Controllers\Pelanggan\PelangganController;
use App\Http\Controllers\Waha\BroadcastController;
use App\Http\Controllers\Waha\TemplateController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\Ppp\PppActiveController;
use App\Http\Controllers\Ppp\PppProfileController;
use App\Http\Controllers\Ppp\PppSecretController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\Servers\GenieacsServerController;
use App\Http\Controllers\Servers\MikrotikServerController;
use App\Http\Controllers\Servers\RadiusServerController;
use App\Http\Controllers\Tickets\PsbTicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Gateway webhooks are public endpoints verified by signature / API key.
Route::post('webhooks/midtrans', MidtransWebhookController::class)->name('webhooks.midtrans');
Route::post('webhooks/waha', WahaWebhookController::class)->name('webhooks.waha');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('pelanggan', [PelangganController::class, 'index'])
        ->name('pelanggan.index');
    Route::get('pelanggan/{pelanggan}', [PelangganController::class, 'show'])
        ->name('pelanggan.show');
    Route::post('pelanggan', [PelangganController::class, 'store'])
        ->name('pelanggan.store');
    Route::put('pelanggan/{pelanggan}', [PelangganController::class, 'update'])
        ->name('pelanggan.update');
    Route::delete('pelanggan/{pelanggan}', [PelangganController::class, 'destroy'])
        ->name('pelanggan.destroy');
    Route::post('pelanggan/{pelanggan}/stop', [PelangganController::class, 'stop'])
        ->name('pelanggan.stop');
    Route::post('pelanggan/{pelanggan}/activate', [PelangganController::class, 'activate'])
        ->name('pelanggan.activate');

    Route::resource('prospects', ProspectController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('prospects');
    Route::post('prospects/{prospect}/approve', [ProspectController::class, 'approve'])
        ->name('prospects.approve');
    Route::post('prospects/{prospect}/reject', [ProspectController::class, 'reject'])
        ->name('prospects.reject');

    Route::resource('packages', PackageController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('packages');

    Route::prefix('ppp')->name('ppp.')->group(function () {
        Route::get('profiles', [PppProfileController::class, 'index'])->name('profile.index');
        Route::post('profiles', [PppProfileController::class, 'store'])->name('profile.store');
        Route::put('profiles/{profile}', [PppProfileController::class, 'update'])->name('profile.update');
        Route::delete('profiles/{profile}', [PppProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('profiles/sync', [PppProfileController::class, 'syncFromMikrotik'])->name('profile.sync');

        Route::get('secrets', [PppSecretController::class, 'index'])->name('secret.index');
        Route::post('secrets', [PppSecretController::class, 'store'])->name('secret.store');
        Route::put('secrets/{secret}', [PppSecretController::class, 'update'])->name('secret.update');
        Route::delete('secrets/{secret}', [PppSecretController::class, 'destroy'])->name('secret.destroy');

        Route::get('actives', [PppActiveController::class, 'index'])->name('active.index');
        Route::delete('actives/{session}', [PppActiveController::class, 'destroy'])
            ->where('session', '.*')
            ->name('active.destroy');
    });

    Route::resource('ftth/area-codes', AreaCodeController::class)
        ->parameters(['area-codes' => 'area_code'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('ftth.area-codes');

    Route::resource('ftth/odp-odcs', OdpOdcController::class)
        ->parameters(['odp-odcs' => 'odp'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('ftth.odp-odc');

    Route::post('ftth/maps/resolve', MapsUrlResolverController::class)
        ->name('ftth.maps.resolve');

    Route::get('billing/settings', [BillingSettingController::class, 'index'])
        ->name('billing.setting.index');
    Route::put('billing/settings', [BillingSettingController::class, 'update'])
        ->name('billing.setting.update');

    Route::get('billing/invoices', [InvoiceController::class, 'index'])
        ->name('billing.invoice.index');
    Route::post('billing/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])
        ->name('billing.invoice.mark-paid');

    Route::get('billing/bills', [BillController::class, 'index'])
        ->name('billing.bill.index');
    Route::post('billing/bills/generate', [BillController::class, 'generate'])
        ->name('billing.bill.generate');
    Route::post('billing/bills/{bill}/mark-paid', [BillController::class, 'markPaid'])
        ->name('billing.bill.mark-paid');

    Route::get('billing/reports', [\App\Http\Controllers\Billing\FinancialReportController::class, 'index'])
        ->name('billing.report.index');
    Route::get('billing/reports/download', [\App\Http\Controllers\Billing\FinancialReportController::class, 'download'])
        ->name('billing.report.download');

    Route::get('billing/customers', [CustomerController::class, 'index'])
        ->name('billing.customer.index');
    Route::get('billing/customers/{customer}', [CustomerController::class, 'show'])
        ->name('billing.customer.show');
    Route::post('billing/customers', [CustomerController::class, 'store'])
        ->name('billing.customer.store');
    Route::put('billing/customers/{customer}', [CustomerController::class, 'update'])
        ->name('billing.customer.update');
    Route::delete('billing/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('billing.customer.destroy');
    Route::post('billing/customers/{customer}/suspend', [CustomerController::class, 'suspend'])
        ->name('billing.customer.suspend');
    Route::post('billing/customers/{customer}/activate', [CustomerController::class, 'activate'])
        ->name('billing.customer.activate');
    Route::post('billing/customers/{customer}/upgrade', [CustomerController::class, 'upgrade'])
        ->name('billing.customer.upgrade');
    Route::get('billing/customers/{customer}/bills/download', [CustomerController::class, 'downloadBills'])
        ->name('billing.customer.bills-download');

    Route::prefix('wa')->name('wa.')->group(function () {
        Route::get('templates', [TemplateController::class, 'index'])
            ->name('template.index');
        Route::post('templates', [TemplateController::class, 'store'])
            ->name('template.store');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])
            ->name('template.destroy');

        Route::get('broadcasts', [BroadcastController::class, 'index'])
            ->name('broadcast.index');
        Route::post('broadcasts', [BroadcastController::class, 'store'])
            ->name('broadcast.store');
    });

    Route::prefix('tickets/psb')->name('tickets.psb.')->group(function () {
        Route::get('/', [PsbTicketController::class, 'index'])->name('index');
        Route::get('/{ticket}', [PsbTicketController::class, 'show'])->name('show');
        Route::get('/{ticket}/progress', [PsbTicketController::class, 'progress'])->name('progress');
        Route::post('/{ticket}/progress', [PsbTicketController::class, 'storeProgress'])->name('progress.store');
        Route::get('/{ticket}/finish', [PsbTicketController::class, 'finish'])->name('finish');
        Route::post('/{ticket}/finish', [PsbTicketController::class, 'storeFinish'])->name('finish.store');
        Route::post('/{ticket}/cancel', [PsbTicketController::class, 'cancel'])->name('cancel');
    });

    Route::get('ftth/onu', [OnuController::class, 'index'])->name('ftth.onu.index');
    Route::get('ftth/onu/{onu}', [OnuController::class, 'show'])->name('ftth.onu.show');
    Route::post('ftth/onu', [OnuController::class, 'store'])->name('ftth.onu.store');
    Route::put('ftth/onu/{onu}', [OnuController::class, 'update'])->name('ftth.onu.update');
    Route::delete('ftth/onu/{onu}', [OnuController::class, 'destroy'])->name('ftth.onu.destroy');
    Route::post('ftth/onu/{onu}/reboot', [OnuController::class, 'reboot'])->name('ftth.onu.reboot');
    Route::post('ftth/onu/{onu}/refresh', [OnuController::class, 'refresh'])->name('ftth.onu.refresh');
    Route::post('ftth/onu/{onu}/wan', [OnuController::class, 'addWan'])->name('ftth.onu.wan.add');
    Route::put('ftth/onu/{onu}/wan', [OnuController::class, 'editWan'])->name('ftth.onu.wan.edit');
    Route::post('ftth/onu/{onu}/wlan', [OnuController::class, 'addWlan'])->name('ftth.onu.wlan.add');
    Route::put('ftth/onu/{onu}/wlan', [OnuController::class, 'editWlan'])->name('ftth.onu.wlan.edit');

    Route::resource('gateways/midtrans', MidtransAccountController::class)
        ->parameters(['midtrans' => 'account'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('gateways.midtrans');

    Route::resource('gateways/waha', WahaServerController::class)
        ->parameters(['waha' => 'server'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('gateways.waha');
    Route::post('gateways/waha/{server}/test', [WahaServerController::class, 'test'])
        ->name('gateways.waha.test');
    Route::get('gateways/waha/{server}/qr', [WahaServerController::class, 'qr'])
        ->name('gateways.waha.qr');

    Route::resource('servers/mikrotik', MikrotikServerController::class)
        ->parameters(['mikrotik' => 'server'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('servers.mikrotik');
    Route::post('servers/mikrotik/{server}/test', [MikrotikServerController::class, 'test'])
        ->name('servers.mikrotik.test');
    Route::post('servers/mikrotik/{server}/default', [MikrotikServerController::class, 'setDefault'])
        ->name('servers.mikrotik.default');
    Route::get('servers/mikrotik/{server}/detail', [MikrotikServerController::class, 'detail'])
        ->name('servers.mikrotik.detail');

    Route::resource('servers/radius', RadiusServerController::class)
        ->parameters(['radius' => 'server'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('servers.radius');
    Route::post('servers/radius/{server}/test-radius', [RadiusServerController::class, 'testRadius'])
        ->name('servers.radius.test-radius');
    Route::post('servers/radius/{server}/test-database', [RadiusServerController::class, 'testDatabase'])
        ->name('servers.radius.test-database');
    Route::post('servers/radius/{server}/default', [RadiusServerController::class, 'setDefault'])
        ->name('servers.radius.default');

    Route::resource('servers/genieacs', GenieacsServerController::class)
        ->parameters(['genieacs' => 'server'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('servers.genieacs');
    Route::post('servers/genieacs/{server}/test', [GenieacsServerController::class, 'test'])
        ->name('servers.genieacs.test');
    Route::get('servers/genieacs/{server}/detail', [GenieacsServerController::class, 'detail'])
        ->name('servers.genieacs.detail');

    Route::resource('users', UserController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('users');

    Route::resource('roles', RoleController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('roles');

    Route::get('permissions', [RolePermissionController::class, 'index'])
        ->name('permissions.index');
    Route::put('permissions/{role}', [RolePermissionController::class, 'update'])
        ->name('permissions.update');
});

require __DIR__.'/settings.php';
