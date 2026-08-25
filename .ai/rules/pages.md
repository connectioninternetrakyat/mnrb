---
paths:
  - 'app/Http/Controllers/DashboardController.php,resources/js/pages/dashboard.tsx'
---

# Pages

## Dashboard nyata + RADIUS online count
Dashboard = DashboardController (bukan Route::inertia lagi): stats pelanggan aktif/total, prospek menunggu, tiket terbuka, tunggakan (bill unpaid), pendapatan bulan-ini (invoice+bill lunas whereYear/Month paid_at — cross-dialect, jangan to_char), pppoe_online dari RadiusPppService::onlineSessionCount() (radacct acctstoptime null; return NULL saat radius tak ada/gagal → UI tampil —). Panel: prospek pending, tiket PSB, tagihan jatuh tempo. Wayfinder: modul tanpa default export pakai { index as x } dan .url untuk href string (RouteDefinition bukan string). RadiusPppService::activeSession/onlineSessionCount bungkus try-catch Throwable agar sqlite test aman.
