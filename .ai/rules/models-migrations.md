---
paths:
  - 'app/Http/Controllers/Tickets/PsbTicketController.php,app/Models/MessageTemplate.php,database/migrations/*message_templates*'
---

# Models Migrations

## Notifikasi WA penyelesaian tiket PSB (slug 'selesai')
storeFinish kini kirim WA via notifyCompletion($ticket,$invoice) SETELAH issueInvoice + attachMidtransVa: TemplateRenderer::renderSlug('selesai', ...) → app(WahaSender::class)->send(prospect->whatsapp) dalam try/catch + Log::warning (pola sama dgn BillController::notifyPayment). Slug 'selesai' ada di MessageTemplate::SLUGS + di-seed migration terpisah updateOrInsert (idempoten). Vars: nama,tiket,paket,teknisi,tanggal,invoice,total,metode. Total = cash_total utk tunai, total_exact utk transfer. Guard: prospect null / whatsapp kosong / message kosong → skip diam. Jangan taruh sebelum VA attach agar nomor/total final.
