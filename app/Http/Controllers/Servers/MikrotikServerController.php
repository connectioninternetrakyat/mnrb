<?php

namespace App\Http\Controllers\Servers;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Servers\MikrotikServerRequest;
use App\Models\MikrotikServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class MikrotikServerController extends Controller
{
    /**
     * Display the Mikrotik servers list.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::ServersView->value);

        return Inertia::render('servers/mikrotik', [
            'servers' => $this->servers(),
            'detail' => null,
        ]);
    }

    /**
     * Store a newly created Mikrotik server.
     */
    public function store(MikrotikServerRequest $request): RedirectResponse
    {
        MikrotikServer::create($this->credentials($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server Mikrotik berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given Mikrotik server.
     */
    public function update(MikrotikServerRequest $request, MikrotikServer $server): RedirectResponse
    {
        $server->update($this->credentials($request, $server->password));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server Mikrotik berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given Mikrotik server.
     */
    public function destroy(MikrotikServer $server): RedirectResponse
    {
        $server->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server Mikrotik berhasil dihapus.')]);

        return back();
    }

    /**
     * Connect to the router via the RouterOS API and verify the credentials.
     */
    public function test(MikrotikServer $server): RedirectResponse
    {
        Gate::authorize(UserPermission::ServersUpdate->value);

        try {
            $client = $this->connect($server);

            $identity = $client->query((new Query('/system/identity/print')))->read();

            $this->markConnected($server);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Berhasil terhubung ke :name (:identity).', [
                    'name' => $server->name,
                    'identity' => data_get($identity, '0.name', 'router'),
                ]),
            ]);
        } catch (Throwable $e) {
            $this->markFailed($server, $e);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Gagal terhubung ke :name: :error', ['name' => $server->name, 'error' => $e->getMessage()])]);
        }

        return back();
    }

    /**
     * Fetch live system information from the router.
     */
    public function detail(MikrotikServer $server): Response
    {
        Gate::authorize(UserPermission::ServersView->value);
        Gate::authorize(UserPermission::ServersUpdate->value);

        $detail = null;

        try {
            $client = $this->connect($server);

            $identity = $client->query((new Query('/system/identity/print')))->read();
            $resource = $client->query((new Query('/system/resource/print')))->read();
            $routerboard = $client->query((new Query('/system/routerboard/print')))->read();

            $detail = [
                'id' => $server->id,
                'name' => $server->name,
                'identity' => data_get($identity, '0.name'),
                'board_name' => data_get($resource, '0.board-name'),
                'architecture' => data_get($resource, '0.architecture-name'),
                'version' => data_get($resource, '0.version'),
                'uptime' => data_get($resource, '0.uptime'),
                'cpu_load' => data_get($resource, '0.cpu-load'),
                'free_memory' => data_get($resource, '0.free-memory'),
                'total_memory' => data_get($resource, '0.total-memory'),
                'routerboard_model' => data_get($routerboard, '0.model'),
                'serial_number' => data_get($routerboard, '0.serial-number'),
            ];

            $this->markConnected($server);
        } catch (Throwable $e) {
            $this->markFailed($server, $e);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Gagal mengambil detail dari :name: :error', ['name' => $server->name, 'error' => $e->getMessage()])]);
        }

        return Inertia::render('servers/mikrotik', [
            'servers' => $this->servers(),
            'detail' => $detail,
        ]);
    }

    /**
     * Mark the given server as the default PPPoE target.
     */
    public function setDefault(MikrotikServer $server): RedirectResponse
    {
        Gate::authorize(UserPermission::ServersUpdate->value);

        DB::transaction(function () use ($server): void {
            MikrotikServer::query()->whereKeyNot($server->id)->update(['is_default' => false]);
            $server->update(['is_default' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name kini menjadi server utama.', ['name' => $server->name])]);

        return back();
    }

    /**
     * Open a RouterOS API client connection.
     */
    private function connect(MikrotikServer $server): Client
    {
        $config = (new Config)
            ->set('host', $server->host)
            ->set('user', $server->username)
            ->set('pass', (string) $server->password)
            ->set('port', $server->port)
            ->set('ssl', $server->use_tls)
            ->set('timeout', $server->timeout);

        return new Client($config);
    }

    /**
     * Persist the success state of the last check.
     */
    private function markConnected(MikrotikServer $server): void
    {
        $server->is_connected = true;
        $server->last_checked_at = now();
        $server->last_error = null;
        $server->save();
    }

    /**
     * Persist the failure state of the last check.
     */
    private function markFailed(MikrotikServer $server, Throwable $e): void
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
    private function credentials(MikrotikServerRequest $request, ?string $currentPassword = null): array
    {
        $data = $request->safe()->except(['password']);

        if ($request->filled('password')) {
            $data['password'] = (string) $request->input('password');
        } elseif ($currentPassword !== null) {
            $data['password'] = $currentPassword;
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
        return MikrotikServer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MikrotikServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'port' => $server->port,
                'username' => $server->username,
                'has_password' => $server->password !== null && $server->password !== '',
                'use_tls' => $server->use_tls,
                'timeout' => $server->timeout,
                'is_connected' => $server->is_connected,
                'last_checked_at' => $server->last_checked_at?->toIso8601String(),
                'last_error' => $server->last_error,
            ])
            ->all();
    }
}
