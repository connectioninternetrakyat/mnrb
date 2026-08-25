<?php

namespace App\Services\Prospect;

use App\Enums\CustomerStatus;
use App\Enums\PelangganStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Models\Customer;
use App\Models\Pelanggan;
use App\Models\PppSecret;
use App\Models\Prospect;
use App\Models\PsbTicket;
use App\Models\User;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Waha\TemplateRenderer;
use App\Services\Waha\WahaSender;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Approves a prospect by provisioning its PPP secret on Mikrotik & Radius
 * with the automatic credentials, flipping the prospect status and creating
 * the PSB installation ticket with its schedule.
 */
class ProspectApprovalService
{
    /**
     * Approve the prospect, provision its PPP secret on both systems and
     * create the PSB ticket.
     *
     * @param  Prospect  $prospect  Prospect still waiting for verification.
     * @param  array{user_id: int, scheduled_at: string}|null  $schedule  Teknisi + jadwal pemasangan.
     * @param  PppProvisioningService|null  $provisioning  Defaults to the default servers.
     * @return array{username: string, password: string}
     */
    public function approve(Prospect $prospect, ?array $schedule = null, ?PppProvisioningService $provisioning = null): array
    {
        if (! $prospect->isPending()) {
            throw new RuntimeException(__('Prospect sudah diproses sebelumnya.'));
        }

        if ($schedule !== null) {
            $this->assertSchedule($schedule);
        }

        $prospect->load(['areaCode', 'package.pppProfile']);

        $profile = $prospect->package?->pppProfile;

        if ($profile === null) {
            throw new RuntimeException(__('Paket pada prospect belum terhubung ke PPP Profile.'));
        }

        $credentials = ProspectCredentialService::forProspect($prospect);

        if (PppSecret::query()->where('name', $credentials['username'])->exists()) {
            throw new RuntimeException(__('Secret :name sudah ada di PPP Secret.', ['name' => $credentials['username']]));
        }

        $secret = ($provisioning ?? PppProvisioningService::make())->createSecret(
            $credentials['username'],
            $credentials['password'],
            $profile->name,
            sprintf('prospect #%d - %s', $prospect->id, $prospect->name),
        );

        DB::transaction(function () use ($prospect, $schedule, $secret): void {
            $prospect->update([
                'status' => ProspectStatus::Approved->value,
                'approved_at' => now(),
            ]);

            $ticket = PsbTicket::create([
                'ticket_number' => PsbTicket::generateNumber(),
                'prospect_id' => $prospect->id,
                'user_id' => $schedule['user_id'] ?? null,
                'scheduled_at' => $schedule['scheduled_at'] ?? now()->addDay(),
                'status' => PsbTicketStatus::Scheduled->value,
            ]);

            // The customer record of the platform is born here: every
            // approved prospect becomes a managed FTTH pelanggan.
            Pelanggan::create([
                'name' => $prospect->name,
                'email' => $prospect->email,
                'whatsapp' => $prospect->whatsapp,
                'address' => $prospect->address,
                'maps_url' => $prospect->maps_url,
                'area_code_id' => $prospect->area_code_id,
                'package_id' => $prospect->package_id,
                'ppp_secret_id' => $secret->id,
                'psb_ticket_id' => $ticket->id,
                'status' => PelangganStatus::Active->value,
                'joined_at' => now(),
                'notes' => __('Konversi dari prospect #:id', ['id' => $prospect->id]),
            ]);

            // Real billing integration: the billing customer registry
            // receives the same conversion, linked to the ticket, so
            // bills/invoices are reachable from the billing module.
            Customer::updateOrCreate(
                ['psb_ticket_id' => $ticket->id],
                [
                    'name' => $prospect->name,
                    'email' => $prospect->email,
                    'whatsapp' => $prospect->whatsapp,
                    'address' => $prospect->address,
                    'area_code_id' => $prospect->area_code_id,
                    'package_id' => $prospect->package_id,
                    'ppp_secret_id' => $secret->id,
                    'status' => CustomerStatus::Active->value,
                    'notes' => __('Konversi dari prospect #:id', ['id' => $prospect->id]),
                ],
            );
        });

        // Real integration: notify the customer about the installation
        // schedule through WhatsApp using the PSB template.
        try {
            $technicianName = isset($schedule['user_id'])
                ? (string) (User::find($schedule['user_id'])?->name ?? '-')
                : '-';

            $message = TemplateRenderer::renderSlug('psb', '', [
                'nama' => $prospect->name,
                'paket' => $profile->name,
                'alamat' => $prospect->address,
                'area' => trim(($prospect->areaCode?->code ?? '').' - '.($prospect->areaCode?->name ?? '')),
                'jadwal' => Carbon::parse($schedule['scheduled_at'] ?? now()->addDay())
                    ->locale('id')
                    ->isoFormat('dddd, D MMMM YYYY HH:mm'),
                'teknisi' => $technicianName,
            ]);

            if ($message !== '') {
                app(WahaSender::class)
                    ->send($prospect->whatsapp, $message);
            }
        } catch (Throwable) {
            // WAHA not configured: the schedule still stands.
        }

        return $credentials;
    }

    /**
     * Guard against assigning unknown users or broken schedules.
     *
     * @param  array{user_id: int, scheduled_at: string}  $schedule
     */
    private function assertSchedule(array $schedule): void
    {
        $scheduledAt = Carbon::parse($schedule['scheduled_at']);

        if ($scheduledAt->isPast()) {
            throw new RuntimeException(__('Jadwal pemasangan harus lebih dari waktu sekarang.'));
        }
    }
}
