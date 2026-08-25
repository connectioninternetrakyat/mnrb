<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Bill;
use App\Models\BillingSetting;
use App\Services\Waha\WahaSender;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendIsolationRemindersCommand extends Command
{
    protected $signature = 'billing:remind';

    protected $description = 'Kirim pengingat WhatsApp H-3/H-2/H-1 sebelum auto isolir ke pelanggan yang belum bayar';

    public function handle(WahaSender $sender): int
    {
        $settings = BillingSetting::current();
        $now = CarbonImmutable::now();

        if ($settings->isolir_day === null) {
            $this->info('Auto isolir tidak aktif. Pengingat dilewati.');

            return self::SUCCESS;
        }

        // Next occurrence of the isolation day (today or later).
        $isolirDate = $now->day < (int) $settings->isolir_day
            ? $now->setDay((int) $settings->isolir_day)
            : $now->addMonthNoOverflow()->setDay(min((int) $settings->isolir_day, $now->daysInMonth));

        $daysLeft = (int) $now->startOfDay()->diffInDays($isolirDate->startOfDay());

        $flag = match (true) {
            $daysLeft === 3 && $settings->remind_h3 => 'h3',
            $daysLeft === 2 && $settings->remind_h2 => 'h2',
            $daysLeft === 1 && $settings->remind_h1 => 'h1',
            default => null,
        };

        if ($flag === null) {
            $this->line('DEBUG: daysLeft='.$daysLeft.' isolirDay='.$settings->isolir_day);

            $this->info("Bukan hari pengingat (sisa {$daysLeft} hari).");

            return self::SUCCESS;
        }

        $bills = Bill::query()
            ->where('status', InvoiceStatus::Unpaid->value)
            ->where('period', '<=', $now->format('Y-m'))
            ->whereNull('reminder_sent_at')
            ->with([
                'psbTicket:id,ticket_number,prospect_id,isolated_at',
                'psbTicket.prospect:id,name,whatsapp',
                // Managed customer record wins for contact data.
                'psbTicket.pelanggan:id,name,whatsapp,psb_ticket_id',
            ])
            ->get();

        $sent = 0;

        foreach ($bills as $bill) {
            $phone = $bill->psbTicket?->contactWhatsapp();

            if ($phone === null) {
                continue;
            }

            $message = sprintf(
                "Pelanggan Yth, %s\nTagihan %s periode %s sebesar Rp%s.\nMohon lakukan pembayaran sebelum %s agar tidak terisolir otomatis.\nTerima kasih.",
                $bill->psbTicket?->customerName(),
                $bill->bill_number,
                $bill->period,
                number_format($bill->total, 0, ',', '.'),
                $isolirDate->format('d-m-Y'),
            );

            if ($sender->send($phone, $message)) {
                $bill->update(['reminder_sent_at' => now()]);
                $sent++;
            }
        }

        $this->info("{$sent} pengingat terkirim (H-".substr($flag, 1).').');

        return self::SUCCESS;
    }
}
