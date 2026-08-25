---
paths:
  - 'app/Models/Invoice.php,app/Enums/InvoiceStatus.php,app/Http/Controllers/Gateways/MidtransWebhookController.php,database/migrations/*invoices*,app/Http/Controllers/Tickets/PsbTicketController.php'
---

# Controllers Tickets

## Invoice aktivasi PSB: tunai=lunas, transfer=verifikasi webhook
Tabel invoices (1:1 ke psb_tickets) menyimpan snapshot estimasi (prorata/otc/diskon/dpp/ppn/total) dari ProratedInvoiceEstimator saat storeFinish. Tunai → status lunas + paid_at langsung. Transfer → menunggu_pembayaran dengan va_number 16 digit = prefix bank (BRI 888/BNI 988/BCA 8077/Mandiri 895, Invoice::BANK_PREFIXES) + WA format 08 dipad '0'; bank wajib salah satu dari daftar itu. Verifikasi otomatis: MidtransWebhookController mencocokkan order_id == invoice_number, transaction_status settlement/capture tanpa fraud challenge, gross_amount harus == total_exact → markAsPaid(). Jangan pecah format VA; order_id invoice = nomor invoice INV-YYYYMMDD-NNNN.
