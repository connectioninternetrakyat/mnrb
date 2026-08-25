<?php

use App\Console\Commands\AutoIsolateCommand;
use App\Console\Commands\GenerateMonthlyBillsCommand;
use App\Console\Commands\SendIsolationRemindersCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Billing Automation
|--------------------------------------------------------------------------
|
| - Tagihan bulanan digenerate otomatis setiap awal hari (idempoten).
| - Pengingat WhatsApp dikirim H-3/H-2/H-1 sebelum auto isolir.
| - Isolir otomatis dijalankan berkala; perintahnya sendiri memastikan
|   waktu isolir (tanggal + jam dari Billing > Setting) sudah terlewati.
|
*/

Schedule::command(GenerateMonthlyBillsCommand::class)->dailyAt('00:10');

Schedule::command(SendIsolationRemindersCommand::class)->dailyAt('09:00');

Schedule::command(AutoIsolateCommand::class)->hourly();
