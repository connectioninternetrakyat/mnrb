<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the PSB ticket completion template (idempotent).
     */
    public function up(): void
    {
        DB::table('message_templates')->updateOrInsert(
            ['slug' => 'selesai'],
            [
                'name' => 'Pemberitahuan Pemasangan Selesai',
                'body' => "Pelanggan Yth, {{nama}}\nPemasangan internet Anda telah SELESAI.\nNo. Tiket: {{tiket}}\nPaket: {{paket}}\nTeknisi: {{teknisi}}\nTanggal: {{tanggal}}\n\nTagihan aktivasi:\nNo. Invoice: {{invoice}}\nTotal: {{total}}\nMetode: {{metode}}\n\nTerima kasih telah memilih layanan kami.",
                'variables_hint' => '{{nama}}, {{tiket}}, {{paket}}, {{teknisi}}, {{tanggal}}, {{invoice}}, {{total}}, {{metode}}',
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('message_templates')->where('slug', 'selesai')->delete();
    }
};
