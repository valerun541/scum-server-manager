<?php

declare(strict_types=1);

namespace App\Services\Scum;

use Illuminate\Support\Facades\Cache;

class CatalogService
{
    private const string ITEMS_FILE = 'scum/catalog_items.json';

    private const string VEHICLES_FILE = 'scum/catalog_vehicles.json';

    private const string NPCS_FILE = 'scum/catalog_npcs.json';

    private const string MARKERS_FILE = 'scum/catalog_markers.json';

    /**
     * Возвращает список предметов с фильтрацией и поиском.
     *
     * @return array<int, array{id: string, name: string, name_ru: string, category: string, category_ru: string, subcategory: string, command: string, icon: ?string}>
     */
    public function getItems(?string $category = null, ?string $search = null, int $limit = 50): array
    {
        /** @var array<int, array{id: string, name: string, name_ru: string, category: string, category_ru: string, subcategory: string, command: string, icon: ?string}> $items */
        $items = $this->loadJson(self::ITEMS_FILE);

        if ($category !== null && $category !== '' && $category !== 'all') {
            $catLower = mb_strtolower($category);
            $items = array_values(array_filter($items, fn ($i) => mb_strtolower($i['category']) === $catLower));
        }

        if ($search !== null && trim($search) !== '') {
            $items = array_values(array_filter($items, fn ($i) => $this->matchesSearch($i, $search)));
        }

        if ($limit > 0) {
            return array_slice($items, 0, $limit);
        }

        return $items;
    }

    /**
     * Возвращает список доступных категорий предметов с количеством.
     *
     * @return array<string, array{key: string, name_ru: string, count: int}>
     */
    public function getCategories(): array
    {
        return Cache::remember('scum_catalog_categories_v2', 3600, function () {
            $items = $this->loadJson(self::ITEMS_FILE);
            $categories = [];

            foreach ($items as $item) {
                $cat = $item['category'] ?? 'other';
                $catRu = $item['category_ru'] ?? 'Разное';
                if (! isset($categories[$cat])) {
                    $categories[$cat] = [
                        'key' => $cat,
                        'name_ru' => $catRu,
                        'count' => 0,
                    ];
                }
                $categories[$cat]['count']++;
            }

            uasort($categories, fn ($a, $b) => $b['count'] <=> $a['count']);

            return $categories;
        });
    }

    /**
     * Возвращает список транспорта.
     *
     * @return array<int, array{id: string, name: string, type: string, command: string, icon?: ?string}>
     */
    public function getVehicles(?string $type = null, ?string $search = null): array
    {
        /** @var array<int, array{id: string, name: string, type: string, command: string, icon?: ?string}> $vehicles */
        $vehicles = $this->loadJson(self::VEHICLES_FILE);

        if ($type !== null && $type !== '' && $type !== 'all') {
            $tLower = mb_strtolower($type);
            $vehicles = array_values(array_filter($vehicles, fn ($v) => mb_strtolower($v['type']) === $tLower));
        }

        if ($search !== null && trim($search) !== '') {
            $vehicles = array_values(array_filter($vehicles, fn ($v) => $this->matchesSearch($v, $search)));
        }

        return $vehicles;
    }

    /**
     * Возвращает список NPC и животных.
     *
     * @return array<int, array{id: string, name: string, category: string, command: string, icon?: ?string}>
     */
    public function getNpcs(?string $category = null, ?string $search = null): array
    {
        /** @var array<int, array{id: string, name: string, category: string, command: string, icon?: ?string}> $npcs */
        $npcs = $this->loadJson(self::NPCS_FILE);

        if ($category !== null && $category !== '' && $category !== 'all') {
            $cLower = mb_strtolower($category);
            $npcs = array_values(array_filter($npcs, fn ($n) => mb_strtolower($n['category']) === $cLower));
        }

        if ($search !== null && trim($search) !== '') {
            $npcs = array_values(array_filter($npcs, fn ($n) => $this->matchesSearch($n, $search)));
        }

        return $npcs;
    }

    /**
     * Возвращает маркеры карты и точки интереса (POI).
     *
     * @return array<int, array{name: string, type: string, type_ru: string, x: float, y: float, z: float, command: string}>
     */
    public function getMarkers(?string $type = null, ?string $search = null, int $limit = 50): array
    {
        /** @var array<int, array{name: string, type: string, type_ru: string, x: float, y: float, z: float, command: string}> $markers */
        $markers = $this->loadJson(self::MARKERS_FILE);

        if ($type !== null && $type !== '' && $type !== 'all') {
            $tLower = mb_strtolower($type);
            $markers = array_values(array_filter($markers, fn ($m) => mb_strtolower($m['type']) === $tLower));
        }

        if ($search !== null && trim($search) !== '') {
            $markers = array_values(array_filter($markers, fn ($m) => $this->matchesSearch($m, $search)));
        }

        if ($limit > 0) {
            return array_slice($markers, 0, $limit);
        }

        return $markers;
    }

