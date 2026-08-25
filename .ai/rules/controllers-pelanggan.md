---
paths:
  - 'app/Models/PsbTicket.php,app/Services/Prospect/ProspectApprovalService.php,app/Services/Billing/MonthlyBillService.php,app/Http/Controllers/Tickets/PsbTicketController.php,app/Http/Controllers/Pelanggan/**'
---

# Controllers Pelanggan

## Integrasi nyata prospect-tiket-pelanggan-billing
Spine integrasi: Prospect→PsbTicket→(Pelanggan + Customer billing) via psb_ticket_id. approve() membuat PELANGGAN dan CUSTOMER (updateOrCreate by psb_ticket_id) dalam transaksi sama. storeFinish → syncCustomerRegistries(activated:true): pelanggan aktif+activated_at, customer aktif; cancel → berhenti/ditangguhkan (JANGAN biarkan phantom aktif). PsbTicket helper WAJIB dipakai utk data tampilan/kontak: customerName(), contactWhatsapp(), customerAreaCode(), customerPackageName() (pelanggan menang atas prospect), billableMonthlyPrice() utk MonthlyBillService (upgrade paket pelanggan otomatis ke tagihan berikut). pelanggans.activated_at ada (cast datetime). Halaman pelanggan/index kirim prop integrasi: source/ticket_number/invoice_*/bills_*/outstanding/isolated.
