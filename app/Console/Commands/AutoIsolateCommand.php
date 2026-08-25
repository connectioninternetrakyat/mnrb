<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\PsbTicketStatus;
use App\Models\BillingSetting;
use App\Models\PppSecret;
use App\Models\PsbTicket;
use App\Models\RadiusServer;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectCredentialService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class AutoIsolateCommand extends Command
{
    protected $signature = 'billing:auto-isolate';

    protected $description = 'Isolir otomatis pelanggan dengan tagihan tertunggak melewati tanggal & jam isolir';

    public function handle(): int
    {
        $settings = BillingSetting::current();

        if ($settings->isolir_day === null) {
            $this->info('Auto isolir tidak aktif (tanggal isolir kosong).');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();
        $dueDay = min((int) $settings->isolir_day, $now->daysInMonth);
        $dueAt = $now->setDay($dueDay)->setTimeFromTimeString($settings->isolir_hour ?: '23:59');

        if ($now->lessThan($dueAt)) {
            $this->info('Belum waktunya isolir (jatuh tempo '.$dueAt->format('d-m-Y H:i').').');

            return self::SUCCESS;
        }

        // Resolve lazily so tests can swap the RouterOS client.
        try {
            $mikrotik = app(MikrotikPppService::class);
            $this->line('DEBUG class: '.get_class($mikrotik));
        } catch (Throwable $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }

        // Overdue = any unpaid bill up to and including the current period,
        // because the command only runs once the cycle's isolir time passed.
        $isolirProfile = $settings->isolir_profile ?: 'Isolir';
        $radiusServer = RadiusServer::default();
        $radius = null;

        try {
            $radius = $radiusServer !== null
                ? RadiusPppService::forServer($radiusServer)
                : null;
        } catch (Throwable) {
            $radius = null;
        }

        $tickets = PsbTicket::query()
            ->whereNull('isolated_at')
            ->where('status', PsbTicketStatus::Completed)
            ->whereHas('bills', fn ($query) => $query
                ->where('status', InvoiceStatus::Unpaid->value)
                ->where('period', '<=', $now->format('Y-m')))
            ->with(['prospect:id,name,whatsapp,area_code_id,package_id', 'prospect.areaCode:id,code'])
            ->get();

        $this->line("Query menemukan {$tickets->count()} tiket tertunggak.");

        $isolated = 0;

        foreach ($tickets as $ticket) {
            try {
                $username = ProspectCredentialService::forProspect($ticket->prospect)['username'];
                $secret = PppSecret::query()
                    ->where('name', $username)
                    ->first();

                if ($secret === null) {
                    continue;
                }

                // Move the customer to the isolir profile on Mikrotik + Radius.
                $mikrotik->updateSecret($secret->name, ['profile' => $isolirProfile]);

                if ($radius !== null) {
                    $radius->upsertUser($secret->name, null, $isolirProfile);
                }

                $ticket->update([
                    'isolated_at' => now(),
                    'isolated_profile' => $secret->profile,
                ]);
                $isolated++;

                $this->line("Isolated {$secret->name} ({$ticket->ticket_number}) ke profile {$isolirProfile}.");
            } catch (Throwable $e) {
                $this->error("Gagal isolir {$ticket->ticket_number}: ".$e->getMessage());
            }
        }

        $this->info("{$isolated} pelanggan terisolir.");

        return self::SUCCESS;
    }
}
