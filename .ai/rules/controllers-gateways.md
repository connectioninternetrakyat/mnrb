---
paths:
  - app/Http/Controllers/Gateways/WahaServerController.php
---

# Controllers Gateways

## WAHA API: gunakan endpoint jamak /api/sessions/* dengan fallback legacy
WAHA server produksi ([SERVER-PRODUKSI], WEBJS engine) pakai rute BENTUK JAMAK: POST /api/sessions/start (bukan /api/session/start), GET /api/sessions/{session} utk status (bukan /api/{session}/status). QR tetap GET /api/{session}/auth/qr?format=image. Controller startSession() + sessionStatus() mencoba bentuk jamak dulu lalu fallback ke bentuk lama agar kompatibel dua versi. QR 422 "Session does not exist" = sesi belum dibuat/di-start, bukan salah endpoint.
