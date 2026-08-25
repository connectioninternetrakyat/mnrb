---
paths:
  - app/Console/Commands/BackfillCustomerRegistriesCommand.php
---

# Commands

## Command psb:sync-customers untuk backfill data lama
Data historis (sebelum integrasi) disinkronkan via `php artisan psb:sync-customers`: utk TIAP PsbTicket buat Pelanggan+Customer bila belum ada (firstOrNew by psb_ticket_id, idempoten), rel secret dihubungkan ulang via ProspectCredentialService::forProspect username, status: selesai=aktif+activated_at=finished_at, dibatalkan=berhenti/ditangguhkan, open=turut aktif. Gunakan bila user melapor "prospect/tiket ada tapi pelanggan/customers kosong".
