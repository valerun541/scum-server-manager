<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScumDbAttributesRequest;
use App\Http\Requests\ScumDbSkillRequest;
use App\Http\Requests\ScumDbTableRequest;
use App\Http\Requests\ScumDbWalletRequest;
use App\Services\Scum\DaemonService;
use App\Services\Scum\FtpLogWatcherService;
use App\Services\Scum\ScumDbQueryService;
use App\Services\Scum\ScumDbService;
use App\Services\Scum\SettingsService;
use App\Services\Scum\SteamProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScumDbController extends Controller
{
    public function __construct(
        private ScumDbService $scumDb,
        private SettingsService $settings,
        private FtpLogWatcherService $ftp,
        private SteamProfileService $steam,
        private ScumDbQueryService $queryService,
    ) {
        $config = $this->settings->getAppConfig();
        $this->scumDb->setDbPath($this->scumDbPath());
    }

    private function scumDbPath(): string
    {
        $local = storage_path('app/scum/SCUM.db');
        if (file_exists($local)) {
            return $local;
        }
        // fallback: пользовательский путь из настроек
        $config = $this->settings->getAppConfig();
        if (! empty($config['hostFtp']['scumDbLocalPath']) && file_exists($config['hostFtp']['scumDbLocalPath'])) {
            return $config['hostFtp']['scumDbLocalPath'];
        }

        return $local;
    }

    /**
     * Синхронизация локальной копии SCUM.db с FTP, если она отсутствует или устарела (TTL 5 мин, как в оригинальном боте).
     * При неудаче FTP тихо используем имеющуюся локальную копию.
     * Если демон scum:watch запущен — он сам держит копию свежей, веб-запрос FTP не трогает.
     */
    private function ensureFreshScumDb(): void
    {
        $local = storage_path('app/scum/SCUM.db');
        $config = $this->settings->getAppConfig();
        if (empty($config['hostFtp']['enabled']) || empty($config['hostFtp']['host'])) {
            return;
        }

        // Демон поддерживает свежесть копии в фоне — не качаем по FTP в веб-запросе.
        if (app(DaemonService::class)->isRunning(DaemonService::WATCH) && is_file($local)) {
            return;
        }

        $needsSync = ! is_file($local) || (time() - (int) @filemtime($local)) >= 300;
        if (! $needsSync) {
            return;
        }
        try {
            $this->ftp->reloadConfig();
            $result = $this->ftp->syncScumDb();
            if (! empty($result['ok'])) {
                // syncScumDb сам переоткрывает PDO и обновляет путь к свежему файлу.
            } else {
                Log::channel('scum')->warning('[Scumdb] auto-sync failed: '.($result['error'] ?? 'unknown error'));
            }
        } catch (\Throwable $e) {
            Log::channel('scum')->warning('[Scumdb] auto-sync failed: '.$e->getMessage());
        }
    }

    public function index()
    {
        $this->ensureFreshScumDb();
        $config = $this->settings->getAppConfig();
        $hostFtp = $config['hostFtp'] ?? [];
        $local = storage_path('app/scum/SCUM.db');

        $counts = [
            'players' => $this->scumDb->isAvailable() ? $this->scumDb->countTableRows('user_profile') : 0,
            'squads' => $this->scumDb->isAvailable() ? $this->scumDb->countTableRows('squad') : 0,
            'vehicles' => $this->scumDb->isAvailable() ? $this->scumDb->countTableRows('vehicle_spawner') : 0,
            'flags' => $this->scumDb->isAvailable() ? $this->scumDb->countTableRows('base_element_flag') : 0,
            'banks' => $this->scumDb->isAvailable() ? $this->scumDb->countTableRows('bank_account_registry') : 0,
            'tables' => $this->scumDb->isAvailable() ? count($this->scumDb->getTables()) : 0,
        ];

        return view('scumdb.index', [
            'available' => $this->scumDb->isAvailable(),
            'tables' => $this->scumDb->isAvailable() ? $this->scumDb->getTables() : [],
            'counts' => $counts,
            'ftpEnabled' => ! empty($hostFtp['enabled']) && ! empty($hostFtp['host']),
            'ftpHost' => $hostFtp['host'] ?? '',
            'dbState' => $this->ftp->getDbState(),
            'localExists' => is_file($local),
            'localMtime' => is_file($local) ? (int) @filemtime($local) : null,
            'localSize' => is_file($local) ? (int) @filesize($local) : null,
            'hasPending' => $this->ftp->hasPendingUpload(),
        ]);
    }

    /**
     * Ручное скачивание SCUM.db с сервера по FTP (сильная синхронизация).
     * Блокируется, если есть невыгруженные локальные правки, чтобы их не затереть.
     */
    public function sync(Request $request)
    {
        $config = $this->settings->getAppConfig();
        if (empty($config['hostFtp']['enabled']) || empty($config['hostFtp']['host'])) {
            return back()->with('error', 'FTP не настроен — укажите доступ в настройках хоста');
        }
        if ($this->ftp->hasPendingUpload()) {
            return back()->with('error', 'Есть невыгруженные локальные правки — скачивание затрёт их. '
                .'Сначала выгрузите базу на сервер (обычно файл занят игровым процессом, поэтому выгрузка доступна после остановки/перезапуска сервера)');
        }
        $this->ftp->reloadConfig();
        $result = $this->ftp->syncScumDb(true);
        // syncScumDb переоткрывает свой PDO; переоткрываем и наш, чтобы читать свежий файл.
        $this->scumDb->close();
        $this->scumDb->setDbPath($this->scumDbPath());
        if (! empty($result['ok'])) {
            return back()->with('success', 'SCUM.db скачана с сервера и сохранена локально');
        }

        return back()->with('error', 'Не удалось скачать SCUM.db: '.($result['error'] ?? 'неизвестная ошибка'));
    }

    /**
     * Ручная выгрузка локальной SCUM.db на сервер по FTP.
     */
    public function upload(Request $request)
    {
        $config = $this->settings->getAppConfig();
        if (empty($config['hostFtp']['enabled']) || empty($config['hostFtp']['host'])) {
            return back()->with('error', 'FTP не настроен — укажите доступ в настройках хоста');
        }
        $this->ftp->reloadConfig();
        $result = $this->ftp->uploadScumDb();
        if (! empty($result['ok'])) {
            return back()->with('success', 'SCUM.db выгружена на сервер');
        }

        return back()->with('error', 'Не удалось выгрузить SCUM.db: '.($result['error'] ?? 'неизвестная ошибка'));
    }

    public function players(Request $request)
    {
        $this->ensureFreshScumDb();
        $players = $this->scumDb->listPlayers(1000);

        return view('scumdb.players', [
            'players' => $players,
            'available' => $this->scumDb->isAvailable(),
        ]);
    }

    public function player(Request $request, string $steamId)
    {
        $this->ensureFreshScumDb();
        if (! $this->scumDb->isAvailable()) {
            return back()->with('error', 'SCUM.db недоступен');
        }
        $tab = $request->query('tab', 'inventory');
        $appConfig = $this->settings->getAppConfig();
        $viewData = [
            'player' => $this->scumDb->getPlayerBySteamId($steamId),
            'wallet' => $this->scumDb->getWallet($steamId),
            'steamId' => $steamId,
            'tab' => $tab,
            'available' => true,
            'packsEnabled' => ($appConfig['plugins']['packs']['enabled'] ?? true) !== false,
        ];
        switch ($tab) {
            case 'attributes':
                $viewData['attributes'] = $this->scumDb->getAttributes($steamId);
                break;
            case 'skills':
                $viewData['skills'] = $this->scumDb->getSkills($steamId);
                break;
            case 'quick':
                $viewData['quickSlots'] = $this->scumDb->getQuickSlots($steamId);
                break;
            default:
                $viewData['inventory'] = $this->scumDb->getInventoryWithDetails($steamId);
        }
        $viewData['maxAttribute'] = ScumDbService::MAX_ATTRIBUTE;
        $viewData['attributeMax'] = ScumDbService::ATTRIBUTE_MAX;
        $viewData['maxSkillLevel'] = ScumDbService::MAX_SKILL_LEVEL;
        $viewData['steamAvatar'] = $this->steam->avatar($steamId);

        return view('scumdb.player', $viewData);
    }

    public function updateWallet(ScumDbWalletRequest $request, string $steamId)
    {
        $wallet = (float) $request->input('wallet', 0);
        $gold = $request->input('gold') !== '' && $request->input('gold') !== null ? (float) $request->input('gold') : null;
        $result = $this->scumDb->updateWallet($steamId, $wallet, $gold);
        if (! empty($result['ok'])) {
            $this->ftp->markPendingUpload();
        }

        return back()->with(! empty($result['ok']) ? 'success' : 'error', ! empty($result['ok']) ? 'Кошелёк обновлён' : ($result['error'] ?? 'Ошибка'));
    }

    public function updateAttributes(ScumDbAttributesRequest $request, string $steamId)
    {
        $keys = ScumDbQueryService::RCON_ATTRIBUTE_ORDER;
        $attrs = [];
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $attrs[$key] = max(0.5, min(ScumDbService::ATTRIBUTE_MAX[$key], round((float) $request->input($key), 2)));
            }
        }
        if (empty($attrs)) {
            return back()->with('error', 'Не указаны атрибуты');
        }

        $res = $this->queryService->updateAttributes($steamId, $attrs);

        return back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function updateSkill(ScumDbSkillRequest $request, string $steamId)
    {
        $name = (string) $request->input('name', '');
        $level = (int) $request->input('level', 0);
        if (! $name) {
            return back()->with('error', 'Не указано название навыка');
        }

        $res = $this->queryService->updateSkill($steamId, $name, $level);

        return back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function updateAllSkills(Request $request, string $steamId)
    {
        $level = max(0, min(ScumDbService::MAX_SKILL_LEVEL, (int) $request->input('level', ScumDbService::MAX_SKILL_LEVEL)));
        $res = $this->queryService->updateAllSkills($steamId, $level);

        return back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function squads()
    {
        $this->ensureFreshScumDb();

        return view('scumdb.squads', [
            'squads' => $this->scumDb->getSquads(),
            'available' => $this->scumDb->isAvailable(),
        ]);
    }

    public function vehicles()
    {
        $this->ensureFreshScumDb();

        $vehicles = $this->scumDb->getVehicles();
        $vehicles = $this->queryService->enrichVehiclesWithOwner($vehicles);

        return view('scumdb.vehicles', [
            'vehicles' => $vehicles,
            'available' => $this->scumDb->isAvailable(),
        ]);
    }

    public function flags()
    {
        $this->ensureFreshScumDb();

        return view('scumdb.flags', [
            'flags' => $this->scumDb->getFlags(),
            'available' => $this->scumDb->isAvailable(),
        ]);
    }

    public function banks()
    {
        $this->ensureFreshScumDb();

        return view('scumdb.banks', [
            'accounts' => $this->scumDb->getBankAccounts(),
            'available' => $this->scumDb->isAvailable(),
        ]);
    }

    public function economy()
    {
        $this->ensureFreshScumDb();

        return view('scumdb.economy', [
            'leaderboard' => $this->scumDb->getEconomyLeaderboard(),
            'available' => $this->scumDb->isAvailable(),
        ]);
    }

    /**
     * Универсальный просмотр содержимого произвольной таблицы SCUM.db.
     */
    public function table(Request $request, string $table)
    {
        $this->ensureFreshScumDb();
        $perPage = 100;
        $page = max(1, (int) $request->query('page', 1));
        $q = trim((string) $request->query('q', ''));

        $data = $this->queryService->getTableData($table, $page, $perPage, $q ?: null);

        $cfg = [
            'table' => $table,
            'cols' => $data['editorCols'],
            'rows' => $data['editorRows'],
            'hasPk' => $data['hasPk'],
            'hasRowId' => $data['hasRowId'],
            'hasPending' => $this->ftp->hasPendingUpload(),
            'updateUrl' => route('scumdb.table.update', $table),
            'insertUrl' => route('scumdb.table.insert', $table),
            'deleteUrl' => route('scumdb.table.delete', $table),
            'csrf' => csrf_token(),
        ];

        return view('scumdb.table', [
            'table' => $table,
            'tables' => $data['tables'],
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'cfg' => $cfg,
            'hasPk' => $data['hasPk'],
            'hasRowId' => $data['hasRowId'],
            'hasPending' => $this->ftp->hasPendingUpload(),
            'total' => $data['total'],
            'page' => $data['page'],
            'maxPage' => $data['maxPage'],
            'perPage' => $perPage,
            'q' => $q,
            'available' => $this->scumDb->isAvailable(),
        ]);
    }

    // ===== Редактирование таблиц =====

    private function prepareFreshBase(): void
    {
        try {
            $config = $this->settings->getAppConfig();
            if (empty($config['hostFtp']['enabled']) || empty($config['hostFtp']['host'])) {
                return;
            }
            $this->ftp->reloadConfig();
            if ($this->ftp->hasPendingUpload()) {
                return;
            }
            $this->ftp->syncScumDb();
        } catch (\Throwable $e) {
            Log::channel('scum')->warning('[Scumdb] fresh base sync failed: '.$e->getMessage());
        } finally {
            // Файл мог быть заменён синком (новый inode) — переоткрываем PDO текущего файла.
            $this->scumDb->close();
            $this->scumDb->setDbPath($this->scumDbPath());
        }
    }

    private function requireTable(string $table): array
    {
        $tables = $this->scumDb->getTables();
        if (! in_array($table, $tables, true)) {
            abort(404, 'Таблица не найдена');
        }

        return $this->scumDb->getTableColumns($table);
    }

    /** Преобразует строку из формы/JSON в типизированное значение SQLite (пустое/NULL → null). */
    private function convertCell(string $raw, array $col): mixed
    {
        $declared = strtoupper((string) ($col['type'] ?? ''));
        if ($raw === '' || strtoupper($raw) === 'NULL') {
            return null;
        }
        if (str_contains($declared, 'INT')) {
            return (int) $raw;
        }
        if (preg_match('/REAL|FLOA|DOUB|NUM/', $declared)) {
            return (float) $raw;
        }
        if (str_contains($declared, 'BLOB')) {
            $clean = preg_replace('/\s+/', '', $raw);
            $clean = preg_replace('/^HEX:/i', '', $clean);
            $bin = $clean === '' ? null : @hex2bin($clean);

            return $bin === false ? $raw : $bin;
        }

        return $raw;
    }

    private function buildWhere(Request $request, array $colMap): ?array
    {
        $id = $request->input('_id', []);
        if (! is_array($id) || empty($id)) {
            return null;
        }
        $where = [];
        foreach ($id as $col => $val) {
            if ($col === '__rowid__') {
                $where['__rowid__'] = (int) $val;

                continue;
            }
            if (! isset($colMap[$col])) {
                return null;
            }
            $where[$col] = $this->convertCell((string) $val, $colMap[$col]);
        }

        return $where;
    }

    private function buildData(Request $request, array $colMap): array
    {
        $raw = $request->input('cols', []);
        if (! is_array($raw)) {
            return [];
        }
        $data = [];
        foreach ($raw as $col => $val) {
            if (! isset($colMap[$col]) || ! is_scalar($val)) {
                continue;
            }
            $data[$col] = $this->convertCell((string) $val, $colMap[$col]);
        }

        return $data;
    }

    private function afterWrite(Request $request, array $result, string $okMsg)
    {
        if (empty($result['ok'])) {
            return $this->respondJson($request, false, $result['error'] ?? 'Ошибка записи в SCUM.db');
        }
        // Локальная правка есть — защищаем её от перекачки демоном, пока не выгружена.
        $this->ftp->markPendingUpload();
        // Загрузка изменённой локальной копии на сервер по FTP (как в updateSkill/updateAttributes).
        $upload = $this->ftp->uploadScumDb();
        $msg = $okMsg;
        if (empty($upload['ok'])) {
            $msg .= '. ⚠️ Изменено в локальной SCUM.db, но НЕ загружено на сервер: '.($upload['error'] ?? 'FTP ошибка')
                .'. Обычно файл занят игровым процессом — изменения применятся при остановке/перезапуске сервера';
        } else {
            $msg .= ' и загружено на сервер';
        }

        return $this->respondJson($request, true, $msg);
    }

    public function updateRow(ScumDbTableRequest $request, string $table)
    {
        $this->prepareFreshBase();
        $cols = $this->requireTable($table);
        $colMap = [];
        foreach ($cols as $c) {
            $colMap[$c['name']] = $c;
        }
        $where = $this->buildWhere($request, $colMap);
        if ($where === null) {
            return $this->respondJson($request, false, 'Не указан идентификатор строки');
        }
        $data = $this->buildData($request, $colMap);
        if (empty($data)) {
            return $this->respondJson($request, false, 'Нет данных для обновления');
        }

        return $this->afterWrite($request, $this->scumDb->updateTableRow($table, $where, $data), 'Строка обновлена');
    }

    public function insertRow(ScumDbTableRequest $request, string $table)
    {
        $this->prepareFreshBase();
        $cols = $this->requireTable($table);
        $colMap = [];
        foreach ($cols as $c) {
            $colMap[$c['name']] = $c;
        }
        $data = $this->buildData($request, $colMap);
        if (empty($data)) {
            return $this->respondJson($request, false, 'Нет данных для вставки');
        }

        return $this->afterWrite($request, $this->scumDb->insertTableRow($table, $data), 'Строка добавлена');
    }

    public function deleteRow(ScumDbTableRequest $request, string $table)
    {
        $this->prepareFreshBase();
        $cols = $this->requireTable($table);
        $colMap = [];
        foreach ($cols as $c) {
            $colMap[$c['name']] = $c;
        }
        $where = $this->buildWhere($request, $colMap);
        if ($where === null) {
            return $this->respondJson($request, false, 'Не указан идентификатор строки');
        }

        return $this->afterWrite($request, $this->scumDb->deleteTableRow($table, $where), 'Строка удалена');
    }
}
