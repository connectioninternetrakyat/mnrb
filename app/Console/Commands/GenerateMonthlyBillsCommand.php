<?php

namespace App\Console\Commands;

use App\Models\BillingSetting;
use App\Services\Billing\MonthlyBillService;
use Illuminate\Console\Command;

class GenerateMonthlyBillsCommand extends Command
{
    protected $signature = 'billing:generate';

    protected $description = 'Generate tagihan bulan berjalan untuk seluruh pelanggan aktif (idempoten)';

    public function handle(MonthlyBillService $service): int
    {
        $settings = BillingSetting::current();
        $period = now()->format('Y-m');

        if ($settings->last_generated_period === $period) {
            $this->info("Tagihan periode {$period} sudah digenerate sebelumnya.");

            return self::SUCCESS;
        }

        $created = $service->generateForCurrentPeriod();

        $settings->update(['last_generated_period' => $period]);

        $this->info("{$created} tagihan periode {$period} berhasil dibuat.");

        return self::SUCCESS;
    }
}
