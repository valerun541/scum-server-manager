<?php

namespace Tests\Unit;

use App\Services\Scum\InsuranceService;
use Tests\TestCase;

class InsuranceServiceTest extends TestCase
{
    protected InsuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InsuranceService::class);
        $this->service->saveSettings([
            'enabled' => true,
            'duration_days' => 30,
            'default_claims' => 2,
        ]);
    }

    public function test_grant_insurance_creates_policy(): void
    {
        $steamId = '76561198000000001';
        $playerName = 'TestSurvivor';

        $result = $this->service->grantInsurance($steamId, $playerName, 'PURCHASE_123', 2, 30);
        $this->assertTrue($result['success'] ?? false);
        $this->assertArrayHasKey('insurance_id', $result);

        $policy = $this->service->getPlayerPolicy($steamId);
        $this->assertNotNull($policy);
        $this->assertEquals($steamId, $policy['steam_id']);
        $this->assertEquals('pending', $policy['status']);
        $this->assertEquals(2, $policy['claims_total']);
    }

    public function test_get_stats_returns_numeric_counts(): void
    {
        $stats = $this->service->getStats();
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('destroyed', $stats);
        $this->assertArrayHasKey('claimed', $stats);
    }
}
