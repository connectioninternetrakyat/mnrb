<?php

namespace App\Services\Ppp;

use App\Enums\RadiusDriver;
use App\Models\RadiusServer;
use PDO;
use RuntimeException;
use Throwable;

class RadiusPppService
{
    /**
     * Standard FreeRADIUS table names.
     */
    private const TABLES = [
        'check' => 'radcheck',
        'reply' => 'radreply',
        'usergroup' => 'radusergroup',
        'groupreply' => 'radgroupreply',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Build a service bound to the given RADIUS database.
     */
    public static function forServer(RadiusServer $server): self
    {
        return new self(static::connect($server));
    }

    /**
     * Open a PDO connection for the given server (MySQL / PostgreSQL).
     */
    public static function connect(RadiusServer $server): PDO
    {
        if ($server->db_host === null || $server->db_host === '' || $server->db_database === null || $server->db_database === '') {
            throw new RuntimeException(__('Kredensial database radius belum lengkap.'));
        }

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

        try {
            return new PDO($dsn, (string) $server->db_username, (string) $server->db_password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => max(2, (int) $server->timeout),
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException(__('Gagal terhubung ke database radius: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Create the standard FreeRADIUS tables when missing. The SQL dialect
     * follows the actual PDO driver so tests can run on SQLite too.
     */
    public function ensureSchema(): void
    {
        $pdoDriver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $idColumn = match ($pdoDriver) {
            'mysql' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            default => throw new RuntimeException(__('Driver database radius tidak didukung: :driver', ['driver' => $pdoDriver])),
        };

        $statements = [
            sprintf('CREATE TABLE IF NOT EXISTS %s (id %s, username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op CHAR(2) NOT NULL DEFAULT ":=", value VARCHAR(253) NOT NULL)', self::TABLES['check'], $idColumn),
            sprintf('CREATE TABLE IF NOT EXISTS %s (id %s, username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op CHAR(2) NOT NULL DEFAULT ":=", value VARCHAR(253) NOT NULL)', self::TABLES['reply'], $idColumn),
            sprintf('CREATE TABLE IF NOT EXISTS %s (id %s, groupname VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op CHAR(2) NOT NULL DEFAULT ":=", value VARCHAR(253) NOT NULL)', self::TABLES['groupreply'], $idColumn),
            sprintf('CREATE TABLE IF NOT EXISTS %s (id %s, username VARCHAR(64) NOT NULL, groupname VARCHAR(64) NOT NULL, priority INTEGER NOT NULL DEFAULT 1)', self::TABLES['usergroup'], $idColumn),
        ];

        foreach ($statements as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (Throwable $e) {
                throw new RuntimeException(__('Gagal menyiapkan skema radius: :error', ['error' => $e->getMessage()]));
            }
        }
    }

    // ------------------------------------------------------------------
    // Groups (= PPP profiles)
    // ------------------------------------------------------------------

    /**
     * Insert or refresh the reply attributes of a profile group.
     *
     * @param  array<string, string|null>  $attributes  attribute => value (null removes)
     */
    public function upsertGroup(string $group, array $attributes): void
    {
        $this->assertConnected();

        foreach ($attributes as $attribute => $value) {
            $this->deleteGroupAttribute($group, $attribute);

            if ($value !== null && $value !== '') {
                $this->insertRow(self::TABLES['groupreply'], [
                    'groupname' => $group,
                    'attribute' => $attribute,
                    'op' => '=',
                    'value' => (string) $value,
                ]);
            }
        }
    }

    /**
     * Delete every reply attribute of a profile group.
     */
    public function deleteGroup(string $group): void
    {
        $this->assertConnected();

        $statement = $this->pdo->prepare(
            sprintf('DELETE FROM %s WHERE groupname = ?', self::TABLES['groupreply']),
        );
        $statement->execute([$group]);
    }

    /**
     * Count secrets assigned to a profile group in radius.
     */
    public function countUsersInGroup(string $group): int
    {
        $this->assertConnected();

        $statement = $this->pdo->prepare(
            sprintf('SELECT COUNT(*) FROM %s WHERE groupname = ?', self::TABLES['usergroup']),
        );
        $statement->execute([$group]);

        return (int) $statement->fetchColumn();
    }

    /**
     * List profile groupnames known to radius with their attributes.
     *
     * @return array<string, array<string, string>>
     */
    public function groups(): array
    {
        $this->assertConnected();

        $rows = $this->pdo
            ->query(sprintf('SELECT groupname, attribute, value FROM %s ORDER BY id', self::TABLES['groupreply']))
            ->fetchAll(PDO::FETCH_ASSOC);

        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['groupname']][$row['attribute']] = $row['value'];
        }

        return $groups;
    }

    // ------------------------------------------------------------------
    // Users (= PPP secrets)
    // ------------------------------------------------------------------

    /**
     * Enable a user: password check + profile membership.
     */
    public function upsertUser(string $username, ?string $password, ?string $profile): void
    {
        $this->assertConnected();

        if ($password !== null && $password !== '') {
            $this->deleteUserAttribute($username, 'Cleartext-Password');
            $this->deleteUserAttribute($username, 'Password');

            $this->insertRow(self::TABLES['check'], [
                'username' => $username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $password,
            ]);
        }

        if ($profile !== null && $profile !== '') {
            $statement = $this->pdo->prepare(
                sprintf('DELETE FROM %s WHERE username = ?', self::TABLES['usergroup']),
            );
            $statement->execute([$username]);

            $this->insertRow(self::TABLES['usergroup'], [
                'username' => $username,
                'groupname' => $profile,
                'priority' => 1,
            ]);
        }
    }

    /**
     * Fully remove a user from radius tables.
     */
    public function deleteUser(string $username): void
    {
        $this->assertConnected();

        foreach ([self::TABLES['check'], self::TABLES['reply'], self::TABLES['usergroup']] as $table) {
            $column = $table === self::TABLES['usergroup'] ? 'username' : 'username';

            $statement = $this->pdo->prepare(
                sprintf('DELETE FROM %s WHERE username = ?', $table),
            );
            $statement->execute([$username]);
        }
    }

    /**
     * List usernames known to radius with their cleartext presence flag.
     *
     * @return array<int, array{username: string, profile: string|null}>
     */
    public function users(): array
    {
        $this->assertConnected();

        $users = [];

        $checks = $this->pdo
            ->query(sprintf("SELECT username, value FROM %s WHERE attribute IN ('Cleartext-Password', 'Password')", self::TABLES['check']))
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($checks as $row) {
            $users[$row['username']] = ['username' => $row['username'], 'profile' => null];
        }

        $memberships = $this->pdo
            ->query(sprintf('SELECT username, groupname FROM %s', self::TABLES['usergroup']))
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($memberships as $row) {
            if (! isset($users[$row['username']])) {
                $users[$row['username']] = ['username' => $row['username'], 'profile' => null];
            }

            $users[$row['username']]['profile'] = $row['groupname'];
        }

        return array_values($users);
    }

    /**
     * Determine whether the radius connection is usable.
     */
    public function ping(): bool
    {
        try {
            $this->pdo->query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Latest open accounting session (radacct) of a username, if any.
     * Returns null when offline or when the radacct table is absent.
     *
     * @return array{online: bool, framed_ip: string|null, mac: string|null, nas_ip: string|null, uptime_seconds: int, started_at: string|null}|null
     */
    public function activeSession(string $username): ?array
    {
        $this->assertConnected();

        try {
            $statement = $this->pdo->prepare(
                sprintf(
                    'SELECT framedipaddress, callingstationid, nasipaddress, acctsessiontime, acctstarttime FROM %s WHERE username = ? AND acctstoptime IS NULL ORDER BY id DESC',
                    'radacct',
                ),
            );
            $statement->execute([$username]);

            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return ['online' => false] + array_fill_keys(
                    ['framed_ip', 'mac', 'nas_ip', 'started_at'],
                    null,
                ) + ['uptime_seconds' => 0];
            }

            return [
                'online' => true,
                'framed_ip' => $row['framedipaddress'] !== '' ? $row['framedipaddress'] : null,
                'mac' => $row['callingstationid'] !== '' ? $row['callingstationid'] : null,
                'nas_ip' => $row['nasipaddress'] !== '' ? $row['nasipaddress'] : null,
                'uptime_seconds' => (int) $row['acctsessiontime'],
                'started_at' => $row['acctstarttime'] !== '' ? $row['acctstarttime'] : null,
            ];
        } catch (Throwable) {
            // Missing radacct table or dialect differences: treat as offline.
            return null;
        }
    }

    /**
     * Number of currently open PPPoE sessions. Null when the radacct table
     * is unavailable (e.g. sqlite test connections without FreeRADIUS).
     */
    public function onlineSessionCount(): ?int
    {
        $this->assertConnected();

        try {
            return (int) $this->pdo
                ->query('SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL')
                ->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Throw when no database connection is bound.
     */
    private function assertConnected(): void
    {
        if ($this->pdo === null) {
            throw new RuntimeException(__('Database radius belum dikonfigurasi.'));
        }
    }

    /**
     * Remove a single attribute row of a user.
     */
    private function deleteUserAttribute(string $username, string $attribute): void
    {
        $statement = $this->pdo->prepare(
            sprintf('DELETE FROM %s WHERE username = ? AND attribute = ?', self::TABLES['check']),
        );
        $statement->execute([$username, $attribute]);
    }

    /**
     * Remove a single attribute row of a group.
     */
    private function deleteGroupAttribute(string $group, string $attribute): void
    {
        $statement = $this->pdo->prepare(
            sprintf('DELETE FROM %s WHERE groupname = ? AND attribute = ?', self::TABLES['groupreply']),
        );
        $statement->execute([$group, $attribute]);
    }

    /**
     * Insert one row into a radius table.
     *
     * @param  array<string, int|string>  $data
     */
    private function insertRow(string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $statement = $this->pdo->prepare(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $columns),
                $placeholders,
            ),
        );

        $statement->execute(array_values($data));
    }
}
