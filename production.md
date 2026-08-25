# Panduan Production — MNRB ISP Panel

Panduan lengkap deployment & konfigurasi wajib platform manajemen ISP
(PPPoE, FTTH, Billing, WhatsApp).

---

## 1. Persyaratan Server

| Komponen | Versi / Ketentuan |
| --- | --- |
| PHP | 8.4 + ekstensi `pgsql`/`mysql`, `mbstring`, `openssl`, `curl`, `gd` |
| Composer | 2.x |
| Node.js + npm | 20+ (untuk build aset frontend) |
| Database | PostgreSQL **atau** MySQL |
| RADIUS | FreeRADIUS dengan tabel radcheck/radusergroup/radgroupreply/radacct |
| Mikrotik | RouterOS dengan API aktif (port 8728) |
| GenieACS (opsional) | Untuk fitur ONU/ONT |
| WAHA (opsional) | Untuk kirim WhatsApp: broadcast, pengingat, notifikasi |
| Akun Midtrans (opsional) | Server key production untuk VA otomatis |

## 2. Instalasi

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# isi .env sesuai bagian di bawah, lalu:
php artisan migrate --force
php artisan db:seed --class=SuperSeeder --force
npm ci && npm run build
php artisan storage:link
```

Variabel `.env` wajib benar:

| Key | Keterangan |
| --- | --- |
| APP_URL | Domain produksi (mempengaruhi link & unduhan) |
| APP_ENV / APP_DEBUG | production / false |
| DB_* | Koneksi database utama panel |
| SESSION_DRIVER | database (disarankan) |

## 3. Cron Wajib (fitur otomatis)

```cron
* * * * * cd /path/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Tanpa cron ini TIDAK ADA fitur otomatis yang berjalan.

## 4. Perintah Artisan Platform

| Perintah | Fungsi | Jadwal bawaan |
| --- | --- | --- |
| billing:generate | Buat tagihan bulanan pelanggan aktif (idempoten) | Harian 00:10 |
| billing:remind | WA pengingat H-3/H-2/H-1 sebelum isolir | Harian 09:00 |
| billing:auto-isolate | Pindahkan pelanggan menunggak ke Profil Isolir | Tiap jam (cek jam isolir) |
| db:seed --class=SuperSeeder | Sinkron role/permission/menu + akun superadmin | Saat install / setelah ubah enum |

Semua bisa dijalankan manual kapan saja.

## 5. Checklist Konfigurasi Wajib di Panel

### 5.1 Keamanan awal (WAJIB)
1. Login superadmin awal `dev@mnrb.com` (dari seeder) lalu SEGERA ganti password.
2. Buat user untuk tiap staf + assign Role (Marketing/Teknisi/CS/dll).

### 5.2 Menu Servers
- Mikrotik: host, port API 8728, user/password (hak ppp+api), centang default. WAJIB agar provisioning/isolir berfungsi.
- RADIUS: driver/host/port/database/user/password RADIUS, centang default. Wajib agar PPP Secret aktif & hitungan online.
- GenieACS (opsional): base URL + kredensial untuk ONU/ONT.

### 5.3 Gateway
- WAHA: base_url, api_key, session -> test hingga terhubung. Tanpa ini broadcast/pengingat/notifikasi WA tidak terkirim.
- Midtrans: merchant_id, server_key, client_key (production saat live), centang aktif + production.

### 5.4 FTTH
1. Kode Area: daftar area layanan (mis. 05).
2. PPPoE > PPP Profile: profil kecepatan nyata di Mikrotik (mis. PPPoE-10M).
3. Buat juga profil bernama "Isolir" di Mikrotik lalu sinkronkan (dipakai auto isolir).
4. Billing > Paket & Harga: paket tertaut ke PPP Profile masing-masing.

### 5.5 Billing > Setting
| Kolom | Keterangan |
| --- | --- |
| Tanggal Prorate | Hari penutup siklus (contoh 20 = siklus 21 s.d. 20). Isi 1-28 |
| PPN (%) | Dipisah dari total tagihan (DPP + PPN) |
| Tanggal Auto Isolir + Jam | Hari & jam penunggak dipindah ke Profil Isolir. Kosongkan tanggal = mati |
| Profil Isolir | Nama PPP Profile isolir (harus ada di Mikrotik) |
| Awal Penagihan | Tanggal tagihan bulanan diterbitkan |
| Pengingat H-3/H-2/H-1 | Aktif/nonaktif pengingat WA sebelum isolir |
| Diskon Promo (%) | Hanya berlaku di invoice aktivasi pertama |

### 5.6 WhatsApp > Template
Template bawaan: psb, pembayaran, gangguan, isolir. Variabel {{nama}} {{paket}} {{jadwal}} {{teknisi}} {{tagihan}} {{total}} {{tanggal_isolir}} {{jam_isolir}} dst terisi otomatis dari data platform.

## 6. Webhook Midtrans (wajib utk Transfer otomatis)

Di dashboard Midtrans (Settings > Configuration), isi:

Payment Notification URL: https://domain-anda/webhooks/midtrans

Alur: tiket ditutup metode Transfer -> panel panggil Core API /v2/charge ->
Midtrans terbitkan VA asli -> pelanggan bayar -> webhook masuk -> invoice LUNAS.

Tanpa akun Midtrans aktif, sistem tetap jalan memakai VA offline lokal
(fallback), namun verifikasi otomatis tidak berfungsi.

## 7. Alur Operasional Harian

Marketing input Prospect -> Admin setujui (teknisi + jadwal):
- PPP Secret dibuat (Radius aktif, Mikrotik disable)
- Tiket PSB terjadwal + WA notifikasi PSB
- Pelanggan FTTH otomatis tercatat

Teknusi kerjakan tiket -> Selesaikan (ceklis + hasil):
- Tunai: invoice langsung LUNAS
- Transfer: VA Midtrans asli diterbitkan -> webhook -> LUNAS

Scheduler harian:
- Generate tagihan bulanan
- H-3/H-2/H-1 pengingat isolir
- Lewat jatuh tempo: auto isolir (profil Isolir)

Pelanggan bayar -> profil asli dikembalikan otomatis.

## 8. Optimasi Produksi

php artisan config:cache && php artisan route:cache && php artisan view:cache

Queue (bila QUEUE_CONNECTION=database): jalankan supervisor utk queue:work.
Log penting di storage/logs/laravel.log: prefix "Midtrans VA gagal",
"WAHA send failed", "Gagal membuka isolir".

## 9. Troubleshooting Cepat

| Gejala | Periksa |
| --- | --- |
| WA tidak terkirim | Status WAHA terhubung? Log "WAHA send failed"? |
| Isolir tidak jalan | Cron schedule:run aktif? Tanggal/jam isolir terisi? Tagihan unpaid? |
| VA tidak muncul | Ada akun Midtrans AKTIF? Log "Midtrans VA gagal"? |
| Invoice tidak lunas otomatis | Payment Notification URL terdaftar? Signature/gross_amount cocok? |
| PPPoE Online tampil "-" | RADIUS default terpasang & tabel radacct dapat dibaca? |
