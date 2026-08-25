<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default templates seeded here (idempotent).
     */
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('body');
            $table->string('variables_hint', 500)->nullable();
            $table->timestamps();
        });

        foreach ($this->defaults() as $template) {
            DB::table('message_templates')->updateOrInsert(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'body' => $template['body'],
                    'variables_hint' => $template['variables_hint'],
                ],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }

    private function defaults(): array
    {
        return [
            [
                'slug' => 'psb',
                'name' => 'Pemberitahuan PSB',
                'body' => "Pelanggan Yth, {{nama}}\nPemasangan internet Anda telah dijadwalkan:\nJadwal: {{jadwal}}\nTeknisi: {{teknisi}}\nPaket: {{paket}}\nAlamat: {{alamat}}\nMohon persiapannya. Terima kasih.",
                'variables_hint' => '{{nama}}, {{jadwal}}, {{teknisi}}, {{paket}}, {{alamat}}, {{area}}',
            ],
            [
                'slug' => 'pembayaran',
                'name' => 'Konfirmasi Pembayaran',
                'body' => "Pembayaran diterima.\nNama: {{nama}}\nNo. Tagihan: {{tagihan}}\nPeriode: {{periode}}\nTotal: {{total}}\nMetode: {{metode}}\nTerima kasih telah membayar tepat waktu.",
                'variables_hint' => '{{nama}}, {{tagihan}}, {{periode}}, {{total}}, {{metode}}',
            ],
            [
                'slug' => 'gangguan',
                'name' => 'Pemberitahuan Gangguan',
                'body' => "Pelanggan Yth, {{nama}}\nKami mohon maaf atas gangguan layanan internet di area {{area}}. Tim teknis kami sedang menangani perbaikan.\nEstimasi normal dalam beberapa jam ke depan.",
                'variables_hint' => '{{nama}}, {{area}}, {{paket}}',
            ],
            [
                'slug' => 'isolir',
                'name' => 'Pengingat Isolir',
                'body' => "Pelanggan Yth, {{nama}}\nTagihan {{tagihan}} belum kami terima.\nIsolir otomatis akan dijalankan pada {{tanggal_isolir}} jam {{jam_isolir}}.\nSegera lakukan pembayaran untuk menghindari isolir.",
                'variables_hint' => '{{nama}}, {{tagihan}}, {{tanggal_isolir}}, {{jam_isolir}}, {{paket}}',
            ],
        ];
    }
};
