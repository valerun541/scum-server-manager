<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scum\CatalogService;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    private CatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CatalogService::class);
    }

    public function test_can_retrieve_items_with_limit_and_search(): void
    {
        $items = $this->service->getItems(limit: 10);
        $this->assertNotEmpty($items);
        $this->assertLessThanOrEqual(10, count($items));
        $this->assertArrayHasKey('id', $items[0]);
        $this->assertArrayHasKey('category', $items[0]);
    }

    public function test_can_filter_items_by_category(): void
    {
        $items = $this->service->getItems(category: 'weapons', limit: 5);
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame('weapons', $item['category']);
        }
    }

    public function test_can_retrieve_vehicles(): void
    {
        $vehicles = $this->service->getVehicles();
        $this->assertNotEmpty($vehicles);
        $this->assertArrayHasKey('id', $vehicles[0]);
        $this->assertArrayHasKey('command', $vehicles[0]);
        $this->assertStringStartsWith('#SpawnVehicle', $vehicles[0]['command']);
    }

    public function test_can_retrieve_npcs_and_animals(): void
    {
        $npcs = $this->service->getNpcs();
        $this->assertNotEmpty($npcs);
        $this->assertArrayHasKey('category', $npcs[0]);
    }

    public function test_can_retrieve_poi_markers(): void
    {
        $markers = $this->service->getMarkers(limit: 10);
        $this->assertNotEmpty($markers);
        $this->assertArrayHasKey('x', $markers[0]);
        $this->assertArrayHasKey('y', $markers[0]);
        $this->assertArrayHasKey('z', $markers[0]);
    }

    public function test_can_retrieve_categories_summary(): void
    {
        $categories = $this->service->getCategories();
        $this->assertNotEmpty($categories);
        $this->assertArrayHasKey('weapons', $categories);
    }
}
