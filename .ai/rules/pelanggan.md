---
paths:
  - 'app/Models/Pelanggan.php,app/Enums/PelangganStatus.php,app/Http/Controllers/Pelanggan/**,app/Http/Requests/Pelanggan/**,database/migrations/*pelanggans*,resources/js/pages/pelanggan/**'
---

# Pelanggan

## Pelanggan FTTH: auto-create saat approve + CRUD
Pelanggan (menus.pelanggans, sidebar di ATAS Prospect) = pelanggan FTTH; OTOMATIS dibuat oleh ProspectApprovalService::approve dalam transaksi yang sama dengan tiket PSB (link ppp_secret_id dari createSecret, psb_ticket_id, joined_at=now). Status enum aktif/berhenti; aksi stop = updateSecret disabled:true + RADIUS deleteUser; activate = enable + profile paket terkini. Destroy diblok bila ppp_secret_id terisi. Secret dropdown opsional saat create manual = ppp_secrets whereNotIn id pelanggans. Permission pelanggan.view/update/delete; Admin semua, Teknisi view+update+menu, Marketing view+menu. PENTING eager-load nested WAJIB sertakan FK di select induk (prospect:id,name,whatsapp,area_code_id,package_id) — tanpa itu relasi null diam-diam. Jangan buat 2 migration untuk tabel sama.
