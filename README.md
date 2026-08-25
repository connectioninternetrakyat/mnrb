# MNRB

Sistem manajemen ISP/FTTH terintegrasi: dari **prospek calon pelanggan**, **tiket pasang baru (PSB)**, **manajemen pelanggan FTTH**, **billing otomatis**, hingga **notifikasi WhatsApp** dan **payment gateway Midtrans**.

Dibangun dengan Laravel 12 + Inertia v3 + React + Tailwind CSS.

---

## Arsitektur Integrasi (Tulang Punggung Data)

```
Prospek ──approve──► Tiket PSB ──selesai──► Invoice Aktivasi
   │                    │                        │
   │                    │                        ▼
   ├──► Pelanggan FTTH ◄┘              Tagihan Bulanan (bills)
   │         │                              │
   │         └── psb_ticket_id ──► bills/invoices
   ▼
Customer Billing ──► unduh tagihan / detail terintegrasi
```

Satu kali approve prospek → sistem otomatis membuat:
1. **Akun PPPoE** di Mikrotik + RADIUS (kredensial otomatis `{kode_area}_{nama}`)
2. **Tiket PSB** dengan jadwal + teknisi
3. **Pelanggan FTTH** (management pelanggan)
4. **Customer billing** (tertaut tiket)
5. **Notifikasi WhatsApp jadwal pemasangan** (template `psb`)

Ketika tiket PSB diselesaikan → invoice aktivasi terbit (tunai = lunas instan, transfer = VA Midtrans nyata), pelanggan mendapat `activated_at`, notifikasi WA `selesai` terkirim, dan tagihan bulanan mengikuti **paket terkini** pelanggan.

---

## Instalasi

```bash
composer install
npm install
cp env.md .env        # lalu isi APP_KEY, kredensial database, dll.
php artisan key:generate
php artisan migrate --seed
npm run dev           # atau npm run build untuk produksi
php artisan serve     # PHP_CLI_SERVER_WORKERS=4 disarankan agar multi-worker
```

Lihat **env.md** untuk daftar variabel lingkungan (tanpa kredensial).

### Perintah Artisan Penting

| Perintah | Fungsi |
|---|---|
| `billing:generate` | Buat tagihan bulanan tiket selesai (idempoten) |
| `billing:remind` | Kirim pengingat WA H-3/H-2/H-1 sebelum isolir |
| `billing:auto-isolate` | Isolir otomatis menunggak (pindah profil PPP) |
| `psb:sync-customers` | Backfill data historis ke registri pelanggan & billing |

Scheduler aktif via `routes/console.php`: generate 00:10, remind 09:00, auto-isolate per jam.

---

## Modul Manajemen & Alur Kerja

### 1. Dashboard
Ringkasan KPI: jumlah pelanggan aktif, pendapatan billing, aktivitas terbaru dari seluruh modul.

### 2. Prospek (Calon Pelanggan)
**Alur:** Input prospek (nama, WA, alamat, URL Google Maps, area, paket) → status *pending* → **Approve** (wajib pilih teknisi + jadwal).

- Approve menjalankan 5 hal otomatis (lihat arsitektur di atas).
- **Reject** untuk menolak dengan alasan.
- URL Google Maps dapat diresolve otomatis menjadi koordinat (`ftth/maps/resolve`).

### 3. Tiket PSB (Pasang Baru)
Status: `terjadwal → dikerjakan → selesai | dibatalkan`.

- **Progres**: teknisi memulai pengerjaan (`started_at` terisi sekali).
- **Selesaikan (finish)**: checklist wajib (PPPoE benar, customer aktif, VLAN TR-069), pilih metode bayar:
  - **Tunai** → invoice langsung lunas
  - **Transfer** → invoice menunggu + **VA bank nyata via Midtrans Core API** (BRI/BNI/BCA/Mandiri), lunas otomatis saat webhook settlement masuk
- Setelah selesai: notifikasi WA berisi nomor tiket, invoice, total; pelanggan & customer billing tersinkron (`activated_at`).
- **Batalkan**: wajib alasan ≥5 karakter → pelanggan menjadi *berhenti* (tidak ada pelanggan fantasi).
- Halaman detail menampilkan kredensial PPPoE, estimasi prorata, dan riwayat lengkap.

### 4. Management Pelanggan (FTTH)
Daftar pelanggan yang lahir otomatis dari approve prospek (atau manual).

