---
paths:
  - 'app/Enums/**,app/Http/Controllers/Servers/**,app/Models/MikrotikSetting.php,app/Models/RadiusSetting.php,app/Models/GenieacsSetting.php,resources/js/pages/servers/**'
---

# Servers

## Servers Mikrotik/Radius/GenieACS integration
Singleton settings (Model::current() + $attributes defaults) di tabel mikrotik_settings/radius_settings/genieacs_settings; secret dienkripsi (cast encrypted) & form memakai pola "kosongkan jika tidak diubah" (request nullable + controller skip blank). Mikrotik test/detail pakai RouterOS API evilfreelancer (RouterOS\Config+Client+Query); Radius auth test nyata via dapphp\radius (Access-Request, TYPE_ACCESS_ACCEPT=2/REJECT=3), database test PDO langsung support mysql & pgsql (enum RadiusDriver); GenieACS NBI via Http client basic-auth (/devices). Semua aksi gagal ditandai is_connected=false + last_error, tanpa crash. Menu sidebar Servers (dropdown) dikontrol menus.servers/mikrotik/radius/genieacs; halaman digate UserPermission servers.view/update.