    /**
     * Поиск предмета по точному ID.
     *
     * @return array{id: string, name: string, name_ru: string, category: string, category_ru: string, subcategory: string, command: string, icon: ?string}|null
     */
    public function findItem(string $id): ?array
    {
        $idLower = mb_strtolower(trim($id));
        /** @var array<int, array{id: string, name: string, name_ru: string, category: string, category_ru: string, subcategory: string, command: string, icon: ?string}> $items */
        $items = $this->loadJson(self::ITEMS_FILE);

        foreach ($items as $item) {
            if (mb_strtolower($item['id']) === $idLower) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Поиск транспорта по точному ID.
     *
     * @return array{id: string, name: string, type: string, command: string, icon?: ?string}|null
     */
    public function findVehicle(string $id): ?array
    {
        $idLower = mb_strtolower(trim($id));
        /** @var array<int, array{id: string, name: string, type: string, command: string, icon?: ?string}> $vehicles */
        $vehicles = $this->loadJson(self::VEHICLES_FILE);

        foreach ($vehicles as $vehicle) {
            if (mb_strtolower($vehicle['id']) === $idLower) {
                return $vehicle;
            }
        }

        return null;
    }

    /**
     * Интеллектуальный поиск по ключевым словам с поддержкой кириллицы/латиницы, синонимов и раскладки клавиатуры.
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesSearch(array $item, string $query): bool
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return true;
        }

        // Подготовка целевого поискового текста для элемента
        $target = mb_strtolower(
            ($item['name'] ?? '').' '.
            ($item['name_ru'] ?? '').' '.
            ($item['id'] ?? '').' '.
            ($item['category_ru'] ?? '').' '.
            ($item['category'] ?? '').' '.
            ($item['type_ru'] ?? '').' '.
            ($item['type'] ?? '').' '.
            str_replace('_', ' ', (string) ($item['id'] ?? ''))
        );

        // Быстрое прямое вхождение полной строки
        if (str_contains($target, $q)) {
            return true;
        }

        // Словарь синонимов и сленга SCUM
        $synonyms = [
            'калаш' => ['ak47', 'ak-47', 'ak_47', 'akm', 'ak15', 'ак'],
            'калашникова' => ['ak47', 'ak-47', 'ak_47', 'akm'],
            'ак' => ['ak47', 'ak-47', 'ak_47', 'akm', 'aks'],
            'свд' => ['svd'],
            'драгунов' => ['svd'],
            'мосинка' => ['mosin'],
            'винторез' => ['vss'],
            'всс' => ['vss'],
            'вал' => ['as_val', 'as val'],
            'дигл' => ['desert_eagle', 'deagle'],
            'мка' => ['m16', 'm4a1'],
            'эмка' => ['m16', 'm4a1'],
            'пнв' => ['night_vision', 'nvg'],
            'сишка' => ['c4', 'tnt'],
            'си4' => ['c4'],
            'патроны' => ['ammo', 'cartridge', 'gauge', 'buckshot', 'slug', 'cal_'],
            'дробь' => ['birdshot', 'buckshot'],
            'картечь' => ['buckshot'],
            'пуля' => ['slug', 'bullet'],
            'хлеб' => ['bread'],
            'яблоко' => ['apple'],
            'вода' => ['water', 'canteen', 'bottle'],
            'топор' => ['axe', 'hatchet', 'tomahawk'],
            'нож' => ['knife', 'cleaver', 'dagger'],
            'пила' => ['saw'],
            'лопата' => ['shovel', 'spade'],
            'кувалда' => ['sledgehammer'],
            'молоток' => ['hammer'],
            'отмычка' => ['lockpick', 'bobby_pin'],
            'отмычки' => ['lockpick', 'bobby_pin'],
            'аптечка' => ['first_aid', 'medkit', 'bandage'],
            'бинт' => ['bandage', 'rag'],
            'таблетки' => ['pills', 'antibiotics', 'painkillers', 'vitamins'],
            'броник' => ['bulletproof', 'vest'],
            'бронежилет' => ['bulletproof', 'vest'],
            'шлем' => ['helmet'],
            'каска' => ['helmet'],
            'маска' => ['mask', 'balaclava'],
            'балаклава' => ['balaclava'],
            'рюкзак' => ['backpack'],
            'штаны' => ['pants', 'jeans'],
            'куртка' => ['jacket'],
            'ботинки' => ['boots'],
            'перчатки' => ['gloves'],
            'бензин' => ['gasoline', 'fuel', 'canister'],
            'ящик' => ['chest', 'box', 'wardrobe'],
            'бревно' => ['log'],
            'доска' => ['plank'],
            'гвозди' => ['nails'],
            'замок' => ['lock'],
            'машина' => ['vehicle', 'car', 'laika', 'wolfswagen', 'rager', 'suv', 'pickup', 'tractor'],
            'тачка' => ['vehicle', 'car', 'laika', 'wolfswagen', 'rager'],
            'авто' => ['vehicle', 'car', 'laika', 'wolfswagen', 'rager'],
            'автомобиль' => ['vehicle', 'car', 'laika', 'wolfswagen', 'rager'],
            'транспорт' => ['vehicle', 'car', 'bike', 'boat', 'plane'],
            'лайка' => ['laika'],
            'гольф' => ['wolfswagen', 'wolfwagon'],
            'фольксваген' => ['wolfswagen', 'wolfwagon'],
            'рейджер' => ['rager'],
            'рейнджер' => ['rager'],
            'рагер' => ['rager'],
            'крейсер' => ['cruiser'],
            'чоппер' => ['cruiser'],
            'эндуро' => ['dirtbike'],
            'мотоцикл' => ['bike', 'cruiser', 'dirtbike', 'sportbike'],
            'байк' => ['bike', 'cruiser', 'dirtbike', 'sportbike'],
            'мот' => ['bike', 'cruiser', 'dirtbike', 'sportbike'],
            'велосипед' => ['bicycle', 'bike'],
            'велик' => ['bicycle', 'bike'],
            'квадрик' => ['quad'],
            'квадроцикл' => ['quad'],
            'пикап' => ['pickup'],
            'джип' => ['suv', 'rager'],
            'лодка' => ['boat', 'dinghy', 'raft', 'barba'],
            'катер' => ['motorboat', 'barba'],
            'самолет' => ['plane', 'kinglet', 'duster'],
            'вертолет' => ['plane', 'helicopter'],
            'трактор' => ['tractor'],
            'медведь' => ['bear'],
            'волк' => ['wolf'],
            'кабан' => ['boar'],
            'олень' => ['deer'],
            'лошадь' => ['horse'],
            'курица' => ['chicken'],
            'кролик' => ['rabbit'],
            'зомби' => ['zombie', 'drifter'],
            'бункер' => ['bunker', 'ww2'],
            'пещера' => ['cave'],
            'заправка' => ['gas_station'],
            'банкомат' => ['atm'],
            'полиция' => ['police'],
            'больница' => ['hospital'],
        ];

        // Проверяем каждое отдельное слово из поискового запроса
        $words = preg_split('/[\s\-_,]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($words)) {
            return true;
        }

        foreach ($words as $word) {
            $wordMatched = false;

            if (str_contains($target, $word)) {
                $wordMatched = true;
            } elseif (isset($synonyms[$word])) {
                foreach ($synonyms[$word] as $syn) {
                    if (str_contains($target, $syn)) {
                        $wordMatched = true;
                        break;
                    }
                }
            }

            // Переключение раскладки клавиатуры RU -> EN
            if (! $wordMatched) {
                $converted = $this->convertKeyboardLayout($word);
                if ($converted !== $word && str_contains($target, $converted)) {
                    $wordMatched = true;
                }
            }

            if (! $wordMatched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Конвертация раскладки клавиатуры (йцукен <-> qwerty).
     */
    private function convertKeyboardLayout(string $str): string
    {
        $ru = ['й', 'ц', 'у', 'к', 'е', 'н', 'г', 'ш', 'щ', 'з', 'х', 'ъ', 'ф', 'ы', 'в', 'а', 'п', 'р', 'о', 'л', 'д', 'ж', 'э', 'я', 'ч', 'с', 'м', 'и', 'т', 'ь', 'б', 'ю'];
        $en = ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p', '[', ']', 'a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', ';', '"', 'z', 'x', 'c', 'v', 'b', 'n', 'm', ',', '.'];

        if (preg_match('/[а-яё]/u', $str)) {
            return str_replace($ru, $en, $str);
        }

        return str_replace($en, $ru, $str);
    }

    /**
     * Универсальная загрузка и кэширование JSON-файлов каталога.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadJson(string $relativePath): array
    {
        $cacheKey = 'scum_catalog_file_v2_'.md5($relativePath);

        return Cache::remember($cacheKey, 3600, function () use ($relativePath) {
            $path = storage_path('app/'.$relativePath);
            if (! file_exists($path)) {
                return [];
            }

            $content = file_get_contents($path);
            if ($content === false) {
                return [];
            }

            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : [];
        });
    }
}
