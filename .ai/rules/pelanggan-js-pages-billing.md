---
paths:
  - 'app/Http/Controllers/Pelanggan/PelangganController.php,app/Http/Controllers/Billing/CustomerController.php,resources/js/pages/pelanggan/**,resources/js/pages/billing/**'
---

# Pelanggan Js Pages Billing

## Halaman detail pelanggan & customer billing terintegrasi
Detail page: pelanggan.show → 'pelanggan/detail' dan billing.customer.show → 'billing/customers-detail'. Props: pelanggan/customer + ticket + prospect + invoice + bills[] + summary{outstanding,unpaid_count,bills_count}; customer detail juga kirim converted_from_prospect/pelanggan_id utk cross-link ke /pelanggan/{id}. Eager-load nested WAJIB sertakan FK di select induk (psbTicket:id,...,prospect_id). Manual customer = ticket null (UI tampil 'manual'). Aksi 'Detail' (ikon Eye) ada di dropdown kedua index. Wayfinder regenerate HARUS --with-form (halaman lama pakai .form()). Jangan buat komponen ui/table — proyek pakai <table> raw + class tailwind.
