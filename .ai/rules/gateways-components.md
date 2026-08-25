---
paths:
  - 'app/Http/Controllers/Gateways/WahaServerController.php,resources/js/pages/gateways/components/waha-qr-dialog.tsx'
---

# Gateways Components

## WAHA QR: status-first, restart stop+start saat FAILED, polling tanpa overlap
JANGAN panggil start tiap poll: qr() baca status dulu (/api/sessions/{session} modern, fallback /api/{session}/status); start+stop hanya saat 404/status kosong/FAILED/STOPPED. Sesi FAILED butuh POST stop lalu start (start saja ditolak). /auth/qr membalas ~10 dtk 422 saat sesi rusak — jangan biarkan endpoint ini dipanggil beruntun. Frontend WahaQrDialog WAJIB polling berantai (setTimeout setelah fetch selesai), BUKAN setInterval — request tumpang tindih 12dtk+ meny saturasi php artisan serve single-worker dan membuat SEMUA halaman lambat. Set PHP_CLI_SERVER_WORKERS=4 di .env utk dev server multi-worker. Hapus log diag per-poll (log laravel bisa membengkak).
