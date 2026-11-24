<?php

namespace Tests\Unit;

use App\Support\VehicleParser;
use PHPUnit\Framework\TestCase;

class VehicleParserTest extends TestCase
{
    public function test_parses_empty_output_returns_empty_array(): void
    {
        $this->assertSame([], VehicleParser::parseList(''));
        $this->assertSame([], VehicleParser::parseList("   \n\n  "));
    }

    public function test_parses_single_vehicle_line(): void
    {
        $raw = 'ID 42 | BPC_WolfWagon | name: Red Beast | (150000.5, -230000.2, 500.0) | owner: ScumKing (db id 101)';
        $vehicles = VehicleParser::parseList($raw);

        $this->assertCount(1, $vehicles);
        $v = $vehicles[0];

        $this->assertSame(42, $v['entityId']);
        $this->assertSame('BPC_WolfWagon', $v['asset']);
        $this->assertSame('Red Beast', $v['customName']);
        $this->assertEquals(150000.5, $v['x']);
        $this->assertEquals(-230000.2, $v['y']);
        $this->assertEquals(500.0, $v['z']);
        $this->assertSame('ScumKing', $v['ownerName']);
        $this->assertSame(101, $v['ownerDbId']);
    }

    public function test_parses_vehicle_without_owner_and_custom_name(): void
    {
        $raw = 'ID 99 | BPC_Bicycle | name: - | (0.0, 0.0, 0.0) | owner: -';
        $vehicles = VehicleParser::parseList($raw);

        $this->assertCount(1, $vehicles);
        $v = $vehicles[0];

        $this->assertSame(99, $v['entityId']);
        $this->assertSame('BPC_Bicycle', $v['asset']);
        $this->assertNull($v['customName']);
        $this->assertNull($v['ownerName']);
        $this->assertNull($v['ownerDbId']);
    }

    public function test_to_map_keys_by_entity_id(): void
    {
        $raw = "ID 10 | BPC_Car | (1, 2, 3) | owner: Bob (db id 1)\nID 20 | BPC_Boat | (4, 5, 6) | owner: Alice (db id 2)";
        $vehicles = VehicleParser::parseList($raw);
        $map = VehicleParser::toMap($vehicles);

        $this->assertArrayHasKey(10, $map);
        $this->assertArrayHasKey(20, $map);
        $this->assertSame('Bob', $map[10]['ownerName']);
        $this->assertSame('Alice', $map[20]['ownerName']);
    }
}
