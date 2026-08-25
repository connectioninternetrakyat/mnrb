---
paths:
  - 'app/Enums/**,app/Http/Controllers/UserController.php,app/Http/Requests/Users/**,resources/js/pages/users/**'
---

# Users

## User management: matrix permission & proteksi role
Permission (users.view/create/update/delete) didefinisikan di App\Enums\UserPermission::forRole(); superadmin = semua, admin = view+create+update tanpa delete. Proteksi di UserController: hanya superadmin boleh assign role superadmin / mengelola user superadmin; tidak boleh hapus akun sendiri; role superadmin terakhir tidak bisa dicabut/dihapus. Search index pakai whereLike(..., false) agar kompatibel pgsql + sqlite (tests). Menu sidebar Pengguna muncul kondisional via auth.user.permissions yang di-share HandleInertiaRequests.
