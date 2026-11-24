<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Scum\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
    ) {}

    /**
     * Поиск и получение предметов каталога.
     */
    public function items(Request $request): JsonResponse
    {
        $search = $request->query('q');
        $category = $request->query('category');
        $limit = max(1, min(100, (int) $request->query('limit', 50)));

        $items = $this->catalog->getItems(
            category: is_string($category) ? $category : null,
            search: is_string($search) ? $search : null,
            limit: $limit
        );

        return response()->json([
            'ok' => true,
            'data' => $items,
            'count' => count($items),
        ]);
    }

    /**
     * Список категорий предметов.
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => array_values($this->catalog->getCategories()),
        ]);
    }

    /**
     * Поиск и получение транспорта.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $search = $request->query('q');
        $type = $request->query('type');

        $vehicles = $this->catalog->getVehicles(
            type: is_string($type) ? $type : null,
            search: is_string($search) ? $search : null,
        );

        return response()->json([
            'ok' => true,
            'data' => $vehicles,
            'count' => count($vehicles),
        ]);
    }

    /**
     * Поиск и получение NPC и животных.
     */
    public function npcs(Request $request): JsonResponse
    {
        $search = $request->query('q');
        $category = $request->query('category');

        $npcs = $this->catalog->getNpcs(
            category: is_string($category) ? $category : null,
            search: is_string($search) ? $search : null,
        );

        return response()->json([
            'ok' => true,
            'data' => $npcs,
            'count' => count($npcs),
        ]);
    }

    /**
     * Поиск и получение точек интереса (POI / Маркеры).
     */
    public function markers(Request $request): JsonResponse
    {
        $search = $request->query('q');
        $type = $request->query('type');
        $limit = max(1, min(100, (int) $request->query('limit', 50)));

        $markers = $this->catalog->getMarkers(
            type: is_string($type) ? $type : null,
            search: is_string($search) ? $search : null,
            limit: $limit
        );

        return response()->json([
            'ok' => true,
            'data' => $markers,
            'count' => count($markers),
        ]);
    }
}
