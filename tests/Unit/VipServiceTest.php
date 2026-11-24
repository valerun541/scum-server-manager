<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scum\PluginService;
use App\Services\Scum\SettingsService;
use App\Services\Scum\VipService;
use Tests\TestCase;

class VipServiceTest extends TestCase
{
    public function test_add_and_remove_vip_player(): void
    {
        $plugins = $this->createMock(PluginService::class);
        $settings = $this->createMock(SettingsService::class);

        $savedData = [];
        $settings->method('getBotData')->willReturn([
            'vip' => ['players' => []],
        ]);
        $settings->method('saveBotData')->willReturnCallback(function ($data) use (&$savedData) {
            $savedData = $data;
        });

        $vipService = new VipService($plugins, $settings);
        $vipService->addPlayer('76561198000000001', 15);

        $this->assertNotEmpty($savedData);
        $this->assertSame('76561198000000001', $savedData['vip']['players'][0]['steamId']);
        $this->assertGreaterThan(time(), $savedData['vip']['players'][0]['expiresAt']);
    }
}
