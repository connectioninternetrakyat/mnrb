---
paths:
  - 'app/Enums/**,app/Http/Controllers/RoleController.php,app/Http/Controllers/RolePermissionController.php,app/Http/Requests/Roles/**,resources/js/pages/roles/**,resources/js/pages/permissions/**'
---

# Permissions

## Role & menu permission system
Role CRUD (RoleController) membaca role dari DB (spatie Role), bukan enum UserRole — UserRole hanya untuk seed awal & proteksi superadmin. Role superadmin terkunci: tidak bisa rename/hapus/ubah permission-nya. Permission sidebar = App\Enums\MenuPermission (menus.users/roles/permissions); halaman Permission menyinkronkan menu per role via RolePermissionController yang mempertahankan permission non-menu (users.view dll). Sidebar difilter via auth.user.permissions 'menus.*' di app-sidebar.tsx. Role baru otomatis muncul di form user karena UserController::assignableRoles() + validasi Rule::exists('roles','name') membaca DB.
