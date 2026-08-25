---
paths:
  - 'app/Http/Controllers/Billing/InvoiceController.php,resources/js/pages/billing/invoices.tsx'
  - 'app/Http/Controllers/Billing/FinancialReportController.php,resources/js/pages/billing/reports.tsx'
---

# Pages Billing

## Menu Invoice: list + tandai lunas manual
Billing > Invoice (menus.billing-invoices) = daftar semua invoice aktivasi (paginate 10, search invoice/pelanggan/VA, filter status). InvoiceController::markPaid (billing.update) hanya membalik status menunggu_pembayaran → lunas; verifikasi otomatis tetap via MidtransWebhookController (order_id == invoice_number, settlement/capture, gross_amount harus sama dengan total_exact). Route billing.invoice.index/mark-paid; wayfinder export markPaid (bukan 'mark'). Row shape di-shape via through() dengan eager psbTicket.prospect.package.

## Laporan keuangan tahunan (agregasi PHP)
Billing > Laporan (menus.billing-reports, gate billing.view): FinancialReportController menghitung per bulan Jan-Des dari Invoice & Bill berstatus lunas berdasarkan paid_at (bukan period), plus kartu bulan-ini dan outstanding (semua bill unpaid). Agregasi pakai KOLEKSI PHP (filter by paid_at->month) — JANGAN SQL strftime/to_char agar aman sqlite+pgsql. availableYears() gabungan tahun paid_at kedua tabel + tahun sekarang. CSV download via streamDownload billing.report.download?year=. Wayfinder query param: index({ query: { year } }), url({ query: {...} }) — bukan arg pertama.
