<?php

namespace App\Http\Controllers\Ppp;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\MikrotikServer;
use App\Services\Ppp\MikrotikPppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PppActiveController extends Controller
{
    /**
     * Display the live active PPPoE sessions of the default router.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::PppView->value);

        $server = MikrotikServer::default();

        $sessions = new LengthAwarePaginator([], 0, 10);
        $error = null;

        if ($server !== null) {
            try {
                $search = mb_strtolower(trim((string) request('search')));

                $rows = collect((new MikrotikPppService($server))->actives())
                    ->map(fn (array $row): array => [
                        'id' => $row['.id'] ?? '',
                        'name' => $row['name'] ?? '-',
                        'service' => $row['service'] ?? '-',
                        'caller_id' => $row['caller-id'] ?? '-',
                        'address' => $row['address'] ?? '-',
                        'uptime' => $row['uptime'] ?? '-',
                        'encoding' => $row['encoding'] ?? '-',
                    ]);

                if ($search !== '') {
                    $rows = $rows->filter(
                        fn (array $session): bool => str_contains(
                            mb_strtolower($session['name'].' '.$session['caller_id'].' '.$session['address']),
                            $search,
                        ),
                    )->values();
                }

                $page = LengthAwarePaginator::resolveCurrentPage();

                $sessions = new LengthAwarePaginator(
                    $rows->forPage($page, 10)->values()->all(),
                    $rows->count(),
                    10,
                )->withQueryString();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('ppp/active', [
            'sessions' => $sessions,
            'filters' => [
                'search' => request('search'),
            ],
            'mikrotik_server' => $server !== null
                ? ['id' => $server->id, 'name' => $server->name]
                : null,
            'error' => $error,
        ]);
    }

    /**
     * Remove (disconnect) an active session — like Winbox.
     */
    public function destroy(string $session): RedirectResponse
    {
        Gate::authorize(UserPermission::PppUpdate->value);

        $server = MikrotikServer::default();

        if ($server === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Belum ada server Mikrotik utama.')]);

            return back();
        }

        try {
            (new MikrotikPppService($server))->removeActive($session);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Sesi PPPoE diputus.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
