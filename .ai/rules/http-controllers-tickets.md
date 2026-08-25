---
paths:
  - 'app/Services/Billing/MidtransVaService.php,app/Http/Controllers/Tickets/PsbTicketController.php'
---

# Http Controllers Tickets

## Midtrans VA nyata (Core API) di finishing transfer
Saat storeFinish metode transfer, attachMidtransVa() memanggil MidtransVaService::charge → POST {api|api.sandbox}.midtrans.com/v2/charge (Basic auth server_key, tanpa titik dua trailing) dengan order_id=invoice_number & gross_amount=total_exact; ambil va dari permata_va_number ?? va_numbers[0]. Sukses → timpa fallback VA lokal di invoices.va_number. Gagal (tanpa akun aktif/gateway error 4xx-5xx) → RuntimeException ditangkap: fallback VA lokal tetap + Log warning, flow tidak pernah 500. Pelunasan tetap otomatis via webhook signature sha512(order+status+gross+serverKey), gross_amount harus == total_exact. Http::fake test pakai pola '*' ATAU full URL berscheme. JANGAN panggil WahaSender::make() — pakai app(WahaSender::class); selalu import Log/Throwable di controller baru.
