---
paths:
  - 'app/Console/Commands/**,app/Services/Waha/**,routes/console.php,app/Providers/AppServiceProvider.php'
---

# Providers

## Otomasi billing: generate, remind H-3/H-2/H-1, auto isolir
Scheduler (routes/console.php): billing:generate daily 00:10 — idempoten via billing_settings.last_generated_period; billing:remind daily 09:00 — kirim WA (WahaSender, server is_connected, chatId 62xx@c.us) ke tagihan unpaid saat sisa hari ke isolir = 3/2/1 sesuai flag remind_h3/h2/h1, dedupe bills.reminder_sent_at; billing:auto-isolate hourly — cek now >= isolir_day+isolir_hour lalu pindahkan secret pelanggan menunggak ke profil Isolir (Mikrotik updateSecret profile + RADIUS upsertUser), catat isolated_at + isolated_profile di psb_tickets. Tandai-lunas tagihan → liftIsolationIfSettled mengembalikan profil asli & clear flag. MikrotikPppService di-bind di AppServiceProvider agar command/test bisa resolve dari container (test: $this->instance + Mockery dengan ctor arg server). PENTING eager-load nested WAJIB sertakan kolom FK di select induk (prospect:id,name,whatsapp,area_code_id,package_id) — kalau tidak relasi null diam-diam. Command output tak terlihat oleh Pest tanpa Artisan::output().
