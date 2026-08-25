<?php

namespace App\Http\Controllers\Servers;

use App\Enums\RadiusDriver;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Servers\RadiusServerRequest;
use App\Models\RadiusServer;
use Dapphp\Radius\Radius;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use Throwable;

class RadiusServerController extends Controller
{
    /**
     * Display the RADIUS servers list.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::ServersView->value);

        return Inertia::render('servers/radius', [
            'servers' => $this->servers(),
            'drivers' => RadiusDriver::options(),
        ]);
    }

    /**
     * Store a newly created RADIUS server.
     */
    public function store(RadiusServerRequest $request): RedirectResponse
    {
        RadiusServer::create($this->credentials($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server Radius berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given RADIUS server.
     */
    public function update(RadiusServerRequest $request, RadiusServer $server): RedirectResponse
    {
        $server->update($this->credentials($request, $server->shared_secret, $server->db_password));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server Radius berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given RADIUS server.
     */
    public function destroy(RadiusServer $server): RedirectResponse
    {
        $server->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server Radius berhasil dihapus.')]);

        return back();
    }

    /**
     * Send a real Access-Request to the FreeRADIUS server.
     *
     * Any valid RADIUS response (accept or reject) proves the server is
     * reachable and the shared secret matches.
     */
    public function testRadius(RadiusServer $server): RedirectResponse
    {
        Gate::authorize(UserPermission::ServersUpdate->value);

        $testUsername = (string) request()->input('test_username', '');
        $testPassword = (string) request()->input('test_password', '');

        if ($server->server_host === '' || $server->shared_secret === null || $server->shared_secret === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Lengkapi host dan shared secret FreeRADIUS terlebih dahulu.')]);

            return back();
        }

        try {
            $client = new Radius;
            $client->setServer($server->server_host)
                ->setSecret((string) $server->shared_secret)
                ->setAuthenticationPort($server->auth_port)
                ->setTimeout($server->timeout);

            $response = $client->accessRequest($testUsername, $testPassword);

            if ($response === false) {
                throw new \RuntimeException(__($client->getErrorMessage() ?: 'Tidak ada respons dari server.'));
            }

            $this->markConnected($server);

            if ($response === Radius::TYPE_ACCESS_ACCEPT) {
                Inertia::flash('toast', ['type' => 'success', 'message' => __('Access-Accept: kredensial uji diterima server :name.', ['name' => $server->name])]);
            } elseif ($response === Radius::TYPE_ACCESS_REJECT) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => __('Access-Reject: server merespons namun menolak kredensial uji.')]);
            } else {
                Inertia::flash('toast', ['type' => 'success', 'message' => __('Server merespons dengan kode :code.', ['code' => $response])]);
            }
        } catch (Throwable $e) {
            $this->markFailed($server, $e);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Gagal menguji :name: :error', ['name' => $server->name, 'error' => $e->getMessage()])]);
        }

        return back();
    }

    /**
     * Open a real connection to the RADIUS database (MySQL / PostgreSQL).
     */
    public function testDatabase(RadiusServer $server): RedirectResponse
    {
        Gate::authorize(UserPermission::ServersUpdate->value);

        if ($server->db_host === null || $server->db_host === '' || $server->db_database === null || $server->db_database === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Lengkapi kredensial database radius terlebih dahulu.')]);

            return back();
        }

        try {
            $pdo = $this->connectDatabase($server);

            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

            $this->markConnected($server);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Berhasil terhubung ke database radius (:driver) — versi :version.', [
                    'driver' => $server->db_driver->label(),
                    'version' => $version,
                ]),
            ]);
        } catch (Throwable $e) {
            $this->markFailed($server, $e);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Gagal terhubung ke database radius: :error', ['error' => $e->getMessage()])]);
        }

        return back();
    }

    /**
     * Mark the given server as the default PPPoE radius target.
     */
    public function setDefault(RadiusServer $server): RedirectResponse
    {
        Gate::authorize(UserPermission::ServersUpdate->value);

        DB::transaction(function () use ($server): void {
            RadiusServer::query()->whereKeyNot($server->id)->update(['is_default' => false]);
            $server->update(['is_default' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name kini menjadi database radius utama.', ['name' => $server->name])]);

        return back();
    }

    /**
     * Build a PDO connection for the configured driver.
     */
    private function connectDatabase(RadiusServer $server): PDO
    {
        $port = $server->db_port ?? $server->db_driver->defaultPort();

        $dsn = match ($server->db_driver) {
            RadiusDriver::MySQL => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $server->db_host,
                $port,
                $server->db_database,
            ),
            RadiusDriver::PostgreSQL => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $server->db_host,
                $port,
                $server->db_database,
            ),
        };

        return new PDO($dsn, (string) $server->db_username, (string) $server->db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => $server->timeout,
        ]);
    }

    /**
     * Persist the success state of the last check.
     */
    private function markConnected(RadiusServer $server): void
    {
        $server->is_connected = true;
        $server->last_checked_at = now();
        $server->last_error = null;
        $server->save();
    }

    /**
     * Persist the failure state of the last check.
     */
    private function markFailed(RadiusServer $server, Throwable $e): void
    {
        $server->is_connected = false;
        $server->last_checked_at = now();
        $server->last_error = $e->getMessage();
        $server->save();
    }

    /**
     * Merge validated input with credential-keep-on-blank semantics.
     *
     * @return array<string, mixed>
     */
    private function credentials(RadiusServerRequest $request, ?string $currentSecret = null, ?string $currentDbPassword = null): array
    {
        $data = $request->safe()->except(['shared_secret', 'db_password']);

        if ($request->filled('shared_secret')) {
            $data['shared_secret'] = (string) $request->input('shared_secret');
        } elseif ($currentSecret !== null) {
            $data['shared_secret'] = $currentSecret;
        }

        if ($request->filled('db_password')) {
            $data['db_password'] = (string) $request->input('db_password');
        } elseif ($currentDbPassword !== null) {
            $data['db_password'] = $currentDbPassword;
        }

        return $data;
    }

    /**
     * Shape all servers for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    private function servers(): array
    {
        return RadiusServer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (RadiusServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'server_host' => $server->server_host,
                'auth_port' => $server->auth_port,
                'has_shared_secret' => $server->shared_secret !== null && $server->shared_secret !== '',
                'timeout' => $server->timeout,
                'db_driver' => $server->db_driver->value,
                'db_host' => $server->db_host,
                'db_port' => $server->db_port,
                'db_database' => $server->db_database,
                'db_username' => $server->db_username,
                'has_db_password' => $server->db_password !== null && $server->db_password !== '',
                'is_connected' => $server->is_connected,
                'last_checked_at' => $server->last_checked_at?->toIso8601String(),
                'last_error' => $server->last_error,
            ])
            ->all();
    }
}
