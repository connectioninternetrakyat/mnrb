---
paths:
  - 'app/Services/Genieacs/**,app/Http/Controllers/Ftth/OnuController.php,app/Models/Onu.php,resources/js/pages/ftth/onu*.tsx'
---

# Pages Ftth

## GenieACS NBI integration quirks (ONU/ONT)
GenieACS NBI menyimpan parameter sebagai objek {_value: x} (bukan 'value') — flattenParameters() menerima keduanya. DeviceID._SerialNumber/_ProductClass/_OUI adalah string polos di root dokumen. Task dikirim via POST /devices/{id}/tasks?timeout=... (addObject/setParameterValues/reboot/refreshObject; setParameterValue format [path, value, xsd:type]). WAN/WLAN/Host/Temperatur diekstrak dari peta parameter FLAT via regex per base-path (GenieacsService::wanConnections dll). PENTING: Http::fake butuh skema URL penuh & urutan stub paling SPESIFIK lebih dulu (first-match menang); wayfinder:generate CLI harus pakai --with-form agar varian .form() tidak hilang, dan route name bertingkat (ftth.onu.wan.add) menghasilkan subdirektori routes/ftth/onu/wan.
