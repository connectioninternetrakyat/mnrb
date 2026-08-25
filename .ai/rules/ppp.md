---
paths:
  - 'app/Enums/**,app/Http/Controllers/Ppp/**,app/Services/Ppp/**,app/Models/PppProfile.php,app/Models/PppSecret.php,database/migrations/*ppp*,resources/js/pages/ppp/**'
---

# Ppp

## PPPoE provisioning Mikrotik + Radius auto-sync
Orkestrator PppProvisioningService::make() memakai server DEFAULT (MikrotikServer::default()/RadiusServer::default(), kolom is_default + route set-default di halaman Servers). Profil = grup FreeRADIUS: radgroupreply (Framed-Pool=remote_address, Mikrotik-Rate-Limit=rate_limit, Framed-IP-Address=local_address); Secret = radcheck Cleartext-Password + radusergroup. Urutan tulis: RADIUS dulu lalu Mikrotik; createSecret sengaja aktif di radius & disable=yes di mikrotik. updateSecret self-heal (recreate baris router yang hilang). Sync manual sekali via ppp.profile.sync untuk import profil mikrotik eksisting. RadiusPppService::ensureSchema() deteksi dialek dari PDO driver (mysql/pgsql/sqlite) sehingga test bisa pakai koneksi sqlite. PPP Active live dari /ppp/active/print, kick via DELETE ppp/actives/{session} (.id di-URL-encode).
