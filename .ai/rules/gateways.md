---
paths:
  - 'app/Enums/**,app/Http/Controllers/Gateways/**,app/Models/MidtransSetting.php,app/Models/WahaSetting.php,database/migrations/*midtrans*,database/migrations/*waha*,resources/js/pages/gateways/**'
---

# Gateways

## Gateway Midtrans & WAHA integration
Kredensial disimpan terenkripsi (cast encrypted) di tabel midtrans_settings & waha_settings (singleton via Model::current()). Webhook publik di /webhooks/midtrans (verifikasi signature sha512 order_id+status_code+gross_amount+server_key) dan /webhooks/waha (header X-Api-Key) — CSRF dikecualikan via validateCsrfTokens(except: webhooks/*) di bootstrap/app.php. URL webhook diturunkan dari config('app.url') (APP_URL), bukan host request. Menu sidebar Gateway (dropdown Midtrans/WAHA) dikontrol MenuPermission menus.gateways/midtrans/waha; akses halaman via UserPermission gateways.view/update. WAHA test koneksi = GET {base_url}/ping dengan X-API-Key.
