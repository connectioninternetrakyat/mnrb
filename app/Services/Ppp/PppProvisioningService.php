<?php

namespace App\Services\Ppp;

use App\Models\MikrotikServer;
use App\Models\PppProfile;
use App\Models\PppSecret;
use App\Models\RadiusServer;
use RuntimeException;
use Throwable;

/**
 * Orchestrates PPP provisioning across Mikrotik (RouterOS API) and the
 * FreeRADIUS database so every platform action lands on both systems.
 */
class PppProvisioningService
{
    public function __construct(
        private readonly ?MikrotikPppService $mikrotik,
        private readonly ?RadiusPppService $radius,
    ) {}

    /**
     * Build the orchestrator from the default servers.
     */
    public static function make(): self
    {
        $mikrotikServer = MikrotikServer::default();
        $radiusServer = RadiusServer::default();

        $mikrotik = $mikrotikServer !== null ? new MikrotikPppService($mikrotikServer) : null;

        // A broken radius configuration must not crash listing flows;
        // provisioning methods surface their own errors.
        $radius = null;

        if ($radiusServer !== null) {
            try {
                $radius = RadiusPppService::forServer($radiusServer);
            } catch (Throwable) {
                $radius = null;
            }
        }

        return new self($mikrotik, $radius);
    }

    /**
     * The default Mikrotik server bound to this orchestrator.
     */
    public function mikrotikServer(): ?MikrotikServer
    {
        return $this->mikrotik !== null ? $this->mikrotik->server : null;
    }

    // ------------------------------------------------------------------
    // Profiles
    // ------------------------------------------------------------------

    /**
     * Create a PPP profile on Mikrotik and its group in Radius.
     *
     * @param  array{name: string, local_address?: ?string, remote_address?: ?string, dns_server?: ?string, rate_limit?: ?string}  $attributes
     */
    public function createProfile(array $attributes): PppProfile
    {
        $this->assertMikrotik();
        $this->assertRadius();

        $this->radiusEnsureSchema();

        $this->radius->upsertGroup($attributes['name'], $this->profileGroupAttributes($attributes));

        $mikrotikId = $this->mikrotik->createProfile([
            ...$attributes,
            'comment' => 'dibuat oleh panel',
        ]);

        return PppProfile::create([
            'mikrotik_server_id' => $this->mikrotik->server->id,
            'mikrotik_id' => $mikrotikId,
            ...$attributes,
        ]);
    }

    /**
     * Update a PPP profile on both systems.
     *
     * @param  array{name: string, local_address?: ?string, remote_address?: ?string, dns_server?: ?string, rate_limit?: ?string}  $attributes
     */
    public function updateProfile(PppProfile $profile, array $attributes): void
    {
        $this->assertMikrotik();
        $this->assertRadius();

        $this->mikrotik->updateProfile($profile->name, [
            ...$attributes,
            'comment' => $profile->comment ?? 'dibuat oleh panel',
        ]);

        $this->radius->upsertGroup($attributes['name'], $this->profileGroupAttributes($attributes));

        // When the name changed, drop the old group attributes.
        if ($attributes['name'] !== $profile->name) {
            $this->radius->deleteGroup($profile->name);
        }

        $profile->update($attributes);
    }

    /**
     * Delete a PPP profile from both systems.
     */
    public function deleteProfile(PppProfile $profile): void
    {
        $this->assertMikrotik();
        $this->assertRadius();

        $inUse = $this->radius->countUsersInGroup($profile->name);

        if ($inUse > 0) {
            throw new RuntimeException(__('Profil masih dipakai oleh :count secret di radius.', ['count' => $inUse]));
        }

        $this->mikrotik->deleteProfile($profile->name);
        $this->radius->deleteGroup($profile->name);

        $profile->delete();
    }

    /**
     * One-time manual import: every existing Mikrotik profile becomes a
     * Radius group carrying Framed-Pool / Mikrotik-Rate-Limit attributes.
     *
     * @return int Number of profiles imported.
     */
    public function importProfilesFromMikrotik(): int
    {
        $this->assertMikrotik();
        $this->assertRadius();

        $this->radiusEnsureSchema();

        $profiles = $this->mikrotik->profiles();
        $imported = 0;

        foreach ($profiles as $row) {
            $name = (string) ($row['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $this->radius->upsertGroup($name, [
                'Framed-Pool' => $row['remote-address'] ?? null,
                'Mikrotik-Rate-Limit' => $row['rate-limit'] ?? null,
                'Framed-IP-Address' => $row['local-address'] ?? null,
            ]);

            PppProfile::query()->updateOrCreate(
                [
                    'mikrotik_server_id' => $this->mikrotik->server->id,
                    'name' => $name,
                ],
                [
                    'mikrotik_id' => $row['.id'] ?? null,
                    'local_address' => $row['local-address'] ?? null,
                    'remote_address' => $row['remote-address'] ?? null,
                    'dns_server' => $row['dns-server'] ?? null,
                    'rate_limit' => $row['rate-limit'] ?? null,
                    'comment' => $row['comment'] ?? null,
                ],
            );

            $imported++;
        }

        return $imported;
    }

