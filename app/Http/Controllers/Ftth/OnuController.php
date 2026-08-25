<?php

namespace App\Http\Controllers\Ftth;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ftth\OnuRequest;
use App\Http\Requests\Ftth\OnuWanRequest;
use App\Http\Requests\Ftth\OnuWlanRequest;
use App\Models\GenieacsServer;
use App\Models\Onu;
use App\Models\Pelanggan;
use App\Services\Genieacs\GenieacsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OnuController extends Controller
{
    /**
     * Display the ONU/ONT list merged with GenieACS live data.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::FtthView->value);

        $genieacs = GenieacsService::make();
        $server = GenieacsServer::default();

        $devices = [];

        if ($genieacs !== null) {
            try {
                // Full document (no projection): DeviceID is required for
                // serial/type columns and some vendors break projections.
                $devices = $genieacs->devices();

                // Discovery-service placeholders are unmanageable stubs.
                $devices = collect($devices)
                    ->filter(fn (array $device): bool => ! str_starts_with(
                        (string) ($device['_id'] ?? ''),
                        'DISCOVERYSERVICE-',
                    ))
                    ->values()
                    ->all();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $mappings = Onu::query()->get()->keyBy('genieacs_id');

        $search = mb_strtolower(trim((string) request('search')));

        $rows = collect($devices)
            ->map(function (array $device) use ($mappings): array {
                $deviceId = (string) ($device['_id'] ?? '');

                $serial = data_get($device, 'DeviceID._SerialNumber');
                $productClass = data_get($device, 'DeviceID._ProductClass');
                $oui = data_get($device, 'DeviceID._OUI');

                // Incomplete CPEs carry their identity inside the _id:
                // {OUI}-{ProductClass}-{Serial}
                if ($serial === null || $productClass === null) {
                    $parsed = GenieacsService::identityFromId($deviceId);

                    if ($parsed !== null) {
                        $serial ??= $parsed['serial'];
                        $productClass ??= $parsed['product_class'];
                        $oui ??= $parsed['oui'];
                    }
                }

                return [
                    'device_id' => $deviceId,
                    'serial' => $serial ?? '-',
                    'product_class' => $productClass ?? '-',
                    'oui' => $oui ?? '-',
                    'last_inform' => isset($device['_lastInform']) ? substr((string) $device['_lastInform'], 0, 19) : null,
                    'onu_id' => $mappings->has($device['_id'] ?? '') ? $mappings[(string) $device['_id']]->id : null,
                    'customer_name' => $mappings->has($device['_id'] ?? '') ? $mappings[(string) $device['_id']]->name : null,
                    'pppoe_user' => $mappings->has($device['_id'] ?? '') ? $mappings[(string) $device['_id']]->pppoe_user : null,
                ];
            })
            ->filter(function (array $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return str_contains(
                    mb_strtolower(
                        $row['customer_name'].' '.$row['pppoe_user'].' '.$row['serial'].' '.$row['product_class'],
                    ),
                    $search,
                );
            })
            ->sortBy([['customer_name', 'asc'], ['device_id', 'asc']])
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, 10)->values()->all(),
            $rows->count(),
            10,
        )->withQueryString();

        return Inertia::render('ftth/onu', [
            'devices' => $paginated,
            'pelanggans' => Pelanggan::query()
                ->orderBy('name')
                ->get(['id', 'name', 'whatsapp'])
                ->map(fn (Pelanggan $pelanggan): array => [
                    'id' => $pelanggan->id,
                    'name' => $pelanggan->name,
                    'whatsapp' => $pelanggan->whatsapp,
                    'secret_username' => $pelanggan->pppSecret?->name,
                ])
                ->all(),
            'unregistered' => $rows
                ->filter(fn (array $row): bool => $row['onu_id'] === null)
                ->take(100)
                ->map(fn (array $row): array => [
                    'value' => $row['device_id'],
                    'label' => $row['serial'].' ('.$row['product_class'].')',
                ])
                ->values()
                ->all(),
            'filters' => [
                'search' => request('search'),
            ],
            'genieacs_server' => $server !== null
                ? ['id' => $server->id, 'name' => $server->name]
                : null,
        ]);
    }

    /**
     * Show the full detail of one ONU/ONT straight from GenieACS.
     */
    public function show(Onu $onu): Response
    {
        Gate::authorize(UserPermission::FtthView->value);

        return Inertia::render('ftth/onu-detail', $this->detailProps($onu));
    }

    /**
     * Attach a GenieACS device to a customer.
     */
    public function store(OnuRequest $request): RedirectResponse
    {
        Onu::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ONU/ONT terdaftar atas nama :name.', ['name' => $request->input('name')])]);

        return back();
    }

    /**
     * Update the customer mapping.
     */
    public function update(OnuRequest $request, Onu $onu): RedirectResponse
    {
        $onu->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Data ONU/ONT diperbarui.')]);

        return back();
    }

    /**
     * Remove only the mapping; the physical device stays in GenieACS.
     */
    public function destroy(Onu $onu): RedirectResponse
    {
        $onu->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pemetaan ONU/ONT dihapus. Perangkat tetap ada di GenieACS.')]);

        return back();
    }

    /**
     * Queue a reboot task on the device.
     */
    public function reboot(Onu $onu): RedirectResponse
    {
        Gate::authorize(UserPermission::FtthUpdate->value);

        try {
            $genieacs = $this->requireGenieacs();

            $genieacs->createTask($onu->genieacs_id, ['name' => 'reboot']);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Perintah reboot dikirim ke :name.', ['name' => $onu->name])]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Ask the CPE to refresh its parameter tree.
     */
    public function refresh(Onu $onu): RedirectResponse
    {
        Gate::authorize(UserPermission::FtthUpdate->value);

        try {
            $genieacs = $this->requireGenieacs();

            $genieacs->createTask($onu->genieacs_id, [
                'name' => 'refreshObject',
                'objectName' => '',
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Refresh parameter dikirim.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Add a new WAN connection on the device.
     */
    public function addWan(OnuWanRequest $request, Onu $onu): RedirectResponse
    {
        Gate::authorize(UserPermission::FtthUpdate->value);

        try {
            $genieacs = $this->requireGenieacs();

            $base = rtrim($request->input('path'), '.');

            $genieacs->createTask($onu->genieacs_id, [
                'name' => 'addObject',
                'objectName' => $base.'.WANIPConnection.',
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Perintah tambah WAN dikirim — refresh perangkat setelahnya.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Write parameters of an existing WAN connection.
     */
    public function editWan(OnuWanRequest $request, Onu $onu): RedirectResponse
    {
        Gate::authorize(UserPermission::FtthUpdate->value);

        try {
            $genieacs = $this->requireGenieacs();

            $base = rtrim($request->input('path'), '.');
            $values = [[
                $base.'.Name',
                (string) $request->input('name'),
                'xsd:string',
            ]];

            if ($request->filled('connection_type')) {
                $values[] = [$base.'.ConnectionType', (string) $request->input('connection_type'), 'xsd:string'];
            }

            if ($request->boolean('nat_enabled')) {
                $values[] = [$base.'.NATEnabled', '1', 'xsd:boolean'];
            } elseif ($request->has('nat_enabled')) {
                $values[] = [$base.'.NATEnabled', '0', 'xsd:boolean'];
            }

            if ($request->filled('vlan_id')) {
                $values[] = [$base.'.VLANIDMark', (string) (int) $request->input('vlan_id'), 'xsd:unsignedInt'];
            }

            if ($request->filled('username')) {
                $values[] = [$base.'.Username', (string) $request->input('username'), 'xsd:string'];
            }

            if ($request->filled('password')) {
                $values[] = [$base.'.Password', (string) $request->input('password'), 'xsd:string'];
            }

            $genieacs->createTask($onu->genieacs_id, [
                'name' => 'setParameterValues',
                'parameterValues' => $values,
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Parameter WAN dikirim ke perangkat.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Add a new WLAN configuration block.
     */
    public function addWlan(OnuWlanRequest $request, Onu $onu): RedirectResponse
    {
        Gate::authorize(UserPermission::FtthUpdate->value);

        try {
            $genieacs = $this->requireGenieacs();

            $base = rtrim($request->input('path'), '.');

            $genieacs->createTask($onu->genieacs_id, [
                'name' => 'addObject',
                'objectName' => $base.'.WLANConfiguration.',
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Perintah tambah WLAN dikirim — atur SSID/password setelah refresh.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Push SSID / password / channel to an existing WLAN.
     */
    public function editWlan(OnuWlanRequest $request, Onu $onu): RedirectResponse
    {
        Gate::authorize(UserPermission::FtthUpdate->value);

        try {
            $genieacs = $this->requireGenieacs();

            $base = rtrim($request->input('path'), '.');
            $password = (string) $request->input('password');

            $values = [
                [$base.'.SSID', (string) $request->input('ssid'), 'xsd:string'],
                [$base.'.BeaconType', 'WPAand11i', 'xsd:string'],
                [$base.'.WPAAuthenticationMode', 'PSKAuthentication', 'xsd:string'],
                [$base.'.EncryptionModes', 'TKIPandAESEnable', 'xsd:string'],
            ];

            if ($password !== '') {
                $values[] = [$base.'.KeyPassphrase', $password, 'xsd:string'];
                $values[] = [$base.'.PreSharedKey', $password, 'xsd:string'];
            }

            if (filled($request->input('channel'))) {
                $values[] = [$base.'.Channel', (string) (int) $request->input('channel'), 'xsd:unsignedInt'];
            }

            $genieacs->createTask($onu->genieacs_id, [
                'name' => 'setParameterValues',
                'parameterValues' => $values,
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('SSID/Password WLAN dikirim ke perangkat.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Build the props for the detail page.
     *
     * @return array<string, mixed>
     */
    private function detailProps(Onu $onu): array
    {
        $genieacs = $this->requireGenieacs();

        $device = [];
        $error = null;

        try {
            $device = $genieacs->device($onu->genieacs_id);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $fallbackId = GenieacsService::identityFromId($onu->genieacs_id);

        $identity = [
            'serial' => data_get($device, 'DeviceID._SerialNumber') ?? $fallbackId['serial'] ?? '-',
            'product_class' => data_get($device, 'DeviceID._ProductClass') ?? $fallbackId['product_class'] ?? '-',
            'oui' => data_get($device, 'DeviceID._OUI') ?? $fallbackId['oui'] ?? '-',
            'model' => $this->firstParam($device, '/\.ModelName$/') ?? '-',
            'software' => $this->firstParam($device, '/\.SoftwareVersion$/') ?? '-',
            'hardware' => $this->firstParam($device, '/\.HardwareVersion$/') ?? '-',
            'last_inform' => isset($device['_lastInform'])
                ? substr((string) $device['_lastInform'], 0, 19)
                : null,
        ];

        $values = GenieacsService::flattenParameters($device);

        $wanBases = collect(array_keys($values))
            ->map(fn (string $path): ?array => preg_match(
                '/^((?:InternetGatewayDevice|Device)\.WANDevice\.\d+\.WANConnectionDevice\.\d+)\.WANIPConnection\./',
                $path,
            ) === 1 ? [$path] : null)
            ->filter()
            ->flatten()
            ->map(fn (string $path): string => preg_replace('/\.WANIPConnection\.\d+\..*$/', '', $path))
            ->unique()
            ->values()
            ->all();

        $wlanBases = collect(array_keys($values))
            ->map(fn (string $path): ?string => preg_match(
                '/^((?:InternetGatewayDevice|Device)\.LANDevice\.\d+)\.WLANConfiguration\.\d+\./',
                $path,
            ) === 1 ? preg_replace('/\.WLANConfiguration\.\d+\..*$/', '', $path) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'onu' => [
                'id' => $onu->id,
                'genieacs_id' => $onu->genieacs_id,
                'name' => $onu->name,
                'pppoe_user' => $onu->pppoe_user,
                'description' => $onu->description,
            ],
            'identity' => $identity,
            'wans' => GenieacsService::wanConnections($device),
            'wlans' => GenieacsService::wlanConfigurations($device),
            'hosts' => GenieacsService::connectedHosts($device),
            'temperatures' => GenieacsService::temperatures($device),
            'wan_bases' => $wanBases,
            'wlan_bases' => $wlanBases,
            'error' => $error,
        ];
    }

    /**
     * First matching flattened parameter value.
     *
     * @param  array<string, mixed>  $device
     */
    private function firstParam(array $device, string $pattern): ?string
    {
        foreach (GenieacsService::flattenParameters($device) as $path => $value) {
            if (preg_match($pattern, $path) === 1 && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Require a configured GenieACS server.
     */
    private function requireGenieacs(): GenieacsService
    {
        $service = GenieacsService::make();

        if ($service === null) {
            throw new RuntimeException(__('Belum ada server GenieACS. Tambahkan di menu Servers.'));
        }

        return $service;
    }
}
