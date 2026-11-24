<?php

declare(strict_types=1);

namespace App\Services\Scum;

use App\Support\VehicleParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScumDbQueryService
{
    /** Порядок значений в RCON-команде SetAttributes (канонический): сила, выносливость, ловкость, интеллект */
    public const array RCON_ATTRIBUTE_ORDER = ['strength', 'constitution', 'dexterity', 'intelligence'];

    /** Названия навыков в prisoner_skill → имена для команды RCON SetSkillLevel */
    public const array SKILL_RCON_MAP = [
        'ArcherySkill' => 'Archery',
        'AviationSkill' => 'Aviation',
        'AwarenessSkill' => 'Awareness',
        'BoxingSkill' => 'Brawling',
        'CamouflageSkill' => 'Camouflage',
        'CookingSkill' => 'Cooking',
        'DemolitionSkill' => 'Demolition',
        'DrivingSkill' => 'Driving',
        'EnduranceSkill' => 'Endurance',
        'EngineeringSkill' => 'Engineering',
        'FarmingSkill' => 'Farming',
        'HandgunSkill' => 'Handgun',
        'MedicalSkill' => 'Medical',
        'MeleeWeaponsSkill' => '"Melee Weapons"',
        'MotorcycleSkill' => 'Motorcycling',
        'ResistanceSkill' => 'Resistance',
        'RiflesSkill' => 'Rifles',
        'RunningSkill' => 'Running',
        'SnipingSkill' => 'Sniping',
        'StealthSkill' => 'Stealth',
        'SurvivalSkill' => 'Survival',
        'TacticsSkill' => 'Tactics',
        'ThieverySkill' => 'Thievery',
    ];

    public function __construct(
        private readonly ScumDbService $scumDb,
        private readonly RconService $rcon,
        private readonly FtpLogWatcherService $ftp,
    ) {}

    /**
     * Обогащает список техники данными о владельце из RCON ListSpawnedVehicles и профилей игроков.
     *
     * @param  array<int, array<string, mixed>>  $vehicles
     * @return array<int, array<string, mixed>>
     */
    public function enrichVehiclesWithOwner(array $vehicles): array
    {
        if (empty($vehicles)) {
            return $vehicles;
        }

        try {
            // 1. Карта профилей игроков из БД (profileId / name -> steamId, name)
            $profileMap = [];
            $nameMap = [];
            try {
                $players = $this->scumDb->getPlayers(5000);
                foreach ($players as $p) {
                    $pId = ! empty($p['profileId']) ? (int) $p['profileId'] : null;
                    $sid = ! empty($p['steamId']) ? (string) $p['steamId'] : '';
                    $name = ! empty($p['name']) ? (string) $p['name'] : '';
                    if ($pId !== null) {
                        $profileMap[$pId] = ['steamId' => $sid, 'name' => $name];
                    }
                    if ($name !== '') {
                        $nameMap[mb_strtolower(trim($name))] = ['steamId' => $sid, 'name' => $name];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('enrichVehiclesWithOwner: error fetching players: '.$e->getMessage());
            }

            // 2. Список транспорта из RCON (с безопасным кешированием на 20 сек)
            $rconVehicles = [];
            try {
                $rconVehicles = Cache::remember('scum_rcon_spawned_vehicles', 20, function () {
                    try {
                        $r = $this->rcon->sendCommand('ListSpawnedVehicles');
                        if (empty($r['success']) || empty($r['response'])) {
                            return [];
                        }

                        $vehicles = VehicleParser::parseList($r['response']);

                        return VehicleParser::toMap($vehicles);
                    } catch (\Throwable $e) {
                        Log::warning('enrichVehiclesWithOwner: RCON error: '.$e->getMessage());

                        return [];
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('enrichVehiclesWithOwner: Cache remember error: '.$e->getMessage());
            }

            // 3. Объединение данных
            foreach ($vehicles as &$v) {
                $eId = (int) ($v['entityId'] ?? 0);
                $v['ownerName'] = null;
                $v['ownerSteamId'] = null;
                $v['ownerDbId'] = null;

                if (! empty($rconVehicles[$eId])) {
                    $rData = $rconVehicles[$eId];
                    $v['ownerDbId'] = $rData['ownerDbId'];
                    $v['ownerName'] = $rData['ownerName'];

                    if (! empty($rData['customName']) && empty($v['alias'])) {
                        $v['alias'] = $rData['customName'];
                    }

                    if ($rData['ownerDbId'] !== null && isset($profileMap[$rData['ownerDbId']])) {
                        $v['ownerSteamId'] = $profileMap[$rData['ownerDbId']]['steamId'];
                        if (empty($v['ownerName'])) {
                            $v['ownerName'] = $profileMap[$rData['ownerDbId']]['name'];
                        }
                    } elseif (! empty($rData['ownerName'])) {
                        $lower = mb_strtolower($rData['ownerName']);
                        if (isset($nameMap[$lower])) {
                            $v['ownerSteamId'] = $nameMap[$lower]['steamId'];
                        }
                    }
                }
            }
            unset($v);

            return $vehicles;
        } catch (\Throwable $e) {
            Log::error('enrichVehiclesWithOwner: catastrophic failure fallback: '.$e->getMessage());

            return $vehicles;
        }
    }

    /**
     * Формирует данные таблицы БД, колонки и безопасные сериализованные строки для веб-редактора.
     *
     * @return array{
     *     tables: array<int, string>,
     *     columns: array<int, array<string, mixed>>,
     *     rows: array<int, array<string, mixed>>,
     *     editorCols: array<int, array<string, mixed>>,
     *     editorRows: array<int, array<string, mixed>>,
     *     hasPk: bool,
     *     hasRowId: bool,
     *     total: int,
     *     page: int,
     *     maxPage: int,
     *     perPage: int
     * }
     */
    public function getTableData(string $table, int $page = 1, int $perPage = 100, ?string $q = null): array
    {
        $tables = $this->scumDb->getTables();
        if (! in_array($table, $tables, true)) {
            abort(404, 'Таблица не найдена');
        }

        $page = max(1, $page);
        $total = $this->scumDb->countTableRows($table, $q ?: null);
        $maxPage = max(1, (int) ceil($total / $perPage));
        if ($page > $maxPage) {
            $page = $maxPage;
        }

        $rows = $this->scumDb->getTableRows($table, $perPage, ($page - 1) * $perPage, $q ?: null);
        $cols = $this->scumDb->getTableColumns($table);
        $pk = $this->scumDb->getPrimaryKey($table);
        $hasRowId = $this->scumDb->hasRowId($table);

        $colTypes = [];
        foreach ($cols as $c) {
            $colTypes[$c['name']] = (string) $c['type'];
        }

        // Редактор: BLOB-колонки и колонки с длинными значениями — только просмотр.
        $longCols = [];
        foreach ($rows as $row) {
            foreach ($row as $name => $val) {
                if (is_string($val) && mb_strlen($val) > 200) {
                    $longCols[$name] = true;
                }
            }
        }

        $editorCols = [];
        foreach ($cols as $c) {
            $name = $c['name'];
            $blob = str_contains(strtoupper($colTypes[$name] ?? ''), 'BLOB');
            $editorCols[] = [
                'name' => $name,
                'type' => $colTypes[$name] ?? '',
                'pk' => (int) $c['pk'],
                'readonly' => $blob || ! empty($longCols[$name]),
            ];
        }

        $editorRows = [];
        foreach ($rows as $row) {
            $d = [];
            foreach ($row as $name => $val) {
                if ($val === null) {
                    $d[$name] = null;

                    continue;
                }
                if (is_resource($val)) {
                    $val = stream_get_contents($val);
                }
                if (is_float($val)) {
                    if (is_nan($val)) {
                        $d[$name] = 'NaN';

                        continue;
                    }
                    if (is_infinite($val)) {
                        $d[$name] = $val > 0 ? 'INF' : '-INF';

                        continue;
                    }
                    $d[$name] = $val;

                    continue;
                }
                $t = strtoupper($colTypes[$name] ?? '');
                if (str_contains($t, 'BLOB')) {
                    $d[$name] = 'hex:'.strtoupper(bin2hex(substr((string) $val, 0, 32)));
                } elseif (is_string($val)) {
                    if (! mb_check_encoding($val, 'UTF-8')) {
                        $val = mb_convert_encoding($val, 'UTF-8', 'UTF-8, Windows-1251, ISO-8859-1');
                    }
                    if (mb_strlen($val) > 80) {
                        $d[$name] = mb_substr($val, 0, 80).'…';
                    } else {
                        $d[$name] = $val;
                    }
                } else {
                    $d[$name] = $val;
                }
            }
            $editorRows[] = $d;
        }

        return [
            'tables' => $tables,
            'columns' => $cols,
            'rows' => $rows,
            'editorCols' => $editorCols,
            'editorRows' => $editorRows,
            'hasPk' => ! empty($pk),
            'hasRowId' => $hasRowId,
            'total' => $total,
            'page' => $page,
            'maxPage' => $maxPage,
            'perPage' => $perPage,
        ];
    }

    /**
     * Обновление атрибутов персонажа: RCON -> Локальная БД -> FTP-fallback.
     *
     * @param  array<string, float>  $attrs
     * @return array{ok: bool, message: string}
     */
    public function updateAttributes(string $steamId, array $attrs): array
    {
        $keys = self::RCON_ATTRIBUTE_ORDER;
        $current = $this->scumDb->getAttributes($steamId) ?? [];
        $merged = [];
        foreach ($keys as $key) {
            $v = $attrs[$key] ?? (float) ($current[$key] ?? 1.0);
            $merged[$key] = max(0.5, min(ScumDbService::ATTRIBUTE_MAX[$key], round((float) $v, 2)));
        }

        $cmd = 'SetAttributes '.implode(' ', array_map(fn ($k) => (string) $merged[$k], self::RCON_ATTRIBUTE_ORDER)).' '.$steamId;
        $rcon = $this->rcon->sendCommand($cmd);

        if (! empty($rcon['success'])) {
            $this->scumDb->updateAttributes($steamId, $merged);

            return ['ok' => true, 'message' => 'Атрибуты обновлены через RCON: <span class="mono">'.e($cmd).'</span>'];
        }

        // Fallback: прямая запись в SCUM.db + загрузка по FTP
        $result = $this->scumDb->updateAttributes($steamId, $merged);
        if (! empty($result['ok'])) {
            $this->ftp->markPendingUpload();
            $upload = $this->ftp->uploadScumDb();
            $msg = 'Атрибуты изменены в SCUM.db (RCON недоступен: '.($rcon['error'] ?? 'ошибка RCON').')';
            if (empty($upload['ok'])) {
                $msg .= ' ⚠️ Не удалось загрузить на сервер: '.($upload['error'] ?? 'FTP ошибка');
            } else {
                $msg .= ' и загружены на сервер';
            }

            return ['ok' => true, 'message' => $msg];
        }

        return ['ok' => false, 'message' => $result['error'] ?? 'Ошибка'];
    }

    /**
     * Обновление конкретного навыка: RCON -> Локальная БД -> FTP-fallback.
     *
     * @return array{ok: bool, message: string}
     */
    public function updateSkill(string $steamId, string $name, int $level): array
    {
        $level = max(0, min(ScumDbService::MAX_SKILL_LEVEL, $level));
        $cmdName = self::SKILL_RCON_MAP[$name] ?? null;

        if ($cmdName !== null) {
            $cmd = "SetSkillLevel {$cmdName} {$level} {$steamId}";
            $rcon = $this->rcon->sendCommand($cmd);
            if (! empty($rcon['success'])) {
                $this->scumDb->updateSkill($steamId, $name, $level);

                return ['ok' => true, 'message' => "Навык «{$name}» установлен на {$level} ур. через RCON: <span class=\"mono\">".e($cmd).'</span>'];
            }
            $rconError = $rcon['error'] ?? ($rcon['response'] ?? 'ошибка RCON');
        } else {
            $rconError = 'нет RCON-имени для «'.$name.'»';
        }

        // Fallback: прямая запись в SCUM.db + загрузка по FTP
        $result = $this->scumDb->updateSkill($steamId, $name, $level);
        if (! empty($result['ok'])) {
            $this->ftp->markPendingUpload();
            $upload = $this->ftp->uploadScumDb();
            $msg = "Навык «{$name}» изменён в SCUM.db (RCON недоступен: {$rconError})";
            if (empty($upload['ok'])) {
                $msg .= ' ⚠️ Не удалось загрузить на сервер: '.($upload['error'] ?? 'FTP ошибка');
            } else {
                $msg .= ' и загружен на сервер';
            }

            return ['ok' => true, 'message' => $msg];
        }

        return ['ok' => false, 'message' => $result['error'] ?? 'Ошибка'];
    }

    /**
     * Обновление всех навыков до заданного уровня.
     *
     * @return array{ok: bool, message: string}
     */
    public function updateAllSkills(string $steamId, int $level): array
    {
        $level = max(0, min(ScumDbService::MAX_SKILL_LEVEL, $level));
        $errors = 0;
        $okRcon = 0;

        foreach (self::SKILL_RCON_MAP as $dbName => $rconName) {
            $r = $this->rcon->sendCommand("SetSkillLevel {$rconName} {$level} {$steamId}");
            if (! empty($r['success'])) {
                $okRcon++;
            } else {
                $errors++;
            }
            $this->scumDb->updateSkill($steamId, $dbName, $level);
        }

        if ($okRcon > 0) {
            $msg = "Все навыки ({$okRcon}/".count(self::SKILL_RCON_MAP).") прокачаны до MAX ({$level} ур.) через RCON";
            if ($errors > 0) {
                $msg .= " ({$errors} навыков не ответили по RCON, но обновлены в SCUM.db)";
            }

            return ['ok' => true, 'message' => $msg];
        }

        // Fallback: прямая запись в SCUM.db + загрузка по FTP
        $this->ftp->markPendingUpload();
        $upload = $this->ftp->uploadScumDb();
        $msg = "Все навыки установлены на {$level} ур. в SCUM.db (RCON недоступен)";
        if (empty($upload['ok'])) {
            $msg .= ' ⚠️ Не удалось загрузить на сервер: '.($upload['error'] ?? 'FTP ошибка');
        } else {
            $msg .= ' и загружены на сервер';
        }

        return ['ok' => true, 'message' => $msg];
    }
}
