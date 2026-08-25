---
paths:
  - 'app/Models/Bill.php,app/Services/Billing/MonthlyBillService.php,app/Http/Controllers/Billing/BillController.php,database/migrations/*bills*,resources/js/pages/billing/bills.tsx'
---

# Js Pages Billing

## Tagihan bulanan (bills) + generator periode
Tabel bills: 1 tagihan per tiket per periode (unique psb_ticket_id+period, period 'Y-m'), nomor TAG-YYYYMM-NNNN. MonthlyBillService::generateForCurrentPeriod() membuat tagihan untuk SEMUA tiket status Completed yang belum punya bill periode berjalan — TANPA diskon promo (promo hanya invoice aktivasi), harga paket langsung dipakai sbg total, DPP/PPN dipecah dari BillingSetting::ppn_percent. Idempotent (generate kedua = 0 baru). Endpoint billing.bill.index/generate/mark-paid; permission billing.view/update; menu menus.billing-bills label 'Tagihan' dalam dropdown Billing. Jangan buat 2 migration create tabel sama (hapus duplikat, edit file bawaan make:model).
