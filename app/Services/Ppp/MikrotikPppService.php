<?php

namespace App\Services\Ppp;

use App\Models\MikrotikServer;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use RuntimeException;
use Throwable;

class MikrotikPppService
{
    /**
     * Attribute keys written to Mikrotik for a profile.
     *
     * @var array<int, string>
     */
    private const PROFILE_KEYS = [
        'name',
        'local-address',
        'remote-address',
        'dns-server',
        'rate-limit',
        'comment',
    ];

    public function __construct(public readonly MikrotikServer $server) {}

    /**
     * The Mikrotik server this service is bound to.
     */
    public function server(): MikrotikServer
    {
        return $this->server;
    }

    /**
     * Open the RouterOS API client.
     */
    protected function client(): Client
    {
        $config = (new Config)
            ->set('host', $this->server->host)
            ->set('user', $this->server->username)
            ->set('pass', (string) $this->server->password)
            ->set('port', $this->server->port)
            ->set('ssl', $this->server->use_tls)
            ->set('timeout', $this->server->timeout);

        return new Client($config);
    }

    /**
     * Run a query against the router.
     *
     * @param  array<int, array{0: string, 1: string}>|null  $where
     * @return array<int, array<string, string>>
     */
    protected function read(string $endpoint, ?array $where = null): array
    {
        try {
            $query = new Query($endpoint, $where ?? []);

            return $this->client()->query($query)->read();
        } catch (Throwable $e) {
            throw new RuntimeException(__('Gagal menghubungi Mikrotik: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Write an add/set/remove command to the router.
     *
     * @param  array<string, string>  $equal
     */
    protected function write(string $endpoint, ?string $id = null, array $equal = []): void
    {
        try {
            $query = new Query($endpoint);

            if ($id !== null) {
                $query->equal('.id', $id);
            }

            foreach ($equal as $key => $value) {
                $query->equal($key, $value);
            }

            $response = $this->client()->query($query)->read();

            if (isset($response['!trap'][0]['message'])) {
                throw new RuntimeException((string) $response['!trap'][0]['message']);
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(__('Gagal menghubungi Mikrotik: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * List all PPP profiles on the router.
     *
     * @return array<int, array<string, string>>
     */
    public function profiles(): array
    {
        return $this->read('/ppp/profile/print');
    }

    /**
     * Find a profile row by name.
     *
     * @return array<string, string>|null
     */
    public function findProfile(string $name): ?array
    {
        return collect($this->profiles())
            ->first(fn (array $row): bool => ($row['name'] ?? '') === $name);
    }

    /**
     * Create a PPP profile.
     *
     * @param  array{name: string, local_address?: ?string, remote_address?: ?string, dns_server?: ?string, rate_limit?: ?string, comment?: ?string}  $attributes
     * @return string The router .id of the created profile.
     */
    public function createProfile(array $attributes): string
    {
        $equal = $this->profileEqual($attributes);

        try {
            $query = new Query('/ppp/profile/add');

            foreach ($equal as $key => $value) {
                $query->equal($key, $value);
            }

            $response = $this->client()->query($query)->read();
        } catch (Throwable $e) {
            throw new RuntimeException(__('Gagal membuat profil di Mikrotik: :error', ['error' => $e->getMessage()]));
        }

        $createdId = data_get($response, '0.ret') ?? null;

        $row = $this->findProfile($attributes['name']);

        if (! empty($row['.id'])) {
            return (string) $row['.id'];
        }

        throw new RuntimeException(__('Profil dibuat namun .id tidak ditemukan di Mikrotik.'));
    }

    /**
     * Update a PPP profile by its current name (resolves the .id first).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(string $currentName, array $attributes): void
    {
        $row = $this->findProfile($currentName);

        if ($row === null) {
            throw new RuntimeException(__('Profil :name tidak ditemukan di Mikrotik.', ['name' => $currentName]));
        }

        // RouterOS clears an attribute when written with an empty value,
        // so empty strings are intentional here.
        $equal = $this->profileEqual($attributes);

        if ($equal !== []) {
            $this->write('/ppp/profile/set', $row['.id'], $equal);
        }
    }

    /**
     * Delete a PPP profile by name.
     */
    public function deleteProfile(string $name): void
    {
        $row = $this->findProfile($name);

        if ($row === null) {
            return; // Already gone — treat as success for idempotency.
        }

        $this->write('/ppp/profile/remove', $row['.id']);
    }

    /**
     * List all PPP secrets on the router.
     *
     * @return array<int, array<string, string>>
     */
    public function secrets(): array
    {
        return $this->read('/ppp/secret/print');
    }

    /**
     * Find a secret row by username.
     *
     * @return array<string, string>|null
     */
    public function findSecret(string $username): ?array
    {
        return collect($this->secrets())
            ->first(fn (array $row): bool => ($row['name'] ?? '') === $username);
    }

    /**
     * Create a PPP secret with an explicit disabled state.
     *
     * @return string The router .id of the created secret.
     */
    public function createSecret(string $username, string $password, string $profile, bool $disabled, ?string $comment = null): string
    {
        $equal = [
            'name' => $username,
            'password' => $password,
            'service' => 'pppoe',
            'profile' => $profile,
            'disabled' => $disabled ? 'yes' : 'no',
        ];

        if ($comment !== null && $comment !== '') {
            $equal['comment'] = $comment;
        }

        try {
            $query = new Query('/ppp/secret/add');

            foreach ($equal as $key => $value) {
                $query->equal($key, $value);
            }

            $this->client()->query($query)->read();
        } catch (Throwable $e) {
            throw new RuntimeException(__('Gagal membuat secret di Mikrotik: :error', ['error' => $e->getMessage()]));
        }

        $row = $this->findSecret($username);

        if (! empty($row['.id'])) {
            return (string) $row['.id'];
        }

        throw new RuntimeException(__('Secret dibuat namun .id tidak ditemukan di Mikrotik.'));
    }

    /**
     * Update a PPP secret by username.
     *
     * @param  array{password?: ?string, profile?: ?string, disabled?: ?bool, comment?: ?string}  $changes
     */
    public function updateSecret(string $username, array $changes): void
    {
        $row = $this->findSecret($username);

        if ($row === null) {
            throw new RuntimeException(__('Secret :name tidak ditemukan di Mikrotik.', ['name' => $username]));
        }

        $equal = [];

        if (array_key_exists('password', $changes) && $changes['password'] !== null && $changes['password'] !== '') {
            $equal['=password'] = $changes['password'];
        }

        if (! empty($changes['profile'])) {
            $equal['=profile'] = (string) $changes['profile'];
        }

        if (isset($changes['disabled']) && $changes['disabled'] !== null) {
            $equal['=disabled'] = $changes['disabled'] ? 'yes' : 'no';
        }

        if (array_key_exists('comment', $changes)) {
            $equal['=comment'] = (string) $changes['comment'];
        }

        if ($equal !== []) {
            $this->write('/ppp/secret/set', $row['.id'], $equal);
        }
    }

    /**
     * Delete a PPP secret by username.
     */
    public function deleteSecret(string $username): void
    {
        $row = $this->findSecret($username);

        if ($row === null) {
            return; // Already gone — idempotent.
        }

        $this->write('/ppp/secret/remove', $row['.id']);
    }

    /**
     * List all active PPPoE sessions on the router.
     *
     * @return array<int, array<string, string>>
     */
    public function actives(): array
    {
        return $this->read('/ppp/active/print');
    }

    /**
     * Remove (disconnect) an active session by its .id.
     */
    public function removeActive(string $routerId): void
    {
        $this->write('/ppp/active/remove', $routerId);
    }

    /**
     * Convert platform attribute names into RouterOS argument pairs.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    private function profileEqual(array $attributes): array
    {
        $mapping = [
            'name' => 'name',
            'local_address' => 'local-address',
            'remote_address' => 'remote-address',
            'dns_server' => 'dns-server',
            'rate_limit' => 'rate-limit',
            'comment' => 'comment',
        ];

        $equal = [];

        foreach ($mapping as $platformKey => $mikrotikKey) {
            if (array_key_exists($platformKey, $attributes)) {
                $equal[$mikrotikKey] = (string) $attributes[$platformKey];
            }
        }

        return $equal;
    }
}
