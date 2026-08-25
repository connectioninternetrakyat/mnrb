<?php

namespace App\Console\Commands;

use App\Enums\CustomerStatus;
use App\Enums\PelangganStatus;
use App\Enums\PsbTicketStatus;
use App\Models\Customer;
use App\Models\Pelanggan;
use App\Models\PppSecret;
use App\Models\PsbTicket;
use App\Services\Prospect\ProspectCredentialService;
use Illuminate\Console\Command;

/**
 * Backfills the customer registries (FTTH pelanggan + billing customer)
 * for PSB tickets created before the real integration existed, so every
 * historical prospect/ticket becomes visible in both management modules.
 */
class BackfillCustomerRegistriesCommand extends Command
{
    protected $signature = 'psb:sync-customers';

    protected $description = 'Sinkronisasi tiket PSB lama ke data Pelanggan & Customer billing (backfill)';

    public function handle(): int
    {
        $tickets = PsbTicket::query()
            ->with(['prospect.areaCode:id,code,name', 'prospect.package:id,name'])
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('Tidak ada tiket PSB untuk disinkronkan.');

            return self::SUCCESS;
        }

        $pelangganCreated = 0;
        $customerCreated = 0;
        $updated = 0;

        foreach ($tickets as $ticket) {
            $prospect = $ticket->prospect;

            if ($prospect === null) {
                $this->warn("Tiket {$ticket->ticket_number} dilewati: prospect tidak ditemukan.");

                continue;
            }

            // Completed -> aktif + activated_at; Cancelled -> berhenti/ditangguhkan;
            // open tickets keep the legacy active state until they close.
            $completed = $ticket->status === PsbTicketStatus::Completed;
            $cancelled = $ticket->status === PsbTicketStatus::Cancelled;

            $pelanggan = Pelanggan::query()->firstOrNew(['psb_ticket_id' => $ticket->id]);

            if (! $pelanggan->exists) {
                $pelanggan->fill([
                    'name' => $prospect->name,
                    'email' => $prospect->email,
                    'whatsapp' => $prospect->whatsapp,
                    'address' => $prospect->address,
                    'maps_url' => $prospect->maps_url,
                    'area_code_id' => $prospect->area_code_id,
                    'package_id' => $prospect->package_id,
                    'ppp_secret_id' => $this->existingSecretId($ticket),
                    'joined_at' => now(),
                    'notes' => __('Konversi dari prospect #:id', ['id' => $prospect->id]),
                ]);

                $pelangganCreated++;
            } elseif (($pelanggan->maps_url ?? '') === '' && ($prospect->maps_url ?? '') !== '') {
                // Real sync: legacy rows also receive the prospect's map link.
                $pelanggan->maps_url = $prospect->maps_url;
            }

            $pelanggan->status = match (true) {
                $cancelled => PelangganStatus::Stopped,
                default => PelangganStatus::Active,
            };

            if ($completed && $pelanggan->activated_at === null) {
                $pelanggan->activated_at = $ticket->finished_at ?? now();
            }

            $pelanggan->save();

            $customer = Customer::query()->firstOrNew(['psb_ticket_id' => $ticket->id]);

            if (! $customer->exists) {
                $customer->fill([
                    'name' => $prospect->name,
                    'email' => $prospect->email,
                    'whatsapp' => $prospect->whatsapp,
                    'address' => $prospect->address,
                    'area_code_id' => $prospect->area_code_id,
                    'package_id' => $prospect->package_id,
                    'ppp_secret_id' => $pelanggan->ppp_secret_id,
                    'notes' => __('Konversi dari prospect #:id', ['id' => $prospect->id]),
                ]);

                $customerCreated++;
            }

            $customer->status = $cancelled
                ? CustomerStatus::Suspended
                : CustomerStatus::Active;

            $customer->save();
            $updated++;
        }

        $this->info("Sinkron selesai: {$updated} tiket diproses, {$pelangganCreated} pelanggan dibuat, {$customerCreated} customer billing dibuat.");

        return self::SUCCESS;
    }

    /**
     * Find the PPP secret provisioned at approval time by rebuilding the
     * deterministic credential scheme of the prospect.
     */
    private function existingSecretId(PsbTicket $ticket): ?int
    {
        $prospect = $ticket->prospect;
        $username = ProspectCredentialService::forProspect($prospect)['username'];

        return PppSecret::query()->where('name', $username)->value('id');
    }
}
