---
paths:
  - 'app/Services/Billing/**,config/billing.php,resources/js/pages/tickets/psb/components/payment-estimate-card.tsx'
---

# Components

## Estimasi tagihan prorata (siklus tgl 11→10)
ProratedInvoiceEstimator: siklus tagihan = tgl 11 s.d. 10 bulan berikut (config billing.cycle_cut_day=10); period_days SELALU = jumlah hari bulan mulai siklus. prorata = floor(harga_bulanan/period_days × hari_aktif), aktif dari tanggal aktivasi sampai akhir siklus. Harga SUDAH termasuk PPN: total_exact = prorata+otc−diskon(45%, config promo_percent, floor ke (prorata+otc)); DPP = floor(total/1,11); PPN = total−DPP. Cash dibulatkan KE ATAS ke kelipatan 1000; online/VA exact. Card estimasi dirender di halaman finish tiket PSB; invoice asli menyusul modul billing. Jangan ubah angka contoh uji di BillingEstimatorTest (24 Agu 2026, Rp166rb → semua baris terkunci).
