---
paths:
  - 'app/Http/Controllers/Gateways/**,app/Http/Controllers/Servers/**,app/Models/MidtransAccount.php,app/Models/WahaServer.php,app/Models/MikrotikServer.php,app/Models/RadiusServer.php,app/Models/GenieacsServer.php,database/migrations/*midtrans*,database/migrations/*waha*,database/migrations/*mikrotik*,database/migrations/*radius*,database/migrations/*genieacs*'
---

# Migrations

## Gateway & server resources adalah multi-row CRUD (bukan singleton)
Tabel: midtrans_accounts (+is_active), waha_servers, mikrotik_servers, radius_servers, genieacs_servers — masing-masing punya kolom name dan dikelola via resource controller (index/store/update/destroy) + action routes (test, detail, test-radius, test-database). Secret tetap encrypted + pola "kosongkan jika tidak diubah" di credentials(). Webhook Midtrans/WAHA memverifikasi terhadap SEMUA akun/server tersimpan (signature per server_key / X-Api-Key per server) hingga cocok; midtrans hanya is_active=true. Halaman Inertia menampilkan tabel list + dialog create/edit/hapus/test; View Detail me-render ulang halaman index dengan prop detail lalu dibuka sebagai dialog (mikrotik & genieacs). Kolom timestamp (last_checked_at dll) WAJIB dicast datetime agar toIso8601String tidak error.
