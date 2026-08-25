<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PelangganStatus;
use App\Enums\ProspectStatus;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\Prospect;
use App\Models\PsbTicket;
use App\Models\RadiusServer;
use App\Services\Ppp\RadiusPppService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    /**
     * Real platform overview: counts, income and the latest records from
     * every integrated module.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedTickets = fn ($query) => $query->where(function ($query) use ($user): void {
            $query->where('psb_tickets.user_id', $user->id)
                ->orWhereHas('prospect', fn ($p) => $p->where('prospects.user_id', $user->id));
        });

        $month = now()->format('Y-m');
        $seeAll = $user->seesAllData();

        $incomeActivation = (int) Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('total_exact');

        $incomeBills = (int) Bill::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('total');

        return Inertia::render('dashboard', [
            'stats' => [
                'pelanggan_aktif' => Pelanggan::where('status', PelangganStatus::Active->value)->count(),
                'pelanggan_total' => Pelanggan::count(),
                'prospek_menunggu' => Prospect::where('status', ProspectStatus::Pending->value)->when(! $seeAll, fn ($q) => $q->where('user_id', $user->id))->count(),
                'tiket_terbuka' => PsbTicket::query()
                    ->whereIn('status', ['terjadwal', 'dikerjakan'])
                    ->when(! $seeAll, $scopedTickets)
                    ->count(),
                'tagihan_belum_bayar' => Bill::where('status', InvoiceStatus::Unpaid->value)->count(),
                'tunggakan_total' => (int) Bill::where('status', InvoiceStatus::Unpaid->value)->sum('total'),
                'pendapatan_bulan_ini' => $incomeActivation + $incomeBills,
                'pppoe_online' => $this->onlineSessions(),
            ],
            'latestProspects' => Prospect::query()
                ->where('status', ProspectStatus::Pending->value)
                ->when(! $seeAll, fn ($q) => $q->where('user_id', $user->id))
                ->with('areaCode:id,code')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'name', 'whatsapp', 'area_code_id', 'created_at'])
                ->map(fn (Prospect $prospect): array => [
                    'id' => $prospect->id,
                    'name' => $prospect->name,
                    'whatsapp' => $prospect->whatsapp,
                    'area_code' => $prospect->areaCode?->code,
                    'created_at' => $prospect->created_at?->toISOString(),
                ]),
            'latestTickets' => PsbTicket::query()
                ->when(! $seeAll, $scopedTickets)
                ->with(['prospect:id,name'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'ticket_number', 'prospect_id', 'status', 'scheduled_at'])
                ->map(fn (PsbTicket $ticket): array => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'customer_name' => $ticket->prospect?->name,
                    'status' => $ticket->status->value,
                    'scheduled_at' => $ticket->scheduled_at?->toISOString(),
                ]),
            'dueBills' => Bill::query()
                ->where('status', InvoiceStatus::Unpaid->value)
                ->when(! $seeAll, fn ($q) => $q->whereHas('psbTicket', $scopedTickets))
                ->with(['psbTicket:id,prospect_id', 'psbTicket.prospect:id,name'])
                ->orderBy('period')
                ->limit(5)
                ->get(['id', 'bill_number', 'period', 'total', 'psb_ticket_id'])
                ->map(fn (Bill $bill): array => [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'period' => $bill->period,
                    'total' => $bill->total,
                    'customer_name' => $bill->psbTicket?->prospect?->name,
                ]),
        ]);
    }

    /**
     * Live PPPoE sessions from the RADIUS accounting table. Null when no
     * RADIUS server is configured or reachable.
     */
    private function onlineSessions(): ?int
    {
        try {
            $server = RadiusServer::default();

            if ($server === null) {
                return null;
            }

            return RadiusPppService::forServer($server)->onlineSessionCount();
        } catch (Throwable) {
            return null;
        }
    }
}
