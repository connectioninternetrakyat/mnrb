---
paths:
  - 'app/Enums/**,app/Http/Controllers/Ftth/**,app/Services/GoogleMapsUrlResolver.php,app/Models/AreaCode.php,app/Models/OdpOdc.php,database/migrations/*area_codes*,database/migrations/*odp_odcs*,resources/js/pages/ftth/**'
---

# Ftth

## FTTH module & Google Maps shortlink resolver
Menu FTTH (dropdown Kode Area + ODP/ODC) dikontrol menus.ftth/area-codes/odp-odc; akses via ftth.view/update. Tabel area_codes (code unique; hapus diblok bila masih dipakai ODP/ODC) dan odp_odcs (FK area_code_id restrictOnDelete). Koordinat diisi otomatis dari URL Google Maps via POST ftth/maps/resolve (App\Services\GoogleMapsUrlResolver): ikuti redirect chain maps.app.goo.gl maks 5 hop lalu ekstrak koordinat dari beberapa bentuk URL. PENTING: format koordinat Google = "lat,+lng" — pemisah WAJIB menoleransi '+' tambahan sebelum longitude ((?:%2C|,|\+)\+?), regex tanpa itu gagal diam-diam. Frontend pakai useHttp (Inertia v3) untuk panggil resolve tanpa page visit.
