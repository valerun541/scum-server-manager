<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PacksController;
use App\Http\Controllers\PlayersController;
use App\Http\Controllers\PluginsController;
use App\Http\Controllers\RconConsoleController;
use App\Http\Controllers\ScumDbController;
use App\Http\Controllers\ServerConfigController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WargmController;
use Illuminate\Support\Facades\Route;

// ===== Все маршруты панели под защитой IP Whitelist =====
Route::middleware(['ip.whitelist'])->group(function () {

    // ===== Auth =====
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
    Route::get('/login/2fa', [AuthController::class, 'form2fa'])->name('login.2fa');
    Route::post('/login/2fa', [AuthController::class, 'verify2fa'])->middleware('throttle:login')->name('login.2fa.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // ===== Защищённые маршруты панели =====
    Route::middleware(['scum.auth', 'throttle:panel'])->group(function () {
        // Интерактивный каталог (предметы, транспорт, NPC, маркеры)
        Route::get('/catalog-api/items', [CatalogController::class, 'items'])->name('catalog.items');
        Route::get('/catalog-api/categories', [CatalogController::class, 'categories'])->name('catalog.categories');
        Route::get('/catalog-api/vehicles', [CatalogController::class, 'vehicles'])->name('catalog.vehicles');
        Route::get('/catalog-api/npcs', [CatalogController::class, 'npcs'])->name('catalog.npcs');
        Route::get('/catalog-api/markers', [CatalogController::class, 'markers'])->name('catalog.markers');

        // Обратная совместимость с путями без префикса (включая слеш в конце)
        Route::get('/catalog/items', [CatalogController::class, 'items']);
        Route::get('/catalog/items/', [CatalogController::class, 'items']);
        Route::get('/catalog/categories', [CatalogController::class, 'categories']);
        Route::get('/catalog/categories/', [CatalogController::class, 'categories']);
        Route::get('/catalog/vehicles', [CatalogController::class, 'vehicles']);
        Route::get('/catalog/vehicles/', [CatalogController::class, 'vehicles']);
        Route::get('/catalog/npcs', [CatalogController::class, 'npcs']);
        Route::get('/catalog/npcs/', [CatalogController::class, 'npcs']);
        Route::get('/catalog/markers', [CatalogController::class, 'markers']);
        Route::get('/catalog/markers/', [CatalogController::class, 'markers']);
        // Servers (landing)
        Route::get('/', [ServersController::class, 'index'])->name('servers.index');
        Route::get('/servers', [ServersController::class, 'index'])->name('servers.list');
        Route::post('/servers', [ServersController::class, 'store'])->name('servers.store');
        Route::post('/servers/test-ftp', [ServersController::class, 'testFtp'])->name('servers.test-ftp');
        Route::post('/servers/test-rcon', [ServersController::class, 'testRcon'])->name('servers.test-rcon');
        Route::post('/servers/{id}', [ServersController::class, 'update'])->name('servers.update');
        Route::post('/servers/{id}/activate', [ServersController::class, 'activate'])->name('servers.activate');
        Route::post('/servers/{id}/enter', [ServersController::class, 'enter'])->name('servers.enter');
        Route::post('/servers/{id}/delete', [ServersController::class, 'destroy'])->name('servers.destroy');

        // Настройки учетной записи и безопасность (IP Whitelist + Profile + 2FA + Sessions)
        Route::get('/account/settings', [AccountController::class, 'index'])->name('account.settings');
        Route::post('/account/settings/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
        Route::get('/account/2fa/setup', [AccountController::class, 'get2faSetup'])->name('account.2fa.setup');
        Route::post('/account/2fa/enable', [AccountController::class, 'enable2fa'])->name('account.2fa.enable');
        Route::post('/account/2fa/disable', [AccountController::class, 'disable2fa'])->name('account.2fa.disable');
        Route::post('/account/sessions/revoke-others', [AccountController::class, 'revokeOtherSessions'])->name('account.sessions.revokeOthers');
        Route::post('/account/settings/whitelist/toggle', [AccountController::class, 'toggleWhitelist'])->name('account.whitelist.toggle');
        Route::post('/account/settings/whitelist/add', [AccountController::class, 'addWhitelist'])->name('account.whitelist.add');
        Route::post('/account/settings/whitelist/quick-add', [AccountController::class, 'quickAddWhitelist'])->name('account.whitelist.quickAdd');
        Route::post('/account/settings/whitelist/delete', [AccountController::class, 'deleteWhitelist'])->name('account.whitelist.delete');

        // Алиасы для обратной совместимости
        Route::get('/security/whitelist', fn () => redirect()->route('account.settings'))->name('security.whitelist');
        Route::post('/security/whitelist/toggle', [AccountController::class, 'toggleWhitelist'])->name('security.whitelist.toggle');
        Route::post('/security/whitelist/add', [AccountController::class, 'addWhitelist'])->name('security.whitelist.add');
        Route::post('/security/whitelist/quick-add', [AccountController::class, 'quickAddWhitelist'])->name('security.whitelist.quickAdd');
        Route::post('/security/whitelist/delete', [AccountController::class, 'deleteWhitelist'])->name('security.whitelist.delete');

        // Игроки (главная после входа в панель)
        Route::get('/players', [PlayersController::class, 'index'])->name('players.index');
        Route::get('/map', [MapController::class, 'index'])->name('map.index');
        Route::get('/map/stats', [MapController::class, 'stats'])->name('map.stats');
        Route::get('/map/image', [MapController::class, 'image'])->name('map.image');
        Route::get('/map/chest/{entityId}', [MapController::class, 'chest'])->name('map.chest');

        // RCON / FTP подключение (переключатели на дашборде)
        Route::post('/settings/rcon/connect', [SettingsController::class, 'connectRcon'])->name('settings.rcon.connect');
        Route::post('/settings/rcon/disconnect', [SettingsController::class, 'disconnectRcon'])->name('settings.rcon.disconnect');
        Route::post('/settings/ftp/connect', [SettingsController::class, 'connectFtp'])->name('settings.ftp.connect');
        Route::post('/settings/ftp/disconnect', [SettingsController::class, 'disconnectFtp'])->name('settings.ftp.disconnect');

        // RCON Console
        Route::get('/rcon', [RconConsoleController::class, 'index'])->name('rcon.index');
        Route::post('/rcon/send', [RconConsoleController::class, 'send'])->name('rcon.send');
        Route::post('/rcon/connect', [RconConsoleController::class, 'connect'])->name('rcon.connect');
        Route::post('/rcon/disconnect', [RconConsoleController::class, 'disconnect'])->name('rcon.disconnect');

        // Players
        Route::get('/players/whois/{steamId}', [PlayersController::class, 'whois'])->name('players.whois');
        Route::post('/players/action', [PlayersController::class, 'action'])->name('players.action');
        Route::get('/players/vehicles/{steamId}', [PlayersController::class, 'vehicles'])->name('players.vehicles');

        // Packs
        Route::get('/packs', [PacksController::class, 'index'])->name('packs.index');
        Route::post('/packs', [PacksController::class, 'save'])->name('packs.save');
        Route::post('/packs/give', [PacksController::class, 'give'])->name('packs.give');
        Route::post('/packs/cooldowns/reset', [PacksController::class, 'resetCooldown'])->name('packs.cooldowns.reset');

        // Plugins
        Route::get('/plugins/teleport', [PluginsController::class, 'teleport'])->name('plugins.teleport');
        Route::post('/plugins/teleport', [PluginsController::class, 'saveTeleport'])->name('plugins.teleport.save');
        Route::get('/plugins/vip', [PluginsController::class, 'vip'])->name('plugins.vip');
        Route::post('/plugins/vip', [PluginsController::class, 'saveVip'])->name('plugins.vip.save');
        Route::post('/plugins/vip/add', [PluginsController::class, 'addVipPlayer'])->name('plugins.vip.add');
        Route::post('/plugins/vip/remove', [PluginsController::class, 'removeVipPlayer'])->name('plugins.vip.remove');
        Route::get('/plugins/savehome', [PluginsController::class, 'savehome'])->name('plugins.savehome');
        Route::post('/plugins/savehome', [PluginsController::class, 'saveSavehome'])->name('plugins.savehome.save');
        Route::get('/plugins/airdrop', [PluginsController::class, 'airdrop'])->name('plugins.airdrop');
        Route::post('/plugins/airdrop', [PluginsController::class, 'saveAirdrop'])->name('plugins.airdrop.save');
        Route::post('/plugins/airdrop/drop', [PluginsController::class, 'dropAirdrop'])->name('plugins.airdrop.drop');
        Route::post('/plugins/airdrop/call', [PluginsController::class, 'dropAirdrop'])->name('plugins.airdrop.call');
        Route::post('/plugins/airdrop/calibrate/record', [PluginsController::class, 'recordCalibration'])->name('plugins.airdrop.calibrate.record');
        Route::post('/plugins/airdrop/calibrate', [PluginsController::class, 'recordCalibration'])->name('plugins.airdrop.calibrate');
        Route::post('/plugins/airdrop/calibrate/reset', [PluginsController::class, 'resetCalibration'])->name('plugins.airdrop.calibrate.reset');
        Route::get('/plugins/rewards', [PluginsController::class, 'rewards'])->name('plugins.rewards');
        Route::post('/plugins/rewards', [PluginsController::class, 'saveRewards'])->name('plugins.rewards.save');
        Route::post('/plugins/rewards/hourly', [PluginsController::class, 'hourlyRewards'])->name('plugins.rewards.hourly');
        Route::post('/plugins/rewards/top', [PluginsController::class, 'topRewards'])->name('plugins.rewards.top');
        Route::get('/plugins/rewards/player/{steamId}', [PluginsController::class, 'rewardsPlayer'])->name('plugins.rewards.player');
        Route::post('/plugins/rewards/blacklist', [PluginsController::class, 'saveBlacklist'])->name('plugins.rewards.blacklist');
        Route::post('/plugins/chat-sender', [PluginsController::class, 'saveChatSender'])->name('plugins.chat-sender');
        Route::get('/plugins/insurance', [PluginsController::class, 'insurance'])->name('plugins.insurance');
        Route::post('/plugins/insurance/settings', [PluginsController::class, 'saveInsuranceSettings'])->name('plugins.insurance.settings');
        Route::post('/plugins/insurance/grant', [PluginsController::class, 'grantInsurance'])->name('plugins.insurance.grant');
        Route::post('/plugins/insurance/claim', [PluginsController::class, 'claimInsuranceAdmin'])->name('plugins.insurance.claim');
        Route::post('/plugins/insurance/{id}/update', [PluginsController::class, 'updateInsurance'])->name('plugins.insurance.update');
        Route::post('/plugins/insurance/{id}/delete', [PluginsController::class, 'deleteInsurance'])->name('plugins.insurance.delete');

        // Logs
        Route::get('/logs/chat', [LogsController::class, 'chat'])->name('logs.chat');
        Route::post('/logs/chat/send', [LogsController::class, 'sendChat'])->name('logs.chat.send');
        Route::get('/logs/events', [LogsController::class, 'events'])->name('logs.events');
        Route::get('/logs/rcon', [LogsController::class, 'rconCommands'])->name('logs.rcon');
        Route::get('/logs/audit', [LogsController::class, 'audit'])->name('logs.audit');

        Route::get('/logs/system', [LogsController::class, 'system'])->name('logs.system');
        Route::get('/logs/admin', [LogsController::class, 'admin'])->name('logs.admin');
        Route::get('/logs/login', [LogsController::class, 'login'])->name('logs.login');
        Route::get('/logs/vehicle', [LogsController::class, 'vehicle'])->name('logs.vehicle');
        Route::get('/logs/online', [LogsController::class, 'online'])->name('logs.online');
        Route::get('/logs/chest', [LogsController::class, 'chest'])->name('logs.chest');
        Route::get('/logs/economy', [LogsController::class, 'economy'])->name('logs.economy');
        Route::get('/logs/famepoints', [LogsController::class, 'famepoints'])->name('logs.famepoints');
        Route::get('/logs/gameplay', [LogsController::class, 'gameplay'])->name('logs.gameplay');
        Route::get('/logs/kill', [LogsController::class, 'kill'])->name('logs.kill');
        Route::get('/logs/loot', [LogsController::class, 'loot'])->name('logs.loot');
        Route::get('/logs/violations', [LogsController::class, 'violations'])->name('logs.violations');

        Route::get('/logs/quests', [LogsController::class, 'quests'])->name('logs.quests');
        Route::get('/logs/sentry', [LogsController::class, 'sentry'])->name('logs.sentry');
        // Logs AJAX polling
        Route::get('/logs/poll/chat', [LogsController::class, 'pollChat'])->name('logs.poll.chat');
        Route::get('/logs/poll/events', [LogsController::class, 'pollEvents'])->name('logs.poll.events');
        Route::get('/logs/poll/rcon', [LogsController::class, 'pollRcon'])->name('logs.poll.rcon');
        Route::get('/logs/poll/system', [LogsController::class, 'pollSystem'])->name('logs.poll.system');
        Route::get('/logs/poll/type/{type}', [LogsController::class, 'pollType'])->name('logs.poll.type');

        // SCUM.db
        Route::get('/scumdb', [ScumDbController::class, 'index'])->name('scumdb.index');
        Route::get('/scumdb/players', [ScumDbController::class, 'players'])->name('scumdb.players');
        Route::get('/scumdb/player/{steamId}', [ScumDbController::class, 'player'])->name('scumdb.player');
        Route::post('/scumdb/player/{steamId}/wallet', [ScumDbController::class, 'updateWallet'])->name('scumdb.wallet');
        Route::post('/scumdb/player/{steamId}/attributes', [ScumDbController::class, 'updateAttributes'])->name('scumdb.attributes');
        Route::get('/scumdb/player/{steamId}/attributes', fn (string $steamId) => redirect()->route('scumdb.player', ['steamId' => $steamId, 'tab' => 'attributes']));
        Route::post('/scumdb/player/{steamId}/skill', [ScumDbController::class, 'updateSkill'])->name('scumdb.skill');
        Route::post('/scumdb/player/{steamId}/skills/max', [ScumDbController::class, 'updateAllSkills'])->name('scumdb.skills.max');
        Route::get('/scumdb/player/{steamId}/skill', fn (string $steamId) => redirect()->route('scumdb.player', ['steamId' => $steamId, 'tab' => 'skills']));
        Route::get('/scumdb/squads', [ScumDbController::class, 'squads'])->name('scumdb.squads');
        Route::get('/scumdb/vehicles', [ScumDbController::class, 'vehicles'])->name('scumdb.vehicles');
        Route::get('/scumdb/flags', [ScumDbController::class, 'flags'])->name('scumdb.flags');
        Route::get('/scumdb/banks', [ScumDbController::class, 'banks'])->name('scumdb.banks');
        Route::get('/scumdb/economy', [ScumDbController::class, 'economy'])->name('scumdb.economy');
        Route::get('/scumdb/table/{table}', [ScumDbController::class, 'table'])->name('scumdb.table');
        Route::post('/scumdb/sync', [ScumDbController::class, 'sync'])->name('scumdb.sync');
        Route::post('/scumdb/upload', [ScumDbController::class, 'upload'])->name('scumdb.upload');
        Route::post('/scumdb/table/{table}/update', [ScumDbController::class, 'updateRow'])->name('scumdb.table.update');
        Route::post('/scumdb/table/{table}/insert', [ScumDbController::class, 'insertRow'])->name('scumdb.table.insert');
        Route::post('/scumdb/table/{table}/delete', [ScumDbController::class, 'deleteRow'])->name('scumdb.table.delete');

        // Конфиги сервера (FTP)
        Route::get('/config-files', [ServerConfigController::class, 'index'])->name('config-files.index');
        Route::post('/config-files/read', [ServerConfigController::class, 'read'])->name('config-files.read');
        Route::post('/config-files/save', [ServerConfigController::class, 'save'])->name('config-files.save');
        Route::post('/config-files/backup', [ServerConfigController::class, 'backup'])->name('config-files.backup');
        Route::post('/config-files/restore', [ServerConfigController::class, 'restore'])->name('config-files.restore');

        // WARGM
        Route::get('/wargm', [WargmController::class, 'index'])->name('wargm.index');
        Route::post('/wargm/settings', [WargmController::class, 'saveSettings'])->name('wargm.settings');
        Route::post('/wargm/test', [WargmController::class, 'test'])->name('wargm.test');
        Route::post('/wargm/cards', [WargmController::class, 'saveCard'])->name('wargm.cards.save');
        Route::post('/wargm/cards/{id}', [WargmController::class, 'updateCard'])->name('wargm.cards.update');
        Route::post('/wargm/cards/{id}/delete', [WargmController::class, 'deleteCard'])->name('wargm.cards.delete');
        Route::post('/wargm/cards/{id}/duplicate', [WargmController::class, 'duplicateCard'])->name('wargm.cards.duplicate');
        Route::post('/wargm/check', [WargmController::class, 'checkPlayer'])->name('wargm.check');
        Route::get('/wargm/deliveries/{steamId}', [WargmController::class, 'deliveries'])->name('wargm.deliveries');
        Route::get('/wargm/export', [WargmController::class, 'export'])->name('wargm.export');
        Route::post('/wargm/import', [WargmController::class, 'import'])->name('wargm.import');

        // Automation
        Route::get('/automation', [AutomationController::class, 'index'])->name('automation.index');
        Route::get('/automation/create', [AutomationController::class, 'create'])->name('automation.create');
        Route::get('/automation/{id}', [AutomationController::class, 'edit'])->name('automation.edit');
        Route::post('/automation/save', [AutomationController::class, 'save'])->name('automation.save');
        Route::post('/automation/{id}/delete', [AutomationController::class, 'delete'])->name('automation.delete');
        Route::post('/automation/{id}/toggle', [AutomationController::class, 'toggle'])->name('automation.toggle');
        Route::post('/automation/{id}/test', [AutomationController::class, 'test'])->name('automation.test');
        Route::post('/automation/{id}/reset-stats', [AutomationController::class, 'resetStats'])->name('automation.reset-stats');
        Route::post('/automation/schedules/run', [AutomationController::class, 'runSchedules'])->name('automation.schedules');
    });

    // Server-Sent Events (SSE) стриминг логов в реальном времени
    Route::get('/logs/stream', [LogsController::class, 'stream'])
        ->middleware(['scum.auth'])
        ->name('logs.stream');

    // Поллинг статуса RCON-задач — вне throttle:panel
    Route::get('/rcon/job/{id}', [RconConsoleController::class, 'jobStatus'])
        ->whereNumber('id')
        ->middleware(['scum.auth', 'throttle:jobs'])
        ->name('rcon.job');
});