    /**
     * Radius reply attributes mirroring a Mikrotik profile.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function profileGroupAttributes(array $attributes): array
    {
        return [
            'Framed-Pool' => isset($attributes['remote_address']) && $attributes['remote_address'] !== ''
                ? (string) $attributes['remote_address']
                : null,
            'Mikrotik-Rate-Limit' => isset($attributes['rate_limit']) && $attributes['rate_limit'] !== ''
                ? (string) $attributes['rate_limit']
                : null,
            'Framed-IP-Address' => isset($attributes['local_address']) && $attributes['local_address'] !== ''
                ? (string) $attributes['local_address']
                : null,
        ];
    }

    // ------------------------------------------------------------------
    // Secrets
    // ------------------------------------------------------------------

    /**
     * Create a PPP secret: enabled in Radius, disabled on Mikrotik.
     */
    public function createSecret(string $username, string $password, string $profile, ?string $comment = null): PppSecret
    {
        $this->assertMikrotik();
        $this->assertRadius();

        $this->radiusEnsureSchema();

        // Radius first: the credential must authenticate immediately...
        $this->radius->upsertUser($username, $password, $profile);

        // ...while the router copy stays disabled until an admin enables it.
        try {
            $mikrotikId = $this->mikrotik->createSecret($username, $password, $profile, true, $comment);
        } catch (RuntimeException $e) {
            throw new RuntimeException(__('Secret aktif di radius namun gagal dibuat di Mikrotik: :error', ['error' => $e->getMessage()]));
        }

        return PppSecret::create([
            'mikrotik_server_id' => $this->mikrotik->server->id,
            'mikrotik_id' => $mikrotikId,
            'name' => $username,
            'password' => $password,
            'profile' => $profile,
            'disabled_on_mikrotik' => true,
            'comment' => $comment,
        ]);
    }

    /**
     * Update a secret on both systems. Missing router rows are recreated
     * automatically (self-healing after outages).
     *
     * @param  array{password?: ?string, profile?: ?string, disabled_on_mikrotik?: bool, comment?: ?string}  $changes
     */
    public function updateSecret(PppSecret $secret, array $changes): void
    {
        $this->assertMikrotik();
        $this->assertRadius();

        // Self-heal: recreate the router row when it disappeared.
        if ($this->mikrotik->findSecret($secret->name) === null) {
            $this->mikrotik->createSecret(
                $secret->name,
                $changes['password'] ?? $secret->password,
                $changes['profile'] ?? $secret->profile,
                $changes['disabled_on_mikrotik'] ?? $secret->disabled_on_mikrotik,
                $changes['comment'] ?? $secret->comment,
            );
        } else {
            $this->mikrotik->updateSecret($secret->name, [
                'password' => $changes['password'] ?? null,
                'profile' => $changes['profile'] ?? null,
                'disabled' => $changes['disabled_on_mikrotik'] ?? null,
                'comment' => $changes['comment'] ?? null,
            ]);
        }

        $this->radius->upsertUser(
            $secret->name,
            $changes['password'] ?? null,
            $changes['profile'] ?? null,
        );

        $data = collect($changes)->except(['password'])->all();

        if (isset($changes['password']) && $changes['password'] !== null && $changes['password'] !== '') {
            $data['password'] = $changes['password'];
        }

        $secret->update($data);
    }

    /**
     * Delete a secret from both systems.
     */
    public function deleteSecret(PppSecret $secret): void
    {
        $this->assertMikrotik();
        $this->assertRadius();

        try {
            $this->mikrotik->deleteSecret($secret->name);
        } catch (RuntimeException $e) {
            throw new RuntimeException(__('Gagal menghapus dari Mikrotik: :error', ['error' => $e->getMessage()]));
        }

        $this->radius->deleteUser($secret->name);

        $secret->delete();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Ensure the FreeRADIUS tables exist (idempotent, cached per request).
     */
    private function radiusEnsureSchema(): void
    {
        static $ensured = false;

        if ($ensured || $this->radius === null) {
            return;
        }

        $this->radius->ensureSchema();

        $ensured = true;
    }

    /**
     * Guard against running without a Mikrotik server.
     */
    private function assertMikrotik(): void
    {
        if ($this->mikrotik === null) {
            throw new RuntimeException(__('Belum ada server Mikrotik. Tambahkan dulu di menu Servers.'));
        }
    }

    /**
     * Guard against running without a Radius database.
     */
    private function assertRadius(): void
    {
        if ($this->radius === null) {
            throw new RuntimeException(__('Database radius belum tersambung. Periksa menu Servers > Radius.'));
        }
    }
}
