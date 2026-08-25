# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Models/BillingSetting.php,app/Http/Controllers/Billing/**,app/Http/Requests/Billing/**,app/Services/Billing/**,database/migrations/*billing_settings*,resources/js/pages/billing/** | .ai/rules/billing.md |
| app/Console/Commands/BackfillCustomerRegistriesCommand.php | .ai/rules/commands.md |
| app/Services/Billing/**,config/billing.php,resources/js/pages/tickets/psb/components/payment-estimate-card.tsx | .ai/rules/components.md |
| app/Http/Controllers/Gateways/WahaServerController.php | .ai/rules/controllers-gateways.md |
| app/Models/PsbTicket.php,app/Services/Prospect/ProspectApprovalService.php,app/Services/Billing/MonthlyBillService.php,app/Http/Controllers/Tickets/PsbTicketController.php,app/Http/Controllers/Pelanggan/** | .ai/rules/controllers-pelanggan.md |
| app/Models/Invoice.php,app/Enums/InvoiceStatus.php,app/Http/Controllers/Gateways/MidtransWebhookController.php,database/migrations/*invoices*,app/Http/Controllers/Tickets/PsbTicketController.php | .ai/rules/controllers-tickets.md |
| app/Enums/**,database/seeders/SuperSeeder.php,app/Http/Controllers/RoleController.php | .ai/rules/controllers.md |
| app/Enums/**,app/Http/Controllers/Ftth/**,app/Services/GoogleMapsUrlResolver.php,app/Models/AreaCode.php,app/Models/OdpOdc.php,database/migrations/*area_codes*,database/migrations/*odp_odcs*,resources/js/pages/ftth/** | .ai/rules/ftth.md |
| app/Http/Controllers/Gateways/WahaServerController.php,resources/js/pages/gateways/components/waha-qr-dialog.tsx | .ai/rules/gateways-components.md |
| app/Enums/**,app/Http/Controllers/Gateways/**,app/Models/MidtransSetting.php,app/Models/WahaSetting.php,database/migrations/*midtrans*,database/migrations/*waha*,resources/js/pages/gateways/** | .ai/rules/gateways.md |
| app/Services/Billing/MidtransVaService.php,app/Http/Controllers/Tickets/PsbTicketController.php | .ai/rules/http-controllers-tickets.md |
| app/Models/Bill.php,app/Services/Billing/MonthlyBillService.php,app/Http/Controllers/Billing/BillController.php,database/migrations/*bills*,resources/js/pages/billing/bills.tsx | .ai/rules/js-pages-billing.md |
| app/Models/Customer.php,app/Enums/CustomerStatus.php,app/Http/Controllers/Billing/CustomerController.php,app/Http/Requests/Billing/CustomerRequest.php,database/migrations/*customers*,resources/js/pages/billing/customers.tsx | .ai/rules/migrations-js-pages-billing.md |
| app/Models/MessageTemplate.php,app/Models/Broadcast.php,app/Http/Controllers/Waha/**,app/Services/Waha/**,resources/js/pages/wa/**,database/migrations/*message_templates*,database/migrations/*broadcasts* | .ai/rules/migrations-migrations.md |
| app/Http/Controllers/Gateways/**,app/Http/Controllers/Servers/**,app/Models/MidtransAccount.php,app/Models/WahaServer.php,app/Models/MikrotikServer.php,app/Models/RadiusServer.php,app/Models/GenieacsServer.php,database/migrations/*midtrans*,database/migrations/*waha*,database/migrations/*mikrotik*,database/migrations/*radius*,database/migrations/*genieacs* | .ai/rules/migrations.md |
| app/Http/Controllers/Tickets/PsbTicketController.php,app/Models/MessageTemplate.php,database/migrations/*message_templates* | .ai/rules/models-migrations.md |
| app/Enums/**,database/seeders/**,app/Models/User.php | .ai/rules/models.md |
| app/Models/Package.php,app/Http/Controllers/PackageController.php,app/Http/Requests/Packages/**,database/migrations/*packages*,resources/js/pages/packages/** | .ai/rules/packages.md |
| app/Http/Controllers/Billing/InvoiceController.php,resources/js/pages/billing/invoices.tsx, app/Http/Controllers/Billing/FinancialReportController.php,resources/js/pages/billing/reports.tsx | .ai/rules/pages-billing.md |
| app/Services/Genieacs/**,app/Http/Controllers/Ftth/OnuController.php,app/Models/Onu.php,resources/js/pages/ftth/onu*.tsx | .ai/rules/pages-ftth.md |
| app/Http/Controllers/Gateways/WahaServerController.php,resources/js/pages/gateways/components/waha-qr-dialog.tsx,resources/js/pages/gateways/waha.tsx | .ai/rules/pages-gateways.md |
| app/Models/Pelanggan.php,app/Services/Prospect/ProspectApprovalService.php,app/Console/Commands/BackfillCustomerRegistriesCommand.php,resources/js/pages/pelanggan/** | .ai/rules/pages-pelanggan.md |
| app/Models/Prospect.php,app/Http/Controllers/ProspectController.php,app/Enums/ProspectStatus.php,app/Http/Requests/Prospects/**,app/Services/Prospect/**,database/migrations/*prospects*,resources/js/pages/prospects/** | .ai/rules/pages-prospects.md |
| app/Http/Controllers/DashboardController.php,resources/js/pages/dashboard.tsx | .ai/rules/pages.md |
| app/Http/Controllers/Pelanggan/PelangganController.php,app/Http/Controllers/Billing/CustomerController.php,resources/js/pages/pelanggan/**,resources/js/pages/billing/** | .ai/rules/pelanggan-js-pages-billing.md |
| app/Models/Pelanggan.php,app/Enums/PelangganStatus.php,app/Http/Controllers/Pelanggan/**,app/Http/Requests/Pelanggan/**,database/migrations/*pelanggans*,resources/js/pages/pelanggan/** | .ai/rules/pelanggan.md |
| app/Enums/**,app/Http/Controllers/RoleController.php,app/Http/Controllers/RolePermissionController.php,app/Http/Requests/Roles/**,resources/js/pages/roles/**,resources/js/pages/permissions/** | .ai/rules/permissions.md |
| app/Enums/**,app/Http/Controllers/Ppp/**,app/Services/Ppp/**,app/Models/PppProfile.php,app/Models/PppSecret.php,database/migrations/*ppp*,resources/js/pages/ppp/** | .ai/rules/ppp.md |
| app/Console/Commands/**,app/Services/Waha/**,routes/console.php,app/Providers/AppServiceProvider.php | .ai/rules/providers.md |
| app/Enums/**,app/Http/Controllers/Servers/**,app/Models/MikrotikSetting.php,app/Models/RadiusSetting.php,app/Models/GenieacsSetting.php,resources/js/pages/servers/** | .ai/rules/servers.md |
| app/Services/Ppp/** | .ai/rules/services-ppp.md |
| app/Models/PsbTicket.php,app/Enums/PsbTicketStatus.php,app/Http/Controllers/Tickets/**,app/Http/Requests/Tickets/**,database/migrations/*psb_tickets*,resources/js/pages/tickets/** | .ai/rules/tickets.md |
| app/Enums/**,app/Http/Controllers/UserController.php,app/Http/Requests/Users/**,resources/js/pages/users/** | .ai/rules/users.md |
