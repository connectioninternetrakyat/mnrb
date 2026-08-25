<?php

namespace App\Http\Controllers\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Pelanggan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    /**
     * Display the yearly financial report.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::BillingView->value);

        $year = (int) ($request->input('year') ?: now()->format('Y'));
        $years = $this->availableYears();

        $report = $this->build($year);

        return Inertia::render('billing/reports', [
            'year' => $year,
            'years' => $years,
            'months' => $report['months'],
            'cards' => $this->monthCards(now()->format('Y-m'), $year, $report),
            'outstanding' => $this->outstanding(),
            'activeCustomers' => Pelanggan::where('status', 'aktif')->count(),
            'grand' => $report['grand'],
        ]);
    }

    /**
     * Download the yearly report as CSV.
     */
    public function download(Request $request): StreamedResponse
    {
        Gate::authorize(UserPermission::BillingView->value);

        $year = (int) ($request->input('year') ?: now()->format('Y'));
        $report = $this->build($year);

        $filename = "laporan-keuangan-{$year}.csv";

        return response()->streamDownload(function () use ($report, $year): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ["Laporan Keuangan {$year}"]);
            fputcsv($out, []);
            fputcsv($out, ['Bulan', 'Invoice Lunas', 'Pendapatan Aktivasi', 'Tagihan Lunas', 'Pendapatan Tagihan', 'Total']);

            foreach ($report['months'] as $month) {
                fputcsv($out, [
                    $month['label'],
                    $month['invoice_count'],
                    $month['invoice_total'],
                    $month['bill_count'],
                    $month['bill_total'],
                    $month['total'],
                ]);
            }

            fputcsv($out, ['TOTAL', $report['grand']['invoice_count'], $report['grand']['invoice_total'], $report['grand']['bill_count'], $report['grand']['bill_total'], $report['grand']['total']]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the monthly breakdown of a year from real paid invoices and bills.
     *
     * @return array<string, mixed>
     */
    private function build(int $year): array
    {
        $activationPaid = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereYear('paid_at', $year)
            ->get(['total_exact', 'paid_at']);

        $billPaid = Bill::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereYear('paid_at', $year)
            ->get(['total', 'paid_at']);

        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $invoices = $activationPaid->filter(fn ($row) => (int) $row->paid_at?->month === $month);
            $bills = $billPaid->filter(fn ($row) => (int) $row->paid_at?->month === $month);

            $invoiceTotal = (int) $invoices->sum('total_exact');
            $billTotal = (int) $bills->sum('total');

            $months[] = [
                'month' => $month,
                'label' => $labels[$month - 1],
                'invoice_count' => $invoices->count(),
                'invoice_total' => $invoiceTotal,
                'bill_count' => $bills->count(),
                'bill_total' => $billTotal,
                'total' => $invoiceTotal + $billTotal,
            ];
        }

        return [
            'months' => $months,
            'grand' => [
                'invoice_count' => array_sum(array_column($months, 'invoice_count')),
                'invoice_total' => array_sum(array_column($months, 'invoice_total')),
                'bill_count' => array_sum(array_column($months, 'bill_count')),
                'bill_total' => array_sum(array_column($months, 'bill_total')),
                'total' => array_sum(array_column($months, 'total')),
            ],
        ];
    }

    /**
     * Years that actually have payment data, always including current year.
     *
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        $invoiceYears = Invoice::query()
            ->whereNotNull('paid_at')
            ->get(['paid_at'])
            ->map(fn ($row): int => (int) $row->paid_at->year)
            ->all();

        $billYears = Bill::query()
            ->whereNotNull('paid_at')
            ->get(['paid_at'])
            ->map(fn ($row): int => (int) $row->paid_at->year)
            ->all();

        $years = array_unique(array_merge($invoiceYears, $billYears, [(int) now()->format('Y')]));

        sort($years);

        return array_values($years);
    }

    /**
     * Summary cards for the current month + outstanding receivables.
     *
     * @return array<string, int>
     */
    private function monthCards(string $currentPeriod, int $year, array $report): array
    {
        $monthIndex = (int) now()->format('n');
        $isCurrentYear = $year === (int) now()->format('Y');

        $row = $isCurrentYear ? $report['months'][$monthIndex - 1] : [
            'invoice_count' => 0,
            'invoice_total' => 0,
            'bill_count' => 0,
            'bill_total' => 0,
            'total' => 0,
        ];

        return [
            'activation_total' => $row['invoice_total'],
            'activation_count' => $row['invoice_count'],
            'bill_total' => $row['bill_total'],
            'bill_count' => $row['bill_count'],
            'income_total' => $row['total'],
        ];
    }

    /**
     * All unpaid recurring bills as outstanding receivables.
     *
     * @return array{total: int, count: int}
     */
    private function outstanding(): array
    {
        $rows = Bill::query()
            ->where('status', InvoiceStatus::Unpaid->value)
            ->get(['total']);

        return [
            'total' => (int) $rows->sum('total'),
            'count' => $rows->count(),
        ];
    }
}
