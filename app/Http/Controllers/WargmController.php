<?php

namespace App\Http\Controllers;

use App\Http\Requests\WargmCardRequest;
use App\Http\Requests\WargmCheckRequest;
use App\Http\Requests\WargmImportRequest;
use App\Http\Requests\WargmSettingsRequest;
use App\Services\Scum\CatalogService;
use App\Services\Scum\WargmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WargmController extends Controller
{
    public function __construct(
        private WargmService $wargm,
        private CatalogService $catalog,
    ) {}

    public function index(Request $request)
    {
        $cards = $this->wargm->getCards();
        $totalCards = count($cards);
        $enabledCards = count(array_filter($cards, fn ($c) => $c['enabled']));
        $itemTypes = collect($cards)->flatMap(fn ($c) => collect($c['items'])->pluck('type'))->unique()->count();
        $deliveryCount = DB::table('wargm_deliveries')->count();
        $apiConnected = Cache::remember('wargm_api_status', 30, function () {
            return $this->wargm->testConnection($this->wargm->getSettings())['ok'] ?? false;
        });
        $search = $request->input('delivery_search');
        $deliveries = $this->wargm->getAllDeliveries(50, $search);

        $catalogItems = collect($this->catalog->getItems(limit: 0))->keyBy(fn ($i) => mb_strtolower($i['id']))->all();
        $catalogVehicles = collect($this->catalog->getVehicles())->keyBy(fn ($v) => mb_strtolower($v['id']))->all();

        return view('wargm.index', [
            'settings' => $this->wargm->getSettings(),
            'cards' => $cards,
            'deliveries' => $deliveries,
            'catalogItems' => $catalogItems,
            'catalogVehicles' => $catalogVehicles,
            'stats' => [
                'totalCards' => $totalCards,
                'enabledCards' => $enabledCards,
                'itemTypes' => $itemTypes,
                'deliveryCount' => $deliveryCount,
                'apiConnected' => $apiConnected,
            ],
        ]);
    }

    public function saveSettings(WargmSettingsRequest $request)
    {
        $data = $request->validated();

        // Пустое поле или masked-sentinel (••••••••… = ключ не менялся)
        if (empty($data['apiKey']) || str_contains($data['apiKey'], '••')) {
            $data['apiKey'] = $this->wargm->getSettings()['apiKey'];
        }

        $this->wargm->saveSettings($data);
        Cache::forget('wargm_api_status');

        return back()->with('success', 'Настройки WARGM сохранены');
    }

    public function test(Request $request)
    {
        $settings = $this->wargm->getSettings();
        $apiUrl = (string) $request->input('apiUrl', '');
        $shopId = (string) $request->input('shopId', '');
        $apiKey = (string) $request->input('apiKey', '');
        if ($apiUrl !== '') {
            $settings['apiUrl'] = $apiUrl;
        }
        if ($shopId !== '') {
            $settings['shopId'] = $shopId;
        }
        if ($apiKey !== '' && ! str_contains($apiKey, '••')) {
            $settings['apiKey'] = $apiKey;
        }

        $result = $this->wargm->testConnection($settings);

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'WARGM API подключено' : ('Ошибка: '.$result['message']));
    }

    public function saveCard(WargmCardRequest $request)
    {
        $data = $request->validated();
        $items = $this->parseCardItems($request->input('items_json', '[]'));
        $id = $this->wargm->saveCard([
            'id' => $request->input('card_id') ? (int) $request->input('card_id') : null,
            'name' => $data['name'],
            'shopItemId' => $data['shop_item_id'],
            'enabled' => $request->has('enabled') && $request->input('enabled') === '1',
            'items' => $items,
        ]);

        return back()->with($id !== null ? 'success' : 'error', $id !== null ? 'Карточка сохранена' : 'Ошибка сохранения карточки');
    }

    public function updateCard(WargmCardRequest $request, int $id)
    {
        $card = $this->wargm->getCard($id);
        if (! $card) {
            return back()->with('error', 'Карточка не найдена');
        }
        $items = $this->parseCardItems($request->input('items_json', '[]'));
        $this->wargm->saveCard([
            'id' => $id,
            'name' => $request->input('name', $card['name']),
            'shopItemId' => $request->input('shop_item_id', $card['shopItemId']),
            'enabled' => $request->has('enabled') && $request->input('enabled') === '1',
            'items' => $items,
        ]);

        return back()->with('success', 'Карточка обновлена');
    }

    public function deleteCard(int $id)
    {
        $this->wargm->deleteCard($id);

        return back()->with('success', 'Карточка удалена');
    }

    public function duplicateCard(int $id)
    {
        $this->wargm->duplicateCard($id);

        return back()->with('success', 'Карточка дублирована');
    }

    public function checkPlayer(WargmCheckRequest $request)
    {
        $steamId = (string) $request->input('steam_id', '');
        if (! $steamId) {
            return back()->with('error', 'Укажите SteamID');
        }
        $playerName = $this->wargm->resolvePlayerName($steamId);
        $result = $this->wargm->processPlayer($steamId, $playerName);
        $msg = $result['ok'] ? 'Выдано: '.implode('; ', $result['results']) : implode('; ', $result['results']);

        return back()->with($result['ok'] ? 'success' : 'info', $msg);
    }

    public function deliveries(Request $request, string $steamId)
    {
        $data = [
            'steamId' => $steamId,
            'deliveries' => $this->wargm->getDeliveriesBySteam($steamId),
        ];
        if ($request->query('fragment') === '1') {
            return view('wargm.deliveries-fragment', $data);
        }

        return view('wargm.deliveries', $data);
    }

    public function export()
    {
        return response()->streamDownload(function () {
            echo $this->wargm->exportCards();
        }, 'wargm_cards_export.json', ['Content-Type' => 'application/json']);
    }

    public function import(WargmImportRequest $request)
    {
        $json = (string) $request->input('import_json', '');
        if (! $json) {
            return back()->with('error', 'Вставьте экспорт');
        }
        $result = $this->wargm->importCards($json);
        $msg = "Импортировано: {$result['imported']}";
        if (! empty($result['errors'])) {
            $msg .= '; Ошибки: '.implode('; ', array_slice($result['errors'], 0, 5));
        }

        return back()->with('success', $msg);
    }

    private function parseCardItems(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }
        $items = [];
        foreach ($data as $item) {
            if (empty($item['type'])) {
                continue;
            }
            $items[] = [
                'type' => $item['type'],
                'data' => $item['data'] ?? [],
            ];
        }

        return $items;
    }
}
