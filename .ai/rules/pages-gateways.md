---
paths:
  - 'app/Http/Controllers/Gateways/WahaServerController.php,resources/js/pages/gateways/components/waha-qr-dialog.tsx,resources/js/pages/gateways/waha.tsx'
---

# Pages Gateways

## WAHA QR login (scan barcode) nyata
Endpoint GET gateways/waha/{server}/qr (GatewaysUpdate): POST /api/{session}/start -> GET /api/{session}/status -> bila belum WORKING ambil GET /api/{session}/auth/qr?format=image (PNG binary) lalu bungkus data:image/png;base64. Response JSON {qr,status}; status WORKING = update is_connected true + clear last_error; gagal 502 {error} + simpan last_error. Frontend WahaQrDialog polling fetch tiap 3 dtk sampai status WORKING lalu router.reload only servers & auto close; tampil img base64 + badge status. Aksi "Scan QR" di dropdown baris WAHA. JANGAN buat file duplikat via cat >> saat cwd salah (sumber error duplicate import sebelumnya). onClose dipakai dalam useEffect -> simpan ke ref agar aman exhaustive-deps.
