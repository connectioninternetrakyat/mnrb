---
paths:
  - 'app/Models/Pelanggan.php,app/Services/Prospect/ProspectApprovalService.php,app/Console/Commands/BackfillCustomerRegistriesCommand.php,resources/js/pages/pelanggan/**'
---

# Pages Pelanggan

## URL Google Maps pada pelanggan
pelanggans.maps_url (text nullable) = URL Google Maps pelanggan: dikopi dari prospect saat approve, diisi psb:sync-customers utk baris legacy yang kosong, editable via PelangganRequest maps_url (nullable string max 2000) + input type=url di dialog edit. Detail page menampilkan InfoRow 'Lokasi Google Maps' sebagai <a target=_blank> dgn ikon MapPin/ExternalLink. Jangan validasi url ketat (share link goo.gl panjang).
