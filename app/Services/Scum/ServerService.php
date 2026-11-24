<?php

namespace App\Services\Scum;

use App\Support\SecureValues;
use Illuminate\Support\Facades\DB;

class ServerService
{
    public function all(): array
    {
        return DB::table('servers')
            ->orderByDesc('enabled')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->decryptRow((array) $row))
            ->toArray();
    }

    public function get(int $id): ?array
    {
        $row = DB::table('servers')->where('id', $id)->first();

        return $row ? $this->decryptRow((array) $row) : null;
    }

    public function first(): ?array
    {
        $row = DB::table('servers')->orderBy('id')->first();

        return $row ? $this->decryptRow((array) $row) : null;
    }

    /**
     * Возвращает первый включённый сервер (fallback — самый новый).
     */
    public function active(): ?array
    {
        foreach ($this->all() as $server) {
            if ((int) $server['enabled']) {
                return $server;
            }
        }

        return $this->first();
    }

    /**
     * Гарантирует, что в app_settings есть hostFtp/rcon активного сервера.
     * Демоны вызывают на старте: конфиг не должен зависеть от того, заходил ли
     * пользователь в панель (раньше это делал только ServersController::activateServer).
     * Возвращает актуальный AppConfig.
     */
    public function ensureActiveConfigInSettings(): array
    {
        $settings = app(SettingsService::class);
        $cfg = $settings->getAppConfig();
        $ftp = $cfg['hostFtp'] ?? [];
        $rcon = $cfg['rcon'] ?? [];

        // Если конфиг уже заполнен — ничего не делаем.
        if (! empty($ftp['host']) || ! empty($rcon['host'])) {
            return $cfg;
        }

        $server = $this->active();
        if ($server) {
            $settings->saveAppConfig([
                'hostFtp' => $this->toHostFtp($server),
                'rcon' => $this->toRcon($server),
            ]);
            $cfg = $settings->getAppConfig();
        }

        return $cfg;
    }

    public function count(): int
    {
        return DB::table('servers')->count();
    }

    public function create(array $data): int
    {
        return DB::table('servers')->insertGetId([
            'name' => $data['name'] ?? '',
            'ftp_protocol' => $data['ftp_protocol'] ?? 'ftp',
            'ftp_host' => $data['ftp_host'] ?? '',
            'ftp_port' => (int) ($data['ftp_port'] ?? 21),
            'ftp_username' => $data['ftp_username'] ?? '',
            'ftp_password' => SecureValues::encrypt($data['ftp_password'] ?? ''),
            'ftp_log_path' => $data['ftp_log_path'] ?? 'SCUM/Saved/SaveFiles/Logs',
            'ftp_scumdb_path' => $data['ftp_scumdb_path'] ?? 'SCUM/Saved/SaveFiles/SCUM.db',
            'ftp_config_path' => $data['ftp_config_path'] ?? 'SCUM/Saved/Config/WindowsServer',
            'rcon_host' => $data['rcon_host'] ?? '',
            'rcon_port' => (int) ($data['rcon_port'] ?? 28015),
            'rcon_password' => SecureValues::encrypt($data['rcon_password'] ?? ''),
            'enabled' => (int) (empty($data['enabled']) ? 0 : 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $updates = [
            'updated_at' => now(),
        ];
        foreach ([
            'name', 'ftp_protocol', 'ftp_host', 'ftp_username',
            'ftp_log_path', 'ftp_scumdb_path', 'ftp_config_path', 'rcon_host',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field] ?? '';
            }
        }
        foreach (['ftp_password', 'rcon_password'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $updates[$field] = SecureValues::encrypt($data[$field]);
            }
        }
        foreach (['ftp_port', 'rcon_port'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = (int) ($data[$field] ?? 0);
            }
        }
        if (array_key_exists('enabled', $data)) {
            $updates['enabled'] = (int) (empty($data['enabled']) ? 0 : 1);
        }
        DB::table('servers')->where('id', $id)->update($updates);
    }

    public function delete(int $id): void
    {
        DB::table('servers')->where('id', $id)->delete();
    }

    private function decryptRow(array $row): array
    {
        foreach (['ftp_password', 'rcon_password'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = SecureValues::decrypt($row[$field]);
            }
        }

        return $row;
    }

    public function toHostFtp(array $server): array
    {
        return [
            'enabled' => (bool) ($server['enabled'] ?? true),
            'host' => $server['ftp_host'] ?? '',
            'port' => (int) ($server['ftp_port'] ?? 21),
            'username' => $server['ftp_username'] ?? '',
            'password' => $server['ftp_password'] ?? '',
            'protocol' => $server['ftp_protocol'] ?? 'ftp',
            'logPath' => $server['ftp_log_path'] ?? 'SCUM/Saved/SaveFiles/Logs',
            'scumDbPath' => $server['ftp_scumdb_path'] ?? 'SCUM/Saved/SaveFiles/SCUM.db',
            'configPath' => $server['ftp_config_path'] ?? 'SCUM/Saved/Config/WindowsServer',
        ];
    }

    public function toRcon(array $server): array
    {
        return [
            'enabled' => (bool) ($server['enabled'] ?? true),
            'host' => $server['rcon_host'] ?? '',
            'port' => (int) ($server['rcon_port'] ?? 28015),
            'password' => $server['rcon_password'] ?? '',
        ];
    }
}
