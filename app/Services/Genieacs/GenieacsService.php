<?php

namespace App\Services\Genieacs;

use App\Models\GenieacsServer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GenieacsService
{
    public function __construct(public readonly GenieacsServer $server) {}

    /**
     * Build the service from the default GenieACS server.
     */
    public static function make(): ?self
    {
        $server = GenieacsServer::default();

        return $server !== null ? new self($server) : null;
    }

    /**
     * Call the Northbound Interface API.
     *
     * @param  array<string, mixed>  $query
     */
    public function request(string $endpoint, array $query = [], string $method = 'get', ?array $body = null): Response
    {
        $http = Http::timeout(15)->acceptJson();

        if ($this->server->username !== null && $this->server->username !== '') {
            $http = $http->withBasicAuth($this->server->username, (string) $this->server->password);
        }

        try {
            return $http->{strtolower($method)}(
                rtrim($this->server->base_url, '/').'/'.$endpoint,
                $method === 'get' ? $query : ($body ?? []),
            );
        } catch (Throwable $e) {
            throw new RuntimeException(__('Tidak dapat menghubungi GenieACS: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * List devices with optional mongo-style query and projection.
     *
     * @param  array<string, mixed>|null  $query
     * @param  array<int, string>|null  $projection
     * @return array<int, array<string, mixed>>
     */
    public function devices(?array $query = null, ?array $projection = null): array
    {
        $params = [];

        if ($query !== null && $query !== []) {
            $params['query'] = json_encode($query);
        }

        if ($projection !== null && $projection !== []) {
            $params['projection'] = implode(',', $projection);
        }

        $response = $this->request('devices', $params);

        if (! $response->successful()) {
            throw new RuntimeException(__('GenieACS merespons dengan status :status.', ['status' => $response->status()]));
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    /**
     * Get a single device with its full parameter tree.
     *
     * @return array<string, mixed>
     */
    public function device(string $id): array
    {
        $response = $this->request('devices/'.rawurlencode($id));

        if ($response->notFound()) {
            throw new RuntimeException(__('Perangkat tidak ditemukan di GenieACS.'));
        }

        if (! $response->successful()) {
            throw new RuntimeException(__('GenieACS merespons dengan status :status.', ['status' => $response->status()]));
        }

        return (array) $response->json();
    }

    /**
     * Queue a TR-069 task for a device.
     *
     * @param  array{name: string, parameterValues?: array<int, array<int, mixed>>, objectName?: string}  $task
     * @return array<string, mixed>
     */
    public function createTask(string $deviceId, array $task): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson();

        if ($this->server->username !== null && $this->server->username !== '') {
            $response = $response->withBasicAuth($this->server->username, (string) $this->server->password);
        }

        try {
            $response = $response->post(
                rtrim($this->server->base_url, '/').'/devices/'.rawurlencode($deviceId).'/tasks?timeout=15000&connection_request',
                $task,
            );
        } catch (Throwable $e) {
            throw new RuntimeException(__('Tidak dapat menghubungi GenieACS: :error', ['error' => $e->getMessage()]));
        }

        if (! in_array($response->status(), [200, 201], true)) {
            throw new RuntimeException(__('GenieACS merespons dengan status :status.', ['status' => $response->status()]));
        }

        return (array) $response->json();
    }

    // ------------------------------------------------------------------
    // Parsers over the flat parameter map of a device document
    // ------------------------------------------------------------------

    /**
     * Extract every value of a flattened parameter tree.
     *
     * GenieACS stores parameters as objects with an "_value" key.
     *
     * @param  array<string, mixed>  $device
     * @return array<string, string>
     */
    public static function flattenParameters(array $device): array
    {
        $values = [];

        foreach ($device as $key => $node) {
            if (! is_array($node)) {
                continue;
            }

            if (array_key_exists('_value', $node)) {
                $values[$key] = (string) $node['_value'];
            } elseif (array_key_exists('value', $node)) {
                $values[$key] = (string) $node['value'];
            }
        }

        return $values;
    }

    /**
     * Parse OUI / product class / serial from a device _id
     * ({OUI}-{ProductClass}-{Serial}) used when DeviceID is incomplete.
     *
     * @return array{oui: string, product_class: string, serial: string}|null
     */
    public static function identityFromId(string $id): ?array
    {
        if (str_starts_with($id, 'DISCOVERYSERVICE-')) {
            return null;
        }

        if (preg_match('/^([^-]+)-([^-]+)-(.+)$/', $id, $m) === 1) {
            return [
                'oui' => $m[1],
                'product_class' => $m[2],
                'serial' => $m[3],
            ];
        }

        return null;
    }

    /**
     * Group WAN connection parameters by their base path.
     *
     * @param  array<string, mixed>  $device
     * @return array<int, array{id: string, name: string, connection_type: string, status: string, nat_enabled: ?bool, external_ip: string, mac: string, vlan: string}>
     */
    public static function wanConnections(array $device): array
    {
        $values = self::flattenParameters($device);
        $groups = [];

        foreach ($values as $path => $value) {
            if (preg_match('/^((?:InternetGatewayDevice|Device)\.WANDevice\.\d+\.WANConnectionDevice\.\d+\.WANIPConnection\.\d+)\.(.+)$/', $path, $m) === 1) {
                $groups[$m[1]][$m[2]] = $value;
            }
        }

        ksort($groups);

        return array_map(fn (string $base, array $params): array => [
            'id' => $base,
            'name' => $params['Name'] ?? '-',
            'connection_type' => $params['ConnectionType'] ?? '-',
            'status' => $params['Status'] ?? '-',
            'nat_enabled' => isset($params['NATEnabled']) ? $params['NATEnabled'] === '1' || strtolower($params['NATEnabled']) === 'true' : null,
            'external_ip' => $params['ExternalIPAddress'] ?? '-',
            'mac' => $params['MACAddress'] ?? '-',
            'vlan' => $params['VLANIDMark'] ?? '-',
        ], array_keys($groups), array_values($groups));
    }

    /**
     * Group WLAN configuration parameters by base path.
     *
     * @param  array<string, mixed>  $device
     * @return array<int, array{id: string, enable: bool, ssid: string, password: string, standard: string, channel: string}>
     */
    public static function wlanConfigurations(array $device): array
    {
        $values = self::flattenParameters($device);
        $groups = [];

        foreach ($values as $path => $value) {
            if (preg_match('/^((?:InternetGatewayDevice|Device)\.LANDevice\.\d+\.WLANConfiguration\.\d+)\.(.+)$/', $path, $m) === 1) {
                $groups[$m[1]][$m[2]] = $value;
            }
        }

        ksort($groups);

        return array_map(fn (string $base, array $params): array => [
            'id' => $base,
            'enable' => in_array(strtolower($params['Enable'] ?? ''), ['1', 'true'], true),
            'ssid' => $params['SSID'] ?? '-',
            'password' => $params['KeyPassphrase']
                ?? $params['PreSharedKey']
                ?? ($params['X_AIRTIES_KeyPassphrase'] ?? ''),
            'standard' => $params['Standard'] ?? '-',
            'channel' => $params['Channel'] ?? '-',
        ], array_keys($groups), array_values($groups));
    }

    /**
     * Connected client hosts reported by the CPE.
     *
     * @param  array<string, mixed>  $device
     * @return array<int, array{hostname: string, ip: string, mac: string, interface: string, active: bool}>
     */
    public static function connectedHosts(array $device): array
    {
        $values = self::flattenParameters($device);
        $hosts = [];

        foreach ($values as $path => $value) {
            if (preg_match('/^(?:InternetGatewayDevice|Device)\.LANDevice\.\d+\.Hosts\.Host\.(\d+)\.(HostName|IPAddress|MACAddress|InterfaceType|Active)$/', $path, $m) === 1) {
                $hosts[$m[1]][$m[2]] = $value;
            }
        }

        ksort($hosts);

        return array_values(array_map(fn (array $host): array => [
            'hostname' => $host['HostName'] ?? '-',
            'ip' => $host['IPAddress'] ?? '-',
            'mac' => $host['MACAddress'] ?? '-',
            'interface' => $host['InterfaceType'] ?? '-',
            'active' => in_array(strtolower((string) ($host['Active'] ?? '')), ['1', 'true'], true),
        ], $hosts));
    }

    /**
     * Any parameter that looks like a temperature sensor reading.
     *
     * @param  array<string, mixed>  $device
     * @return array<int, array{path: string, value: string}>
     */
    public static function temperatures(array $device): array
    {
        $values = self::flattenParameters($device);

        return collect($values)
            ->filter(fn (string $value, string $path): bool => preg_match('/temperature/i', $path) === 1 && is_numeric(trim($value)))
            ->map(fn (string $value, string $path): array => ['path' => $path, 'value' => trim($value)])
            ->values()
            ->all();
    }
}
