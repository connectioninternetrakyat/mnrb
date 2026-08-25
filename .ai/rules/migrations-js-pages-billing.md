---
paths:
  - 'app/Models/Customer.php,app/Enums/CustomerStatus.php,app/Http/Controllers/Billing/CustomerController.php,app/Http/Requests/Billing/CustomerRequest.php,database/migrations/*customers*,resources/js/pages/billing/customers.tsx'
---

# Migrations Js Pages Billing

## Billing Customer CRUD + upgrade/suspend nyata
Customer (menus.billing-customers) = pelanggan billing: name/email/wa/address + area_code_id, package_id, ppp_secret_id (nullable, unik), psb_ticket_id nullable (link ke tiket PSB bila konversi dari prospek). Store opsional 'provision'=1 → buat akun PPPoE via ProspectCredentialService::build(area,name)+PppProvisioningService::make()->createSecret, link ppp_secret_id; guard duplikat username. Suspend/Aktifkan = updateSecret disabled true/false di Mikrotik via app(MikrotikPppService::class) (resolve LAZY dalam handle/action agar $this->instance() test binding bekerja — jangan new langsung). Upgrade paket = updateSecret profile ke profil paket baru + RADIUS upsertUser, wajib package.pppProfile ada. Destroy diblok bila masih punya secret. Unduh tagihan = CSV streamDownload dari bills via psb_ticket_id. Request TANPA rule status (status hanya lewat aksi); wa regex 08/+62.
