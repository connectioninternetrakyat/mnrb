---
paths:
  - 'app/Models/Package.php,app/Http/Controllers/PackageController.php,app/Http/Requests/Packages/**,database/migrations/*packages*,resources/js/pages/packages/**'
---

# Packages

## Paket & Harga module terikat 1:1 ke ppp_profiles
Menu "Paket & Harga" (menus.packages, di atas PPPoE) = CRUD paket: name, ppp_profile_id (FK constrained + UNIQUE ke ppp_profiles — satu profil satu paket), price (rupiah integer), description. Dropdown profil di halaman packages/index diisi dari tabel ppp_profiles server Mikrotik DEFAULT (mirror sinkron), opsi yang sudah dipakai dinonaktifkan; validasi Rule::exists + Rule::unique(ignore route package). Hapus paket TIDAK menghapus profil Mikrotik/Radius. Permission aksi: packages.view/create/update/delete (UserPermission). Route resource 'packages' names('packages'), wayfinder --with-form.
