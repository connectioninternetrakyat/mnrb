---
paths:
  - 'app/Models/PsbTicket.php,app/Enums/PsbTicketStatus.php,app/Http/Controllers/Tickets/**,app/Http/Requests/Tickets/**,database/migrations/*psb_tickets*,resources/js/pages/tickets/**'
---

# Tickets

## Tiket PSB: alur terjadwal→dikerjakan→selesai/dibatalkan
Tiket PSB (menus.tickets + menus.tickets-psb, dropdown di bawah FTTH) dibuat OTOMATIS oleh ProspectApprovalService saat prospect disetujui (approve kini butuh user_id teknisi + scheduled_at via ApproveProspectRequest, after:now). Nomor tiket PSB-YYYYMMDD-NNNN dari PsbTicket::generateNumber(). Status enum PsbTicketStatus Indonesia; transisi: progress.store (scheduled|in_progress→in_progress, set started_at sekali), finish.store HANYA dari in_progress→selesai, cancel WAJIB cancel_reason (min:5) dan hanya dari tiket open. Tiket closed = terminal (guard redirectClosed flash toast). Halaman: tickets/psb/index (paginate(10)+search+filter status), detail, progress, finish — GET page + POST store dipisah route. Teknisi dropdown = users dengan role spatie 'Teknisi' via $role->users(). Permission: tickets.view (semua role operasional), tickets.update (teknisi+admin).
