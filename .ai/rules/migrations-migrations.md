---
paths:
  - 'app/Models/MessageTemplate.php,app/Models/Broadcast.php,app/Http/Controllers/Waha/**,app/Services/Waha/**,resources/js/pages/wa/**,database/migrations/*message_templates*,database/migrations/*broadcasts*'
---

# Migrations Migrations

## WhatsApp: template + broadcast terintegrasi
Menu WhatsApp dropdown Template/Broadcast (menus.wa-templates/wa-broadcast; perms wa.view/send/manage — Marketing&Teknisi view+send). message_templates di-seed default psb/pembayaran/gangguan/isolir via updateOrInsert slug di migration; slug bawaan tidak boleh dihapus. TemplateController::store = updateOrCreate by slug (POST sama utk create/update). Render variabel {{...}} via MessageTemplate::render / TemplateRenderer::varsFor(pelanggan). Broadcast store: scope area_code_id nullable(null=semua) + template opsional ATAU message manual; kirim sinkron WahaSender ke pelanggan aktif, catat recipients/sent/failed. Hook otomatis: approve→template 'psb', bill/invoice lunas→'pembayaran' (BillController::notifyPayment, InvoiceController::notifyInvoicePayment), reminder isolir command→'isolir'. WAHA send butuh server is_connected=true; Http::fake('waha.test/*') di test. JANGAN lupa import Rule dsb saat generate controller baru.
