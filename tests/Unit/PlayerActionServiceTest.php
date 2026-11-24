<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Player\PlayerActionService;
use App\Services\Scum\ChatCommandProcessor;
use App\Services\Scum\PluginService;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumDbService;
use App\Services\Scum\WargmService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class PlayerActionServiceTest extends TestCase
{
    private PlayerActionService $service;

    /** @var MockObject&RconService */
    private MockObject $rcon;

    protected function setUp(): void
    {
        parent::setUp();

        $plugins = $this->createMock(PluginService::class);
        $this->rcon = $this->createMock(RconService::class);
        $chat = $this->createMock(ChatCommandProcessor::class);
        $wargm = $this->createMock(WargmService::class);
        $scumDb = $this->createMock(ScumDbService::class);

        $this->service = new PlayerActionService(
            plugins: $plugins,
            rcon: $this->rcon,
            chat: $chat,
            wargm: $wargm,
            scumDb: $scumDb
        );
    }

    public function test_fails_when_steam_id_is_empty(): void
    {
        $result = $this->service->execute('give_money', '', ['amount' => 100]);

        $this->assertFalse($result['success']);
        $this->assertSame('Не указан SteamID', $result['message']);
    }

    public function test_fails_on_unknown_action(): void
    {
        $result = $this->service->execute('non_existent_action', '76561198000000000', []);

        $this->assertFalse($result['success']);
        $this->assertSame('Неизвестное действие', $result['message']);
    }

    public function test_fails_on_zero_currency_amount(): void
    {
        $result = $this->service->execute('give_money', '76561198000000000', ['amount' => 0]);

        $this->assertFalse($result['success']);
        $this->assertSame('Укажите сумму больше 0', $result['message']);
    }

    public function test_set_currency_fails_on_negative_amount(): void
    {
        $result = $this->service->execute('set_money', '76561198000000000', ['amount' => -50]);

        $this->assertFalse($result['success']);
        $this->assertSame('Сумма не может быть отрицательной', $result['message']);
    }

    public function test_fails_on_empty_announce_message(): void
    {
        $result = $this->service->execute('announce', '76561198000000000', ['message' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('Введите текст объявления', $result['message']);
    }

    public function test_teleport_to_me_returns_friendly_error(): void
    {
        $result = $this->service->execute('teleport_to_me', '76561198000000000', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Телепорт «ко мне» недоступен', $result['message']);
    }

    public function test_fails_spawn_item_without_item(): void
    {
        $result = $this->service->execute('spawn_item', '76561198000000000', ['item' => '', 'amount' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('Укажите предмет и количество', $result['message']);
    }

    public function test_fails_chest_full_without_item(): void
    {
        $result = $this->service->execute('chest_full', '76561198000000000', ['item' => '', 'count' => 10]);

        $this->assertFalse($result['success']);
        $this->assertSame('Ящик: не указан предмет', $result['message']);
    }

    public function test_fails_spawn_vehicle_without_vehicle_name(): void
    {
        $result = $this->service->execute('spawn_vehicle', '76561198000000000', ['vehicle' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('Укажите название ТС', $result['message']);
    }

    public function test_fails_teleport_without_coordinates(): void
    {
        $result = $this->service->execute('teleport_to', '76561198000000000', ['x' => 0, 'y' => 0, 'z' => 0]);

        $this->assertFalse($result['success']);
        $this->assertSame('Укажите координаты', $result['message']);
    }

    public function test_fails_teleport_to_player_without_target(): void
    {
        $result = $this->service->execute('teleport_to_player', '76561198000000000', ['target' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('Укажите целевого игрока (SteamID)', $result['message']);
    }

    public function test_fails_bring_vehicle_without_vehicle_id(): void
    {
        $result = $this->service->execute('bring_vehicle', '76561198000000000', ['vehicle_id' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('Не указан ID транспорта', $result['message']);
    }

    public function test_unstuck_sends_correct_rcon_command(): void
    {
        $this->rcon->expects($this->once())
            ->method('sendCommand')
            ->with('Unstuck 76561198000000000')
            ->willReturn(['success' => true, 'response' => 'Unstuck: successfully unstuck']);

        $result = $this->service->execute('unstuck', '76561198000000000', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Unstuck 76561198000000000', $result['message']);
    }

    public function test_upgrade_base_sends_correct_command(): void
    {
        $this->rcon->expects($this->once())
            ->method('sendCommand')
            ->with('UpgradeBase 76561198000000000 250')
            ->willReturn(['success' => true, 'response' => '[BaseUpgrade] Started']);

        $result = $this->service->execute('upgrade_base', '76561198000000000', ['delay' => 250]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('UpgradeBase 76561198000000000 250', $result['message']);
    }

    public function test_delete_active_quests_sends_correct_command(): void
    {
        $this->rcon->expects($this->once())
            ->method('sendCommand')
            ->with('DeleteActiveQuestsForUser 76561198000000000')
            ->willReturn(['success' => true, 'response' => 'deleted active_quest']);

        $result = $this->service->execute('delete_active_quests', '76561198000000000', []);

        $this->assertTrue($result['success']);
    }
}