- Kolom **Tagihan**: nomor tiket, badge *Terisolir*, tunggakan Rp.
- Aksi **Hentikan Layanan**: disable secret di Mikrotik + hapus user RADIUS (nyata).
- Aksi **Aktifkan Layanan**: enable kembali dengan profil paket terkini.
- **Detail**: identitas, layanan, asal prospek & tiket, invoice aktivasi, tabel tagihan bulanan, link Google Maps.
- Pencarian mendukung nama/WA/username PPPoE/nomor tiket.
- Upgrade paket di sini **langsung memengaruhi** tagihan bulanan berikutnya.

### 5. Billing

| Halaman | Isi |
|---|---|
| **Invoices** | Invoice aktivasi 1:1 dengan tiket; tandai lunas manual; VA 16 digit |
| **Bills** | Tagihan bulanan per tiket per periode (`Y-m`); generate manual; tandai lunas → isolir otomatis dicabut |
| **Customers** | Registry pelanggan billing (hasil konversi prospek + manual); detail terintegrasi + cross-link ke Pelanggan FTTH; unduh CSV tagihan |
| **Reports** | Pendapatan per bulan (invoice + bills) + unduh |
| **Settings** | Persen PPN, tanggal & jam isolir, flag pengingat H-3/H-2/H-1 |

Alur tagihan: tiket selesai → `billing:generate` membuat bill periode berjalan (harga = paket pelanggan saat itu) → reminder H-3/2/1 via WA → lewat jatuh tempo → `billing:auto-isolate` memindahkan profil PPP ke profil isolir → pelanggan bayar → profil asli dikembalikan otomatis.

### 6. WhatsApp (WA)

- **Templates**: 5 template bawaan — `psb`, `pembayaran`, `gangguan`, `isolir`, `selesai` — dengan variabel `{{nama}}`, `{{total}}`, dsb. Bisa diedit, tidak bisa dihapus.
- **Broadcast**: kirim massal per area/paket dengan template atau pesan manual, hanya ke server WAHA yang terhubung.
- Semua pesan transaksional (jadwal PSB, konfirmasi bayar, pengingat isolir, tiket selesai) otomatis dari event.

### 7. Gateway

- **Midtrans**: kelola akun (server key terenkripsi), uji koneksi. Webhook publik `/webhooks/midtrans` verifikasi signature SHA-512.
- **WAHA**: kelola server WhatsApp HTTP API; tombol **Scan QR** membuka dialog polling QR login (status WORKING = tersambung); webhook `/webhooks/waha` diverifikasi per header X-API-Key.

### 8. Server & Perangkat

- **Mikrotik**: CRUD server RouterOS, uji koneksi, set default, detail resource.
- **RADIUS**: CRUD server RADIUS + uji koneksi/database.
- **GenieACS**: CRUD server TR-069 untuk perangkat ONU.

### 9. FTTH

- **Area Code**: kode/nama wilayah (dipakai skema kredensial PPPoE).
- **ODP/ODC**: inventaris titik distribusi.
- **ONU (GenieACS)**: daftar perangkat pelanggan, detail informasi perangkat, reboot, refresh, tambah WAN/WLAN.

### 10. PPP (Mikrotik)

- **Profiles**: sinkron profil dari Mikrotik, kelola rate-limit (dipakai paket).
- **Secrets**: daftar/buat akun PPPoE.
- **Active**: sesi PPPoE aktif real-time.

### 11. Pengguna & Hak Akses

Role bawaan: **SuperAdmin** (semua), **Teknisi** (tiket update, pelanggan update), **Marketing** (prospek, view). Kontrol dua lapis: permission halaman (`UserPermission`) + menu sidebar (`MenuPermission`).

---

## Struktur Proyek

```
app/
  Console/Commands/    # billing:generate, remind, auto-isolate, psb:sync-customers
  Enums/               # Status tiket/pelanggan/customer/invoice/prospek
  Http/Controllers/    # Billing, Tickets, Pelanggan, Prospect, Ftth, Ppp, Gateways, Waha, Servers
  Models/              # Prospect, PsbTicket, Pelanggan, Customer, Bill, Invoice, ...
  Services/            # ProspectApprovalService, MonthlyBillService, WahaSender, MidtransVaService, ...
resources/js/pages/    # Halaman Inertia+React per modul
routes/web.php         # Semua rute web
routes/console.php     # Scheduler
tests/Feature/         # Pest test per modul
.ai/rules/             # Konvensi proyek untuk agent/AI
```

## Pengujian

```bash
php artisan test --compact
```

Semua alur penting (approve → tiket → finish → invoice → generate → isolir → webhook) tercakup test fitur.
