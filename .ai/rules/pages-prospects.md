---
paths:
  - 'app/Models/Prospect.php,app/Http/Controllers/ProspectController.php,app/Enums/ProspectStatus.php,app/Http/Requests/Prospects/**,app/Services/Prospect/**,database/migrations/*prospects*,resources/js/pages/prospects/**'
---

# Pages Prospects

## Prospect workflow + auto PPP secret saat approve
Prospect (menus.prospects) = calon pelanggan: marketing/teknisi (role default prospects.view+create+maps.resolve) input; admin (prospects.approve) setujui/tolak. Status enum ProspectStatus string Indonesia; hanya pending boleh update/reject/approve; approved tidak boleh dihapus. Approve via ProspectApprovalService -> PppProvisioningService::createSecret (radius-first, mikrotik disable), kredensial dari ProspectCredentialService: username {kode_area}{nama tanpa spasi lowercase}, password {nomor huruf pertama A=1..Z=26}{nama}; nama wajib diawali huruf. Guard duplikat username via PppSecret mirror. Pembawa = user_id otomatis dari auth. Index paginate(10)+filter status via through(). WAJIB cast float utk latitude/longitude di model — kolom decimal pgsql dikirim sbg string dan merusak .toFixed() frontend (jebakan sama seperti OdpOdc). JANGAN DB::table()->whereKey() (bukan method query builder); pakai Eloquent. Endpoint ftth/maps/resolve shared: butuh maps.resolve ATAU ftth.update (canAny).
