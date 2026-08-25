---
paths:
  - 'app/Services/Ppp/**'
---

# Services Ppp

## RouterOS Query constructor butuh array (bukan null)
Di evilfreelancer/routeros-api-php, RouterOS\Query::__construct($endpoint, array $attributes = []) tidak menerima null pada argumen kedua — selalu kirim $where ?? []. Sudah menelan waktu debug saat integrasi live. Kredensial produksi: Mikrotik "Router Utama" 192.168.100.85:8728 & Radius PostgreSQL [SERVER-PRODUKSI]/radius, keduanya is_default=true; tabel rad* standar FreeRADIUS sudah ada & terverifikasi tulis-baca via RadiusPppService.
