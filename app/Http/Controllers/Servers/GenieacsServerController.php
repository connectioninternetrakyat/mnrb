<?php

namespace App\Http\Controllers\Servers;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Servers\GenieacsServerRequest;
use App\Models\GenieacsServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GenieacsServerController extends Controller
{
    /**
     * Display the GenieACS servers list.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::ServersView->value);

        return Inertia::render('servers/genieacs', [
            'servers' => $this->servers(),
            'detail' => null,
        ]);
    }

    /**
     * Store a newly created GenieACS server.
     */
    public function store(GenieacsServerRequest $request): RedirectResponse
    {
        GenieacsServer::create($this->credentials($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server GenieACS berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given GenieACS server.
     */
    public function update(GenieacsServerRequest $request, GenieacsServer $server): RedirectResponse
    {
        $server->update($this->credentials($request, $server->password));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server GenieACS berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given GenieACS server.
     */
    public function destroy(GenieacsServer $server): RedirectResponse
    {
        $server->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server GenieACS berhasil dihapus.')]);

        return back();
    }

    /**
     * Verify the credentials by calling the GenieACS NBI API.
     */
    public function test(GenieacsServer $server): RedirectResponse
    {
        Gate::authorize(UserPermission::ServersUpdate->value);

        try {
            $response = $this->request($server, 'devices', ['projection' => '_id']);

            if (! $response->successful()) {
                throw new \RuntimeException(__('Server merespons dengan status :status.', ['status' => $response->status()]));
            }

            $this->markConnected($server);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Berhasil terhubung ke :name (:count perangkat terdaftar).', [
                    'name' => $server->name,
                    'count' => is_array($response->json()) ? count($response->json()) : 0,
                ]),
            ]);
        } catch (Throwable $e) {
            $this->markFailed($server, $e);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Gagal terhubung ke :name: :error', ['name' => $server->name, 'error' => $e->getMessage()])]);
        }

        return back();
    }

    /**
     * Fetch a live device list summary from the GenieACS NBI API.
     */
    public function detail(GenieacsServer $server): Response
    {
        Gate::authorize(UserPermission::ServersView->value);
        Gate::authorize(UserPermission::ServersUpdate->value);

        $detail = null;

        try {
            $response = $this->request($server, 'devices');

            if (! $response->successful()) {
                throw new \RuntimeException(__('Server merespons dengan status :status.', ['status' => $response->status()]));
            }

            $devices = collect($response->json())
                ->take(20)
                ->map(fn (array $device): array => [
                    'id' => $device['_id'] ?? null,
                    'product_class' => data_get($device, 'DeviceID.ProductClass'),
                    'manufacturer' => data_get($device, 'DeviceID.Manufacturer'),
                    'serial_number' => data_get($device, 'DeviceID.SerialNumber'),
                    'last_inform' => $device['_lastInform'] ?? null,
                ])
                ->values()
                ->all();

            $detail = [
                'id' => $server->id,
                'name' => $server->name,
                'total_devices' => is_array($response->json()) ? count($response->json()) : 0,
                'devices' => $devices,
            ];

            $this->markConnected($server);
        } catch (Throwable $e) {
            $this->markFailed($server, $e);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Gagal mengambil detail dari :name: :error', ['name' => $server->name, 'error' => $e->getMessage()])]);
        }

        return Inertia::render('servers/genieacs', [
            'servers' => $this->servers(),
            'detail' => $detail,
        ]);
    }

    /**
     * Call the GenieACS Northbound Interface API.
     *
     * @param  array<string, mixed>  $query
     */
    private function request(GenieacsServer $server, string $endpoint, array $query = []): \Illuminate\Http\Client\Response
    {
        $http = Http::timeout(10)
            ->acceptJson();

        if ($server->username !== null && $server->username !== '') {
            $http = $http->withBasicAuth($server->username, (string) $server->password);
        }

        return $http->get(rtrim($server->base_url, '/').'/'.$endpoint, $query);
    }

    /**
     * Persist the success state of the last check.
     */
    private function markConnected(GenieacsServer $server): void
    {
        $server->is_connected = true;
        $server->last_checked_at = now();
        $server->last_error = null;
        $server->save();
    }

    /**
     * Persist the failure state of the last check.
     */
    private function markFailed(GenieacsServer $server, Throwable $e): void
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
    private function credentials(GenieacsServerRequest $request, ?string $currentPassword = null): array
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
        return GenieacsServer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (GenieacsServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'base_url' => $server->base_url,
                'username' => $server->username,
                'has_password' => $server->password !== null && $server->password !== '',
                'is_connected' => $server->is_connected,
                'last_checked_at' => $server->last_checked_at?->toIso8601String(),
                'last_error' => $server->last_error,
            ])
            ->all();
    }
}
