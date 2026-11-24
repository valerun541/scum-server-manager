<?php

namespace App\Services\Scum;

use Illuminate\Support\Facades\Log;

/**
 * Чтение SCUM.db через PDO SQLite — порт scumDatabase.ts (sql.js → PDO)
 */
class ScumDbService
{
    private string $dbPath = '';

    private ?\PDO $pdo = null;

    /** Кэш схемы (таблицы/колонки) в рамках одного запроса — PRAGMA не дёргаем повторно */
    private array $schemaCache = [];

    /** Детектированные имена колонок user_profile (last_login/last_logout) для rich-запросов */
    private ?array $profileCols = null;

    public function __construct(?string $dbPath = null)
    {
        if ($dbPath !== null && $dbPath !== '') {
            $this->setDbPath($dbPath);
        } else {
            $default = storage_path('app/scum/SCUM.db');
            if (file_exists($default)) {
                $this->setDbPath($default);
            }
        }
    }

    public function setDbPath(string $dbPath): void
    {
        if ($dbPath !== $this->dbPath) {
            $this->close();
            $this->dbPath = $dbPath;
        }
    }

    public function isAvailable(): bool
    {
        return $this->dbPath !== '' && file_exists($this->dbPath);
    }

    public function open(): void
    {
        if ($this->pdo) {
            return;
        }
        if (! $this->dbPath || ! file_exists($this->dbPath)) {
            throw new \RuntimeException("SCUM.db not found at {$this->dbPath}");
        }
        $this->pdo = new \PDO('sqlite:'.$this->dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // Ждём освобождения файла, пока демон/другой процесс синкает или пишет (busy timeout, сек).
        $this->pdo->setAttribute(\PDO::ATTR_TIMEOUT, (int) config('panel.db.busy_timeout', 5));
    }

    /**
     * Слить WAL-журнал в основной файл. Обязательно перед выгрузкой SCUM.db на сервер:
     * локальные правки живут в -wal и без checkpoint'а не попадут в загружаемый файл.
     */
    public function checkpointWal(): void
    {
        try {
            if (! $this->pdo) {
                return;
            }
            $this->pdo->query('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable $e) {
            Log::channel('scum')->warning('[SCUMdb] wal checkpoint failed: '.$e->getMessage());
        }
    }

    public function close(): void
    {
        $this->pdo = null;
        $this->schemaCache = [];
        $this->profileCols = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function ensureOpen(): void
    {
        if (! $this->pdo) {
            $this->open();
        }
    }

    private function query(string $sql, array $params = []): array
    {
        // Одна повторная попытка с переоткрытием PDO: файл мог быть заменён синком
        // (старый inode) или кратковременно заблокирован — без ретрая страницы
        // отрисовывались бы пустыми («данные пропали»).
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->ensureOpen();
                $stmt = $this->pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue(is_int($k) ? $k + 1 : $k, $v);
                }
                $stmt->execute();

                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $this->close();
                if ($attempt === 0) {
                    usleep(100000);

                    continue;
                }
                Log::channel('scum')->error('[SCUMdb] query error: '.$e->getMessage());
            }
        }

        return [];
    }

