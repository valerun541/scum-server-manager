<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scum\FtpLogWatcherService;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumDbQueryService;
use App\Services\Scum\ScumDbService;
use Tests\TestCase;

class ScumDbQueryServiceTest extends TestCase
{
    private ScumDbQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $scumDb = $this->createMock(ScumDbService::class);
        $rcon = $this->createMock(RconService::class);
        $ftp = $this->createMock(FtpLogWatcherService::class);

        $this->service = new ScumDbQueryService(
            scumDb: $scumDb,
            rcon: $rcon,
            ftp: $ftp
        );
    }

    public function test_empty_vehicles_array_returns_empty(): void
    {
        $result = $this->service->enrichVehiclesWithOwner([]);
        $this->assertSame([], $result);
    }

    public function test_attribute_order_has_canonical_four_keys(): void
    {
        $this->assertCount(4, ScumDbQueryService::RCON_ATTRIBUTE_ORDER);
        $this->assertSame(['strength', 'constitution', 'dexterity', 'intelligence'], ScumDbQueryService::RCON_ATTRIBUTE_ORDER);
    }

    public function test_skill_map_has_expected_game_skills(): void
    {
        $this->assertArrayHasKey('DrivingSkill', ScumDbQueryService::SKILL_RCON_MAP);
        $this->assertArrayHasKey('ArcherySkill', ScumDbQueryService::SKILL_RCON_MAP);
        $this->assertSame('Driving', ScumDbQueryService::SKILL_RCON_MAP['DrivingSkill']);
        $this->assertSame('Archery', ScumDbQueryService::SKILL_RCON_MAP['ArcherySkill']);
    }
}
