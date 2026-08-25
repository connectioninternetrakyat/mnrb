---
paths:
  - 'app/Enums/**,database/seeders/SuperSeeder.php,app/Http/Controllers/RoleController.php'
---

# Controllers

## Nama & deskripsi role resmi
Role names adalah display names: Superadmin, Admin, Manager Teknisi, NOC, NOI, Teknisi, CS, Marketing — didefinisikan hanya di App\Enums\UserRole (value + description()). SuperSeeder pakai Role::updateOrCreate agar deskripsi tersinkron saat reseed. Jangan hardcode nama role lama (superadmin/noc/customer service dll) — selalu pakai UserRole::case->value.