    private function run(string $sql, array $params = []): bool
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->ensureOpen();
                $stmt = $this->pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue(is_int($k) ? $k + 1 : $k, $v);
                }

                return $stmt->execute();
            } catch (\Throwable $e) {
                $this->close();
                if ($attempt === 0) {
                    usleep(100000);

                    continue;
                }
                Log::channel('scum')->error('[SCUMdb] run error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function getTables(): array
    {
        if (isset($this->schemaCache['tables'])) {
            return $this->schemaCache['tables'];
        }

        return $this->schemaCache['tables'] = array_column($this->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"), 'name');
    }

    /**
     * Колонки таблицы (PRAGMA table_info). Таблица проверяется по белому списку из sqlite_master.
     * Результат кэшируется на время запроса.
     */
    public function getTableColumns(string $table): array
    {
        if (! in_array($table, $this->getTables(), true)) {
            return [];
        }
        if (isset($this->schemaCache['cols'][$table])) {
            return $this->schemaCache['cols'][$table];
        }

        return $this->schemaCache['cols'][$table] = $this->query("PRAGMA table_info(\"{$table}\")");
    }

    /**
     * Количество строк таблицы (с учётом поиска по всем колонкам).
     */
    public function countTableRows(string $table, ?string $search = null): int
    {
        $cols = $this->getTableColumns($table);
        if (empty($cols)) {
            return 0;
        }
        $where = '';
        $params = [];
        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $conds = [];
            foreach (array_column($cols, 'name') as $c) {
                $conds[] = '"'.$c.'" LIKE ?';
                $params[] = $like;
            }
            $where = ' WHERE '.implode(' OR ', $conds);
        }
        $rows = $this->query("SELECT COUNT(*) AS c FROM \"{$table}\"{$where}", $params);

        return (int) ($rows[0]['c'] ?? 0);
    }

    /**
     * Строки таблицы с пагинацией и опциональным поиском по всем колонкам.
     * Для таблиц с rowid добавляем скрытую колонку __rowid__ (идентификация строк без PK).
     */
    public function getTableRows(string $table, int $limit, int $offset, ?string $search = null): array
    {
        $cols = $this->getTableColumns($table);
        if (empty($cols)) {
            return [];
        }
        $where = '';
        $params = [];
        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $conds = [];
            foreach (array_column($cols, 'name') as $c) {
                $conds[] = '"'.$c.'" LIKE ?';
                $params[] = $like;
            }
            $where = ' WHERE '.implode(' OR ', $conds);
        }
        $params[] = (int) $limit;
        $params[] = (int) $offset;
        $select = $this->hasRowId($table) ? '*, rowid AS __rowid__' : '*';

        return $this->query("SELECT {$select} FROM \"{$table}\"{$where} LIMIT ? OFFSET ?", $params);
    }

    /**
     * Есть ли у таблицы неявный rowid (SQLite). WITHOUT ROWID-таблицы — false.
     */
    public function hasRowId(string $table): bool
    {
        return ! empty($this->query("SELECT rowid FROM \"{$table}\" LIMIT 1"));
    }

    public function getPlayers(int $limit = 500): array
    {
        $cols = array_column($this->query('PRAGMA table_info(user_profile)'), 'name');
        $steamCol = in_array('user_id', $cols) ? 'user_id' : (in_array('steam_id', $cols) ? 'steam_id' : 'user_id');
        $nameCol = in_array('name', $cols) ? 'name' : 'Name';
        $sql = "SELECT id AS profileId, {$steamCol} AS steamId, {$nameCol} AS name,
                prisoner_id AS prisonerId, COALESCE(money_balance, 0) AS walletBalance,
                COALESCE(fame_points, 0) AS famePoints, COALESCE(last_login_time, 0) AS lastLogin,
                COALESCE(last_logout_time, 0) AS lastLogout
                FROM user_profile ORDER BY COALESCE(last_login_time, 0) DESC LIMIT ?";

        return $this->query($sql, [(int) $limit]);
    }

    public function getPlayerBySteamId(string $steamId): ?array
    {
        $rows = $this->query('SELECT id AS profileId, user_id AS steamId, name, prisoner_id AS prisonerId,
            money_balance AS walletBalance, fame_points AS famePoints, last_login_time AS lastLogin,
            last_logout_time AS lastLogout FROM user_profile WHERE user_id = ? LIMIT 1', [$steamId]);

        return $rows[0] ?? null;
    }

    public function getPlayerByName(string $name): ?array
    {
        $rows = $this->query('SELECT id AS profileId, user_id AS steamId, name, prisoner_id AS prisonerId,
            money_balance AS walletBalance, fame_points AS famePoints, last_login_time AS lastLogin,
            last_logout_time AS lastLogout FROM user_profile WHERE lower(name) = lower(?) LIMIT 1', [$name]);

        return $rows[0] ?? null;
    }

    public function getWallet(string $steamId): ?array
    {
        $sql = 'SELECT up.id AS profileId, up.user_id AS steamId, up.name AS name,
                COALESCE(up.money_balance, 0) AS walletBalance, COALESCE(up.fame_points, 0) AS famePoints,
                up.last_login_time AS lastLogin,
                COALESCE((SELECT MAX(c.account_balance) FROM bank_account_registry r
                    JOIN bank_account_registry_currencies c ON c.bank_account_id = r.id
                    WHERE (r.user_profile_id = up.id OR r.account_owner_user_profile_id = up.id) AND c.currency_type = 1),
                    COALESCE(up.money_balance, 0)) AS normalBalance,
                COALESCE((SELECT MAX(c.account_balance) FROM bank_account_registry r
                    JOIN bank_account_registry_currencies c ON c.bank_account_id = r.id
                    WHERE (r.user_profile_id = up.id OR r.account_owner_user_profile_id = up.id) AND c.currency_type = 2), 0) AS goldBalance
                FROM user_profile up WHERE up.user_id = ? LIMIT 1';
        $result = $this->query($sql, [$steamId]);

        return $result[0] ?? null;
    }

    public const ATTRIBUTE_MAX = [
        'strength' => 8,
        'constitution' => 5,
        'dexterity' => 5,
        'intelligence' => 5,
    ];

    public const MAX_ATTRIBUTE = 8;

    public const MAX_SKILL_LEVEL = 4;

    public function getAttributes(string $steamId): ?array
    {
        $rows = $this->query('SELECT p.id AS prisonerId, p.age AS age, p.gender AS gender,
            up.user_id AS steamId, up.name AS playerName,
            hex(p.body_simulation) AS bodyHex 
            FROM user_profile up 
            JOIN prisoner p ON (p.id = up.prisoner_id OR p.user_profile_id = up.id)
            WHERE up.user_id = ? 
            ORDER BY up.id DESC LIMIT 1', [$steamId]);
        if (empty($rows[0])) {
            return null;
        }

        return $this->parseAttributes($rows[0]);
    }

    /**
     * Атрибуты хранятся в body_simulation как двоичная сериализация SCUM
     * (UE4-подобный PropertyList): <int32 len><name><...><int32 size><DoubleProperty value>.
     * Значение каждого атрибута — DoubleProperty рядом с именем поля.
     */
    private function parseAttributes(array $row): array
    {
        $hex = $row['bodyHex'] ?? '';
        $buf = hex2bin($hex);
        if ($buf === false || strlen($buf) < 50) {
            return ['error' => 'body_simulation too short', 'hex' => $hex];
        }
        $attrs = [
            'prisonerId' => $row['prisonerId'],
            'steamId' => $row['steamId'],
            'playerName' => $row['playerName'],
            'gender' => (int) ($row['gender'] ?? 0),
            'strength' => $this->readDoubleField($buf, 'BaseStrength', 0.5, 12) ?? 0,
            'constitution' => $this->readDoubleField($buf, 'BaseConstitution', 0.5, 12) ?? 0,
            'dexterity' => $this->readDoubleField($buf, 'BaseDexterity', 0.5, 12) ?? 0,
            'intelligence' => $this->readDoubleField($buf, 'BaseIntelligence', 0.5, 12) ?? 0,
            'age' => (int) ($this->readFloatField($buf, 'InitialAge', 1, 120) ?? ($row['age'] ?? 0)),
        ];

        return $attrs;
    }

    /** Найти 8-байтовое double значение поля по имени свойства (UE Property). */
    private function readDoubleField(string $buf, string $name, float $min = 0.1, float $max = 20.0): ?float
    {
        $off = $this->doubleOffset($buf, $name);
        if ($off === null) {
            return null;
        }
        $v = unpack('e', substr($buf, $off, 8))[1];
        if (is_nan($v) || is_infinite($v) || $v < $min || $v > $max) {
            return null;
        }

        return round($v, 2);
    }

    /** Найти смещение 8-байтового double поля по имени свойства (DoubleProperty). */
    private function doubleOffset(string $buf, string $name): ?int
    {
        $pos = strpos($buf, $name);
        if ($pos === false) {
            return null;
        }
        $typePos = strpos($buf, "DoubleProperty\0", $pos);
        if ($typePos === false || $typePos - $pos > 60) {
            return null;
        }
        $offset = $typePos + 15 + 8 + 1; // 15 = len("DoubleProperty\0"), 8 = size uint64, 1 = tag
        if (strlen($buf) < $offset + 8) {
            return null;
        }

        return $offset;
    }

    /** Найти 4-байтовое float значение поля (FloatProperty), например InitialAge. */
    private function readFloatField(string $buf, string $name, float $min = 1.0, float $max = 120.0): ?float
    {
        $pos = strpos($buf, $name);
        if ($pos === false) {
            return null;
        }
        $typePos = strpos($buf, "FloatProperty\0", $pos);
        if ($typePos === false || $typePos - $pos > 60) {
            return null;
        }
        $offset = $typePos + 14 + 8 + 1; // 14 = len("FloatProperty\0"), 8 = size uint64, 1 = tag
        if (strlen($buf) < $offset + 4) {
            return null;
        }
        $v = unpack('f', substr($buf, $offset, 4))[1];
        if (is_nan($v) || is_infinite($v) || $v < $min || $v > $max) {
            return null;
        }

        return round($v, 2);
    }

    /**
     * Все игровые навыки SCUM (имена из prisoner_skill) — эталон списка RCON-команд
     * SetSkillLevel из scum-server-manager 2.6.10. Показываем весь список,
     * даже если у игрока ещё нет строки в БД (уровень 0).
     */
    public const CANONICAL_SKILLS = [
        'ArcherySkill',
        'AviationSkill',
        'AwarenessSkill',
        'BoxingSkill',
        'CamouflageSkill',
        'CookingSkill',
        'DemolitionSkill',
        'DrivingSkill',
        'EnduranceSkill',
        'EngineeringSkill',
        'FarmingSkill',
        'HandgunSkill',
        'MedicalSkill',
        'MeleeWeaponsSkill',
        'MotorcycleSkill',
        'ResistanceSkill',
        'RiflesSkill',
        'RunningSkill',
        'SnipingSkill',
        'StealthSkill',
        'SurvivalSkill',
        'TacticsSkill',
        'ThieverySkill',
    ];

    /** Русские названия навыков для интерфейса (ключ — имя из prisoner_skill). */
    public const SKILL_LABELS = [
        'ArcherySkill' => 'Стрельба из лука',
        'AviationSkill' => 'Авиация',
        'AwarenessSkill' => 'Внимательность',
        'BoxingSkill' => 'Рукопашный бой',
        'CamouflageSkill' => 'Маскировка',
        'CookingSkill' => 'Кулинария',
        'DemolitionSkill' => 'Подрывное дело',
        'DrivingSkill' => 'Вождение',
        'EnduranceSkill' => 'Выносливость',
        'EngineeringSkill' => 'Инженерное дело',
        'FarmingSkill' => 'Земледелие',
        'HandgunSkill' => 'Пистолеты',
        'MedicalSkill' => 'Медицина',
        'MeleeWeaponsSkill' => 'Холодное оружие',
        'MotorcycleSkill' => 'Мотоциклы',
        'ResistanceSkill' => 'Сопротивление',
        'RiflesSkill' => 'Винтовки',
        'RunningSkill' => 'Бег',
        'SnipingSkill' => 'Снайперская стрельба',
        'StealthSkill' => 'Скрытность',
        'SurvivalSkill' => 'Выживание',
        'TacticsSkill' => 'Тактика',
        'ThieverySkill' => 'Воровство',
    ];

    /** Английские названия для RCON/отображения в скобках. */
    public const SKILL_EN_LABELS = [
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
        'MeleeWeaponsSkill' => 'Melee Weapons',
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

    /** Читаемое название навыка: по карте, иначе имя без суффикса «Skill». */
    public static function skillLabel(string $skillName): string
    {
        return self::SKILL_LABELS[$skillName] ?? preg_replace('/Skill$/', '', $skillName);
    }

    public static function skillEnLabel(string $skillName): string
    {
        return self::SKILL_EN_LABELS[$skillName] ?? preg_replace('/Skill$/', '', $skillName);
    }

    public function getSkills(string $steamId): array
    {
        $rows = $this->query('SELECT up.user_id AS steamId, up.name AS playerName, ps.name AS skillName,
            ps.level AS skillLevel, ps.experience AS skillExperience
            FROM user_profile up 
            JOIN prisoner p ON (p.id = up.prisoner_id OR p.user_profile_id = up.id)
            JOIN prisoner_skill ps ON ps.prisoner_id = p.id
            WHERE up.user_id = ? 
            ORDER BY up.id DESC, ps.name', [$steamId]);

        // Дополняем до полного списка: навыки без строки в БД отображаются с уровнем 0.
        $byName = [];
        foreach ($rows as $r) {
            $byName[$r['skillName']] = $r;
        }
        foreach (self::CANONICAL_SKILLS as $name) {
            if (! isset($byName[$name])) {
                $byName[$name] = [
                    'steamId' => $steamId,
                    'playerName' => $rows[0]['playerName'] ?? null,
                    'skillName' => $name,
                    'skillLevel' => 0,
                    'skillExperience' => 0,
                ];
            }
        }
        ksort($byName);

        return array_values(array_map(function (array $r) {
            $r['skillLabel'] = self::skillLabel($r['skillName']);
            $r['skillEn'] = self::skillEnLabel($r['skillName']);

            $xp = (float) ($r['skillExperience'] ?? 0);
            $lvl = (int) ($r['skillLevel'] ?? 0);
            // Если опыт равен или превышает 10 000 000 XP — это максимальный 4 уровень (Эксперт)
            if ($xp >= 10000000 && $lvl < 4) {
                $lvl = 4;
            }
            $r['skillLevel'] = min(self::MAX_SKILL_LEVEL, max(0, $lvl));
            $r['skillExperience'] = round($xp, 2);

            return $r;
        }, $byName));
    }

    public function getInventory(string $steamId, int $maxDepth = 6): array
    {
        $sql = "WITH RECURSIVE latest_prisoner_entity AS (
                    SELECT prisoner_id, MAX(entity_id) AS entity_id FROM prisoner_entity GROUP BY prisoner_id
                ),
                player_roots AS (
                    SELECT up.user_id AS steam_id, up.name AS player_name, pe.entity_id AS player_entity_id,
                        ec.id AS inventory_component_id
                    FROM user_profile up
                    JOIN latest_prisoner_entity lpe ON lpe.prisoner_id = up.prisoner_id
                    JOIN prisoner_entity pe ON pe.prisoner_id = lpe.prisoner_id AND pe.entity_id = lpe.entity_id
                    JOIN entity_component ec ON ec.entity_id = pe.entity_id
                    WHERE up.user_id = ? AND ec.name = 'Inventory'
                ),
                inventory_tree AS (
                    SELECT pr.steam_id, pr.player_name, pr.player_entity_id, pr.player_entity_id AS container_entity_id,
                        eice.entity_id, 0 AS depth, eice.data AS slot,
                        printf('%08d:%08d', eice.data, eice.entity_id) AS path
                    FROM player_roots pr
                    JOIN entity_inventory_component_entry eice ON eice.entity_component_id = pr.inventory_component_id
                    UNION ALL
                    SELECT it.steam_id, it.player_name, it.player_entity_id, it.entity_id AS container_entity_id,
                        child.entity_id, it.depth + 1, child.data AS slot, it.path || '>' || printf('%08d:%08d', child.data, child.entity_id) AS path
                    FROM inventory_tree it
                    JOIN entity_component ec ON ec.entity_id = it.entity_id AND ec.name = 'Inventory'
                    JOIN entity_inventory_component_entry child ON child.entity_component_id = ec.id
                    WHERE it.depth < ?
                )
                SELECT it.steam_id AS steamId, it.player_name AS playerName, it.player_entity_id AS playerEntityId,
                    it.container_entity_id AS containerEntityId, it.entity_id AS entityId, it.depth AS depth,
                    it.slot AS slot, it.path AS path, COALESCE(e.class, '') AS itemClass,
                    CASE WHEN COALESCE(e.class, '') LIKE '%.%' THEN
                        CASE WHEN substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1) LIKE '%\_C' ESCAPE '\'
                            THEN substr(substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1), 1, length(substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1)) - 2)
                            ELSE substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1) END
                        ELSE COALESCE(e.class, '') END AS itemId,
                    CASE WHEN it.depth = 0 AND it.slot = 2 THEN 'hands'
                         WHEN it.depth = 0 AND it.slot = 5 THEN 'equipped'
                         WHEN it.depth = 0 THEN 'root'
                         ELSE 'container' END AS slotKind,
                    CASE WHEN EXISTS (SELECT 1 FROM entity_component c WHERE c.entity_id = it.entity_id AND c.name = 'Inventory') THEN 1 ELSE 0 END AS hasInventory
                FROM inventory_tree it
                JOIN entity e ON e.id = it.entity_id
                ORDER BY CASE WHEN it.depth = 0 AND it.slot = 5 THEN 0
                              WHEN it.depth = 0 AND it.slot = 2 THEN 1
                              WHEN it.depth = 0 THEN 2 ELSE 3 END, it.path";

        return $this->query($sql, [$steamId, (int) $maxDepth]);
    }

    public function getQuickSlots(string $steamId): array
    {
        $sql = "WITH latest_prisoner_entity AS (
                    SELECT prisoner_id, MAX(entity_id) AS entity_id FROM prisoner_entity GROUP BY prisoner_id
                )
                SELECT up.user_id AS steamId, q.slot_index AS slotIndex, COALESCE(e.class, q.item_entity_setup, '') AS itemClass,
                    CASE WHEN COALESCE(e.class, q.item_entity_setup, '') LIKE '%.%' THEN
                        CASE WHEN substr(COALESCE(e.class, q.item_entity_setup, ''), instr(COALESCE(e.class, q.item_entity_setup, ''), '.') + 1) LIKE '%\_C' ESCAPE '\'
                            THEN substr(substr(COALESCE(e.class, q.item_entity_setup, ''), instr(COALESCE(e.class, q.item_entity_setup, ''), '.') + 1), 1, length(substr(COALESCE(e.class, q.item_entity_setup, ''), instr(COALESCE(e.class, q.item_entity_setup, ''), '.') + 1)) - 2)
                            ELSE substr(COALESCE(e.class, q.item_entity_setup, ''), instr(COALESCE(e.class, q.item_entity_setup, ''), '.') + 1) END
                        ELSE COALESCE(e.class, q.item_entity_setup, '') END AS itemId
                FROM user_profile up
                JOIN latest_prisoner_entity lpe ON lpe.prisoner_id = up.prisoner_id
                JOIN prisoner_inventory_quick_access_slot q ON q.prisoner_entity_id = lpe.entity_id
                LEFT JOIN entity e ON e.id = q.item_entity_id
                WHERE up.user_id = ? ORDER BY q.slot_index";

        return $this->query($sql, [$steamId]);
    }

    public function getSquads(): array
    {
        return $this->query('SELECT s.id AS squadId, s.name AS squadName, s.message AS message,
                s.emblem AS squadEmblem, s.information AS information, s.score AS score, s.member_limit AS memberLimit,
                s.last_member_login_time AS lastMemberLogin, s.last_member_logout_time AS lastMemberLogout,
                m.id AS memberId, m.rank AS memberRank, up.user_id AS memberSteamId,
                up.name AS memberName, up.fake_name AS memberFakeName, up.fame_points AS famePoints,
                up.last_login_time AS memberLastLogin, up.last_logout_time AS memberLastLogout,
                COALESCE(
                    (SELECT MAX(c.account_balance) FROM bank_account_registry r
                     JOIN bank_account_registry_currencies c ON c.bank_account_id = r.id
                     WHERE (r.user_profile_id = up.id OR r.account_owner_user_profile_id = up.id) AND c.currency_type = 1),
                    up.money_balance, 0
                ) AS moneyBalance,
                COALESCE(
                    (SELECT MAX(c.account_balance) FROM bank_account_registry r
                     JOIN bank_account_registry_currencies c ON c.bank_account_id = r.id
                     WHERE (r.user_profile_id = up.id OR r.account_owner_user_profile_id = up.id) AND c.currency_type = 2),
                    0
                ) AS goldBalance
                FROM squad s
                LEFT JOIN squad_member m ON m.squad_id = s.id
                LEFT JOIN user_profile up ON up.id = m.user_profile_id
                ORDER BY s.score DESC, s.id, m.rank ASC, up.fame_points DESC');
    }

    public function getVehicles(): array
    {
        try {
            $this->ensureOpen();
            $cols = array_column($this->query('PRAGMA table_info(vehicle_spawner)'), 'name');
            $hasLoc = in_array('location_x', $cols, true);
            $selectX = $hasLoc ? 'vs.location_x AS x' : 'e.location_x AS x';
            $selectY = $hasLoc ? 'vs.location_y AS y' : 'e.location_y AS y';
            $selectZ = $hasLoc ? 'vs.location_z AS z' : 'e.location_z AS z';

            return $this->query(
                "SELECT vs.vehicle_entity_id AS entityId,
                    replace(vs.vehicle_asset_id, 'Vehicle:', '') AS asset,
                    vs.vehicle_asset_id AS assetId, vs.vehicle_alias AS alias,
                    {$selectX}, {$selectY}, {$selectZ},
                    datetime(vs.vehicle_last_access_time, 'unixepoch') AS lastAccess,
                    is_vehicle_automatically_created AS automatic,
                    is_vehicle_functional AS functional,
                    time_spent_in_forbidden_zone AS forbiddenZoneSeconds,
                    e.class AS entityClass
                FROM vehicle_spawner vs
                LEFT JOIN entity e ON e.id = vs.vehicle_entity_id
                ORDER BY vs.vehicle_entity_id DESC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getFlags(): array
    {
        return $this->query('SELECT f.element_id AS elementId, b.id AS baseId, b.name AS baseName,
                b.location_x AS x, b.location_y AS y, b.location_x AS bx, b.location_y AS by,
                b.is_owned_by_player AS isOwnedByPlayer,
                e.location_x AS ex, e.location_y AS ey, e.location_z AS ez,
                up.name AS ownerName, up.user_id AS ownerSteamId,
                bu.name AS baseOwnerName, bu.user_id AS baseOwnerSteamId,
                eu.name AS elementOwnerName, eu.user_id AS elementOwnerSteamId,
                sq.name AS squadName, sq.id AS squadId,
                f.overtake_end_time AS overtakeEndTime, f.overtaker_user_profile_id AS overtakerProfileId,
                f.expanded_elements AS expandedElements,
                e.element_health AS health, e.asset AS asset,
                ot.name AS overtakerName, ot.user_id AS overtakerSteamId
                FROM base_element_flag f
                LEFT JOIN base_element e ON e.element_id = f.element_id
                LEFT JOIN base b ON b.id = e.base_id
                LEFT JOIN user_profile up ON up.id = COALESCE(b.owner_user_profile_id, b.user_profile_id, e.owner_profile_id)
                LEFT JOIN user_profile bu ON bu.id = b.owner_user_profile_id
                LEFT JOIN user_profile eu ON eu.id = e.owner_profile_id
                LEFT JOIN squad_member sm ON sm.user_profile_id = b.owner_user_profile_id
                LEFT JOIN squad sq ON sq.id = sm.squad_id
                LEFT JOIN user_profile ot ON ot.id = f.overtaker_user_profile_id
                ORDER BY f.element_id DESC');
    }

    public function getBankAccounts(): array
    {
        return $this->query('SELECT r.id AS accountId, COALESCE(r.user_profile_id, r.account_owner_user_profile_id) AS profileId,
                up.name AS ownerName, up.user_id AS ownerSteamId,
                r.bank_account_number AS accountNumber, r.save_timestamp AS saveTimestamp,
                c.currency_type AS currencyType, c.account_balance AS accountBalance
                FROM bank_account_registry r
                LEFT JOIN bank_account_registry_currencies c ON c.bank_account_id = r.id
                LEFT JOIN user_profile up ON up.id = COALESCE(r.user_profile_id, r.account_owner_user_profile_id)
                ORDER BY c.account_balance DESC, r.id LIMIT 300');
    }

    public function getEconomyLeaderboard(int $limit = 50): array
    {
        return $this->query('SELECT up.id AS profileId, up.name AS name, up.user_id AS steamId,
            up.fame_points AS famePoints, up.last_login_time AS lastLogin,
            COALESCE((SELECT MAX(c2.account_balance) FROM bank_account_registry r2
                JOIN bank_account_registry_currencies c2 ON c2.bank_account_id = r2.id
                WHERE (r2.user_profile_id = up.id OR r2.account_owner_user_profile_id = up.id) AND c2.currency_type = 1), up.money_balance, 0) AS walletBalance,
            COALESCE((SELECT MAX(c3.account_balance) FROM bank_account_registry r3
                JOIN bank_account_registry_currencies c3 ON c3.bank_account_id = r3.id
                WHERE (r3.user_profile_id = up.id OR r3.account_owner_user_profile_id = up.id) AND c3.currency_type = 2), 0) AS goldBalance
            FROM user_profile up ORDER BY walletBalance DESC, up.fame_points DESC LIMIT ?', [(int) $limit]);
    }

    // ===== PHOENIX-style rich queries =====

    /**
     * Детектирует имена колонок входа/выхода user_profile (зависит от версии SCUM).
     * Результат кэшируется на время запроса.
     */
    private function detectProfileCols(): array
    {
        if ($this->profileCols !== null) {
            return $this->profileCols;
        }
        $cols = array_column($this->query('PRAGMA table_info(user_profile)'), 'name');
        $login = 'last_login_time';
        foreach (['last_login_time', 'last_login', 'login_time', 'login'] as $c) {
            if (in_array($c, $cols, true)) {
                $login = $c;
                break;
            }
        }
        $logout = 'last_logout_time';
        foreach (['last_logout_time', 'last_logout', 'logout_time', 'logout'] as $c) {
            if (in_array($c, $cols, true)) {
                $logout = $c;
                break;
            }
        }

        return $this->profileCols = ['login' => $login, 'logout' => $logout];
    }

    /**
     * Богатый список игроков: бан, статистика (kills/deaths), деньги/золото — порт listPlayers().
     */
    public function listPlayers(int $limit = 1000): array
    {
        $cols = $this->detectProfileCols();

        return $this->query("SELECT
            u.id AS steamId, u.name AS steamName, u.is_banned AS isBanned,
            up.id AS profileId, up.name AS charName, up.fame_points AS fame,
            up.play_time AS playTime, up.{$cols['login']} AS lastLogin, up.{$cols['logout']} AS lastLogout,
            COALESCE(ss.kills, 0) AS kills, COALESCE(ss.deaths, 0) AS deaths,
            COALESCE(ss.prisoner_kills, 0) AS prisonerKills,
            COALESCE(ss.deaths_by_prisoners, 0) AS prisonerDeaths,
            COALESCE(ss.puppets_killed, 0) AS puppetsKilled,
            COALESCE(cash.account_balance, 0) AS money,
            COALESCE(gold.account_balance, 0) AS gold
        FROM user u
        JOIN user_profile up ON up.user_id = u.id
        LEFT JOIN survival_stats ss ON ss.user_profile_id = up.id
        LEFT JOIN bank_account_registry ba ON ba.account_owner_user_profile_id = up.id
        LEFT JOIN bank_account_registry_currencies cash
            ON cash.bank_account_id = ba.id AND cash.currency_type = 1
        LEFT JOIN bank_account_registry_currencies gold
            ON gold.bank_account_id = ba.id AND gold.currency_type = 2
        ORDER BY up.fame_points DESC LIMIT ?", [(int) $limit]);
    }

    /**
     * Полная карточка игрока (включая статистику и деньги/золото) — порт getPlayerFullDetail().
     */
    public function getPlayerFullDetail(string $steamId): ?array
    {
        $cols = $this->detectProfileCols();
        $rows = $this->query("SELECT
            u.id AS steamId, u.name AS steamName, u.is_banned AS isBanned,
            up.id AS profileId, up.name AS charName, up.fame_points AS fame,
            up.play_time AS playTime, up.{$cols['login']} AS lastLogin, up.{$cols['logout']} AS lastLogout,
            COALESCE(ss.kills, 0) AS kills, COALESCE(ss.deaths, 0) AS deaths,
            COALESCE(ss.prisoner_kills, 0) AS prisonerKills,
            COALESCE(ss.deaths_by_prisoners, 0) AS prisonerDeaths,
            COALESCE(ss.puppets_killed, 0) AS puppetsKilled,
            COALESCE(cash.account_balance, 0) AS money,
            COALESCE(gold.account_balance, 0) AS gold
        FROM user u
        JOIN user_profile up ON up.user_id = u.id
        LEFT JOIN survival_stats ss ON ss.user_profile_id = up.id
        LEFT JOIN bank_account_registry ba ON ba.account_owner_user_profile_id = up.id
        LEFT JOIN bank_account_registry_currencies cash
            ON cash.bank_account_id = ba.id AND cash.currency_type = 1
        LEFT JOIN bank_account_registry_currencies gold
            ON gold.bank_account_id = ba.id AND gold.currency_type = 2
        WHERE u.id = ? LIMIT 1", [$steamId]);

        return $rows[0] ?? null;
    }

    /**
     * Полная карточка игрока + атрибуты из body_simulation — порт getPlayerDetailWithAttrs().
     */
    public function getPlayerDetailWithAttrs(string $steamId): ?array
    {
        $base = $this->getPlayerFullDetail($steamId);
        if (! $base) {
            return null;
        }
        $attrs = $this->getAttributes($steamId) ?? [];
        foreach (['strength', 'constitution', 'dexterity', 'intelligence'] as $a) {
            if (isset($attrs[$a])) {
                $base[$a] = $attrs[$a];
            }
        }

        return $base;
    }

    /**
     * Отряды со счётом участников — порт listSquads().
     */
    public function listSquads(): array
    {
        return $this->query('SELECT
            s.id AS id, s.name AS name, COUNT(sm.id) AS memberCount
        FROM squad s
        LEFT JOIN squad_member sm ON sm.squad_id = s.id
        GROUP BY s.id
        ORDER BY memberCount DESC, s.name COLLATE NOCASE');
    }

    /**
     * Участники отряда — порт listSquadMembers().
     */
    public function listSquadMembers(int $squadId): array
    {
        return $this->query('SELECT
            sm.rank AS rank, up.id AS profileId, up.name AS charName,
            up.user_id AS steamId, up.name AS steamName, up.fame_points AS fame
        FROM squad_member sm
        JOIN user_profile up ON up.id = sm.user_profile_id
        WHERE sm.squad_id = ?
        ORDER BY sm.rank ASC, up.name COLLATE NOCASE', [(int) $squadId]);
    }

    // ===== Map zones =====

    /**
     * Кастомные зоны карты (сейфзоны, зоны трейдеров) — порт getMapZones().
     * configuration_index — это порядковый индекс в конфигурациях, отсортированных по id,
     * а не сам id. size_y == 0 => круговая зона (радиус = size_x), иначе прямоугольник.
     */
    public function getMapZones(): array
    {
        try {
            $this->ensureOpen();
        } catch (\Throwable $e) {
            return [];
        }
        $configs = $this->query('SELECT id, name, color_red, color_green, color_blue FROM custom_zone_configuration ORDER BY id');
        $regions = $this->query('SELECT name, location_x, location_y, size_x, size_y, configuration_index FROM custom_zone_region');
        if (empty($configs) || empty($regions)) {
            return [];
        }

        $colorToHex = function ($r, $g, $b) {
            $to = fn ($v) => str_pad(dechex((int) round(max(0.0, min(1.0, (float) $v)) * 255)), 2, '0', STR_PAD_LEFT);

            return '#'.$to($r).$to($g).$to($b);
        };

        $out = [];
        foreach ($regions as $reg) {
            $cfg = $configs[(int) ($reg['configuration_index'] ?? 0)] ?? ($configs[0] ?? null);
            $sizeX = (float) ($reg['size_x'] ?? 0);
            $sizeY = (float) ($reg['size_y'] ?? 0);
            $shape = $sizeY > 0 ? 'rect' : 'circle';
            $out[] = [
                'name' => (string) ($reg['name'] ?? ''),
                'x' => (float) ($reg['location_x'] ?? 0),
                'y' => (float) ($reg['location_y'] ?? 0),
                'shape' => $shape,
                'sizeX' => $sizeX,
                'sizeY' => $sizeY,
                'radius' => $shape === 'circle' ? $sizeX : 0,
                'color' => $cfg ? $colorToHex($cfg['color_red'] ?? 0, $cfg['color_green'] ?? 0, $cfg['color_blue'] ?? 0) : '#3f8a5a',
            ];
        }

        return $out;
    }

    /**
     * Позиции сундуков и хранилищ из SCUM.db: обход owning/parent chain до 10 хопов
     * (сундуки внутри транспорта или баз не имеют собственных координат).
     * Возвращает массив [entityId => ['x','y','z','entityClass']].
     */
    public function getChestPositions(): array
    {
        $result = [];
        try {
            $this->ensureOpen();
        } catch (\Throwable $e) {
            return $result;
        }
        $rows = $this->query('SELECT id, class, owning_entity_id, parent_entity_id, location_x, location_y, location_z FROM entity');
        if (empty($rows)) {
            return $result;
        }

        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }

        foreach ($rows as $chest) {
            $cls = (string) ($chest['class'] ?? '');
            $lc = strtolower($cls);
            if (str_contains($lc, 'ammobox') || str_contains($lc, 'clothes') || str_contains($lc, 'rig')
                || str_contains($lc, 'boxer') || str_contains($lc, 'boombox') || str_contains($lc, 'package')
                || str_contains($lc, 'vehicle') || str_contains($lc, 'car') || str_contains($lc, 'suv')
                || str_contains($lc, 'truck') || str_contains($lc, 'tractor') || str_contains($lc, 'quad')
                || str_contains($lc, 'bike') || str_contains($lc, 'boat') || str_contains($lc, 'sup_')) {
                continue;
            }
            if (! str_contains($lc, 'chest') && ! str_contains($lc, 'wardrobe') && ! str_contains($lc, 'cabinet')) {
                continue;
            }

            $id = (string) $chest['id'];
            $cur = $chest;
            $ownerFound = null;
            $seen = [];
            for ($i = 0; $i < 10; $i++) {
                if ($cur && abs((float) $cur['location_x']) > 1 && abs((float) $cur['location_y']) > 1) {
                    $ownerFound = $cur;
                    break;
                }
                $nextId = $cur && $cur['owning_entity_id'] !== null
                    ? $cur['owning_entity_id']
                    : ($cur && $cur['parent_entity_id'] !== null ? $cur['parent_entity_id'] : null);
                if ($nextId === null || isset($seen[(int) $nextId])) {
                    break;
                }
                $seen[(int) $nextId] = true;
                $cur = $byId[(int) $nextId] ?? null;
            }
            $loc = $ownerFound
                ?: ((abs((float) $chest['location_x']) > 1 && abs((float) $chest['location_y']) > 1) ? $chest : null);
            if ($loc) {
                $result[$id] = [
                    'x' => (float) $loc['location_x'],
                    'y' => (float) $loc['location_y'],
                    'z' => (float) ($loc['location_z'] ?? 0),
                    'entityClass' => (string) ($chest['class'] ?? ''),
                ];
            }
        }

        return $result;
    }

    public function getInventoryWithDetails(string $steamId): array
    {
        $items = $this->getInventory($steamId);
        if (empty($items)) {
            return $items;
        }
        $ids = array_column($items, 'entityId');
        $in = implode(',', array_map('intval', $ids));
        $comps = $this->query("SELECT ec.entity_id AS entityId, ec.name AS compName, hex(ec.data) AS dataHex,
            length(ec.data) AS dataLen FROM entity_component ec WHERE ec.entity_id IN ({$in}) ORDER BY ec.entity_id, ec.name");
        $map = [];
        foreach ($comps as $c) {
            $map[$c['entityId']][] = $c;
        }
        foreach ($items as &$item) {
            $item['components'] = [];
            foreach ($map[$item['entityId']] ?? [] as $c) {
                $buf = $c['dataHex'] ? hex2bin($c['dataHex']) : null;
                $val = null;
                if ($buf && $c['dataLen'] >= 4) {
                    $v = unpack('V', substr($buf, 0, 4));
                    if (in_array($c['compName'], ['Count', 'AmmunitionSlot'])) {
                        $val = $v[1];
                    } elseif (in_array($c['compName'], ['Durability', 'Weight', 'MaxDurability'])) {
                        $val = round((unpack('f', substr($buf, 0, 4))[1] ?? 0), 2);
                    }
                }
                $item['components'][] = ['name' => $c['compName'], 'value' => $val, 'raw' => $c['dataHex']];
            }
        }

        return $items;
    }

    public function getContainerContents(string $entityId, int $maxDepth = 6): array
    {
        $eid = (int) $entityId;
        if ($eid <= 0) {
            return ['found' => false, 'error' => 'Invalid entity id'];
        }
        try {
            $this->ensureOpen();
        } catch (\Throwable $e) {
            return ['found' => false, 'error' => 'SCUM.db недоступна'];
        }

        $rows = $this->query('SELECT id, class, owning_entity_id, parent_entity_id, location_x, location_y, location_z FROM entity WHERE id = ?', [$eid]);
        $entity = $rows[0] ?? null;
        if (! $entity) {
            return ['found' => false, 'error' => 'Контейнер не найден в SCUM.db'];
        }

        $loc = null;
        $cur = $entity;
        $seen = [];
        for ($i = 0; $i < 10; $i++) {
            if (abs((float) ($cur['location_x'] ?? 0)) > 1 && abs((float) ($cur['location_y'] ?? 0)) > 1) {
                $loc = $cur;
                break;
            }
            $nextId = $cur['owning_entity_id'] !== null
                ? (int) $cur['owning_entity_id']
                : ($cur['parent_entity_id'] !== null ? (int) $cur['parent_entity_id'] : 0);
            if ($nextId <= 0 || isset($seen[$nextId])) {
                break;
            }
            $seen[$nextId] = true;
            $row = $this->query('SELECT id, class, owning_entity_id, parent_entity_id, location_x, location_y, location_z FROM entity WHERE id = ?', [$nextId]);
            $cur = $row[0] ?? null;
            if (! $cur) {
                break;
            }
        }

        $sql = "WITH RECURSIVE container_root AS (
                    SELECT ec.id AS inventory_component_id
                    FROM entity_component ec
                    WHERE ec.entity_id = ? AND ec.name = 'Inventory'
                ),
                inventory_tree AS (
                    SELECT cr.inventory_component_id, eice.entity_id, 0 AS depth, eice.data AS slot,
                        printf('%08d:%08d', eice.data, eice.entity_id) AS path
                    FROM container_root cr
                    JOIN entity_inventory_component_entry eice ON eice.entity_component_id = cr.inventory_component_id
                    UNION ALL
                    SELECT it.inventory_component_id, child.entity_id, it.depth + 1, child.data AS slot,
                        it.path || '>' || printf('%08d:%08d', child.data, child.entity_id) AS path
                    FROM inventory_tree it
                    JOIN entity_component ec ON ec.entity_id = it.entity_id AND ec.name = 'Inventory'
                    JOIN entity_inventory_component_entry child ON child.entity_component_id = ec.id
                    WHERE it.depth < ?
                )
                SELECT it.entity_id AS entityId, it.depth AS depth, it.slot AS slot, it.path AS path,
                    COALESCE(e.class, '') AS itemClass,
                    CASE WHEN COALESCE(e.class, '') LIKE '%.%' THEN
                        CASE WHEN substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1) LIKE '%\_C' ESCAPE '\'
                            THEN substr(substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1), 1, length(substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1)) - 2)
                            ELSE substr(COALESCE(e.class, ''), instr(COALESCE(e.class, ''), '.') + 1) END
                        ELSE COALESCE(e.class, '') END AS itemId,
                    CASE WHEN EXISTS (SELECT 1 FROM entity_component c WHERE c.entity_id = it.entity_id AND c.name = 'Inventory') THEN 1 ELSE 0 END AS hasInventory
                FROM inventory_tree it
                JOIN entity e ON e.id = it.entity_id
                ORDER BY it.path";
        $items = $this->query($sql, [$eid, (int) $maxDepth]);

        if (! empty($items)) {
            $ids = array_column($items, 'entityId');
            $in = implode(',', array_map('intval', $ids));
            $comps = $this->query("SELECT ec.entity_id AS entityId, ec.name AS compName, hex(ec.data) AS dataHex,
                length(ec.data) AS dataLen FROM entity_component ec WHERE ec.entity_id IN ({$in}) ORDER BY ec.entity_id, ec.name");
            $map = [];
            foreach ($comps as $c) {
                $map[$c['entityId']][] = $c;
            }
            foreach ($items as &$item) {
                $item['components'] = [];
                foreach ($map[$item['entityId']] ?? [] as $c) {
                    $buf = $c['dataHex'] ? hex2bin($c['dataHex']) : null;
                    $val = null;
                    if ($buf && $c['dataLen'] >= 4) {
                        $v = unpack('V', substr($buf, 0, 4));
                        if (in_array($c['compName'], ['Count', 'AmmunitionSlot'])) {
                            $val = $v[1];
                        } elseif (in_array($c['compName'], ['Durability', 'Weight', 'MaxDurability'])) {
                            $val = round((unpack('f', substr($buf, 0, 4))[1] ?? 0), 2);
                        }
                    }
                    $item['components'][] = ['name' => $c['compName'], 'value' => $val, 'raw' => $c['dataHex']];
                }
            }
            unset($item);
        }

        return [
            'found' => true,
            'entityId' => $eid,
            'entityClass' => (string) ($entity['class'] ?? ''),
            'location' => $loc ? [
                'x' => (float) $loc['location_x'],
                'y' => (float) $loc['location_y'],
                'z' => (float) ($loc['location_z'] ?? 0),
            ] : null,
            'items' => $items,
            'total' => count($items),
        ];
    }

    // ===== Writes =====

    public function updateWallet(string $steamId, float $walletBalance, ?float $goldBalance = null): array
    {
        $profile = $this->getPlayerBySteamId($steamId);
        if (! $profile) {
            return ['ok' => false, 'error' => 'Player not found'];
        }
        if (! $this->run('UPDATE user_profile SET money_balance = ? WHERE user_id = ?', [$walletBalance, $steamId])) {
            return ['ok' => false, 'error' => 'Failed to update wallet'];
        }
        if ($goldBalance !== null) {
            $bankRows = $this->query('SELECT c.id AS currencyId FROM bank_account_registry r
                JOIN bank_account_registry_currencies c ON c.bank_account_id = r.id
                WHERE (r.user_profile_id = ? OR r.account_owner_user_profile_id = ?) AND c.currency_type = 2 LIMIT 1',
                [$profile['profileId'], $profile['profileId']]);
            if (! empty($bankRows[0])) {
                $this->run('UPDATE bank_account_registry_currencies SET account_balance = ? WHERE id = ?', [$goldBalance, $bankRows[0]['currencyId']]);
            }
        }

        return ['ok' => true];
    }

    public function updateFame(string $steamId, float $famePoints): array
    {
        if (! $this->run('UPDATE user_profile SET fame_points = ? WHERE user_id = ?', [$famePoints, $steamId])) {
            return ['ok' => false, 'error' => 'Failed to update fame'];
        }

        return ['ok' => true];
    }

    public function updateAttributes(string $steamId, array $attributes): array
    {
        $rows = $this->query('SELECT p.id AS prisonerId, hex(p.body_simulation) AS bodyHex
            FROM user_profile up 
            JOIN prisoner p ON (p.id = up.prisoner_id OR p.user_profile_id = up.id)
            WHERE up.user_id = ? 
            ORDER BY up.id DESC LIMIT 1', [$steamId]);
        if (empty($rows[0])) {
            return ['ok' => false, 'error' => 'Игрок не найден в базе данных'];
        }
        $pid = (int) $rows[0]['prisonerId'];
        $hex = $rows[0]['bodyHex'] ?? '';
        $buf = hex2bin($hex);
        if ($buf === false || strlen($buf) < 50) {
            return ['ok' => false, 'error' => 'Некорректный body_simulation'];
        }

        $propMap = [
            'strength' => 'BaseStrength',
            'constitution' => 'BaseConstitution',
            'dexterity' => 'BaseDexterity',
            'intelligence' => 'BaseIntelligence',
        ];

        $modified = false;
        foreach ($propMap as $key => $propName) {
            if (! isset($attributes[$key])) {
                continue;
            }
            $val = (float) $attributes[$key];
            $off = $this->doubleOffset($buf, $propName);
            if ($off !== null && $off + 8 <= strlen($buf)) {
                $packed = pack('e', $val);
                for ($k = 0; $k < 8; $k++) {
                    $buf[$off + $k] = $packed[$k];
                }
                $modified = true;
            }
        }

        if (! $modified) {
            return ['ok' => false, 'error' => 'Не удалось определить смещения атрибутов в body_simulation'];
        }

        $res = $this->run('UPDATE prisoner SET body_simulation = ? WHERE id = ?', [$buf, $pid]);

        return ['ok' => $res, 'error' => $res ? null : 'Ошибка сохранения атрибутов в базу'];
    }

    public function updateSkill(string $steamId, string $skillName, int $level, ?float $experience = null): array
    {
        $level = max(0, min(self::MAX_SKILL_LEVEL, (int) $level));
        $defaultXp = [0 => 0, 1 => 10000, 2 => 100000, 3 => 1000000, 4 => 10000000];
        $xp = $experience ?? (float) $defaultXp[$level];

        $pRows = $this->query('SELECT p.id AS prisonerId FROM user_profile up
            JOIN prisoner p ON (p.id = up.prisoner_id OR p.user_profile_id = up.id)
            WHERE up.user_id = ? ORDER BY up.id DESC LIMIT 1', [$steamId]);

        $pid = $pRows[0]['prisonerId'] ?? null;
        if ($pid === null) {
            return ['ok' => false, 'error' => 'Игрок не найден в базе данных'];
        }

        $sql = 'UPDATE prisoner_skill SET level = ?, experience = ? WHERE prisoner_id = ? AND name = ?';
        $params = [$level, $xp, $pid, $skillName];
        $affected = $this->runAffected($sql, $params);
        if ($affected < 0) {
            return ['ok' => false, 'error' => 'Ошибка записи в SCUM.db'];
        }
        if ($affected === 0) {
            // Строки навыка ещё нет (игрок не прокачивал его) — создаём.
            if (! $this->run('INSERT INTO prisoner_skill (prisoner_id, name, level, experience) VALUES (?, ?, ?, ?)', [$pid, $skillName, $level, $xp])) {
                return ['ok' => false, 'error' => 'Не удалось создать навык'];
            }
        }

        return ['ok' => true];
    }

    private function runAffected(string $sql, array $params = []): int
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->ensureOpen();
                $stmt = $this->pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue(is_int($k) ? $k + 1 : $k, $v);
                }
                $stmt->execute();

                return $stmt->rowCount();
            } catch (\Throwable $e) {
                $this->close();
                if ($attempt === 0) {
                    usleep(100000);

                    continue;
                }
                Log::channel('scum')->error('[SCUMdb] runAffected error: '.$e->getMessage());
            }
        }

        return -1;
    }

    /**
     * Первичный ключ таблицы (колонки с pk > 0). Может быть составным.
     */
    public function getPrimaryKey(string $table): array
    {
        return array_values(array_map(
            fn ($c) => $c['name'],
            array_filter($this->getTableColumns($table), fn ($c) => (int) $c['pk'] > 0)
        ));
    }

    /**
     * Универсальный UPDATE строки по критерию ($where: колонка => значение).
     */
    public function updateTableRow(string $table, array $where, array $data): array
    {
        if (empty($where)) {
            return ['ok' => false, 'error' => 'Не указано условие (строка не идентифицирована)'];
        }
        if (empty($data)) {
            return ['ok' => true];
        }
        $params = [];
        $set = [];
        foreach ($data as $col => $val) {
            $set[] = $this->escapeIdentifier((string) $col).' = ?';
            $params[] = $val;
        }
        $w = [];
        foreach ($where as $col => $val) {
            // __rowid__ — внутренний rowid SQLite (таблицы без PK), не экранируем кавычками.
            $w[] = ($col === '__rowid__' ? 'rowid' : $this->escapeIdentifier((string) $col)).' = ?';
            $params[] = $val;
        }
        $safeTable = $this->escapeIdentifier($table);
        if (! $this->run("UPDATE {$safeTable} SET ".implode(', ', $set).' WHERE '.implode(' AND ', $w), $params)) {
            return ['ok' => false, 'error' => 'Ошибка обновления строки'];
        }

        return ['ok' => true];
    }

    /**
     * Универсальный INSERT строки.
     */
    public function insertTableRow(string $table, array $data): array
    {
        if (empty($data)) {
            return ['ok' => false, 'error' => 'Нет данных для вставки'];
        }
        $cols = array_keys($data);
        $escapedCols = array_map(fn ($c) => $this->escapeIdentifier((string) $c), $cols);
        $placeholders = rtrim(str_repeat('?,', count($cols)), ',');
        $safeTable = $this->escapeIdentifier($table);
        $sql = "INSERT INTO {$safeTable} (".implode(',', $escapedCols).") VALUES ({$placeholders})";
        if (! $this->run($sql, array_values($data))) {
            return ['ok' => false, 'error' => 'Ошибка вставки строки'];
        }

        return ['ok' => true];
    }

    /**
     * Универсальный DELETE строки по критерию.
     */
    public function deleteTableRow(string $table, array $where): array
    {
        if (empty($where)) {
            return ['ok' => false, 'error' => 'Не указано условие (строка не идентифицирована)'];
        }
        $w = [];
        $params = [];
        foreach ($where as $col => $val) {
            $w[] = ($col === '__rowid__' ? 'rowid' : $this->escapeIdentifier((string) $col)).' = ?';
            $params[] = $val;
        }
        $safeTable = $this->escapeIdentifier($table);
        if (! $this->run("DELETE FROM {$safeTable} WHERE ".implode(' AND ', $w), $params)) {
            return ['ok' => false, 'error' => 'Ошибка удаления строки'];
        }

        return ['ok' => true];
    }

    private function escapeIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
