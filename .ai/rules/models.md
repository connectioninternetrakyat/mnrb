---
paths:
  - 'app/Enums/**,database/seeders/**,app/Models/User.php'
---

# Models

## Roles dikelola via enum UserRole + SuperSeeder
Role names (Superadmin, Admin, Manager Teknisi, NOC, Teknisi, CS, Marketing) hanya didefinisikan di App\Enums\UserRole (backed enum string) beserta description() masing-masing. Jangan hardcode nama role di controller/test/seeder baru — pakai UserRole::case->value. SuperSeeder membuat/menyinkronkan semua role via Role::updateOrCreate dan akun superadmin (dev@mnrb.com); dipanggil dari DatabaseSeeder. User model memakai trait HasRoles (spatie/laravel-permission v8), avatar di-append sebagai avatar_url via accessor.
