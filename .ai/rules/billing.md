---
paths:
  - 'app/Models/BillingSetting.php,app/Http/Controllers/Billing/**,app/Http/Requests/Billing/**,app/Services/Billing/**,database/migrations/*billing_settings*,resources/js/pages/billing/**'
---

# Billing

## BillingSetting singleton + estimator membaca DB
Pengaturan tagihan = tabel singleton billing_settings via BillingSetting::current() (firstOrCreate default: cut 10, invoice 11, isolir null+'23:59', PPN 11, promo 0). ProratedInvoiceEstimator WAJIB baca dari sini (bukan config): prorate_cut_day = hari penutup siklus; aktivasi TEPAT di tanggal cut → masuk siklus BARU (banding < bukan <=); cycleStart = setDay(cut+1) bulan sebelumnya dengan clamp daysInMonth. isolir punya jam terpisah isolir_hour string HH:MM 24-jam. Reminder H-3/H-2/H-1 relatif ke AUTO ISOLIR; checkbox radix tak tercentang tidak terkirim → rules 'boolean' tanpa required + controller $request->boolean(). Promo hanya berpengaruh di invoice pertama. Permission billing.view/update, menus.billing + menus.billing-setting (Admin default semua).
