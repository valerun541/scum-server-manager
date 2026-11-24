<?php

namespace Tests\Unit;

use App\Services\Scum\RconService;
use App\Services\Scum\SettingsService;
use App\Services\Scum\WargmService;
use Tests\TestCase;

class WargmServiceTest extends TestCase
{
    private WargmService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $rcon = $this->createMock(RconService::class);
        $settings = $this->createMock(SettingsService::class);
        $this->service = new WargmService($rcon, $settings);
    }

    public function test_get_and_save_settings(): void
    {
        $payload = [
            'apiUrl' => 'https://api.wargm.ru/v1/',
            'shopId' => 'test-shop-123',
            'apiKey' => 'secret-test-key',
            'timeout' => 45,
            'duplicateCheckMinutes' => 10,
            'commandCooldownSeconds' => 60,
            'maxItemsPerCard' => 25,
        ];

        $this->service->saveSettings($payload);
        $saved = $this->service->getSettings();

        $this->assertSame('https://api.wargm.ru/v1/', $saved['apiUrl']);
        $this->assertSame('test-shop-123', $saved['shopId']);
        $this->assertSame('secret-test-key', $saved['apiKey']);
        $this->assertSame(45, $saved['timeout']);
        $this->assertSame(10, $saved['duplicateCheckMinutes']);
        $this->assertSame(60, $saved['commandCooldownSeconds']);
        $this->assertSame(25, $saved['maxItemsPerCard']);
    }

    public function test_save_get_and_delete_card(): void
    {
        $cardData = [
            'name' => 'Mega Pack Enterprise',
            'shopItemId' => 'wargm-999',
            'enabled' => true,
            'items' => [
                [
                    'type' => 'item',
                    'data' => ['itemName' => 'Weapon_AK47', 'amount' => 1],
                ],
                [
                    'type' => 'money',
                    'data' => ['amount' => 5000],
                ],
            ],
        ];

        $cardId = $this->service->saveCard($cardData);
        $this->assertNotNull($cardId);
        $this->assertIsInt($cardId);

        $card = $this->service->getCard($cardId);
        $this->assertNotNull($card);
        $this->assertSame('Mega Pack Enterprise', $card['name']);
        $this->assertSame('wargm-999', $card['shopItemId']);
        $this->assertTrue($card['enabled']);
        $this->assertCount(2, $card['items']);
        $this->assertSame('item', $card['items'][0]['type']);
        $this->assertSame('Weapon_AK47', $card['items'][0]['data']['itemName']);
        $this->assertSame('money', $card['items'][1]['type']);
        $this->assertSame(5000, $card['items'][1]['data']['amount']);

        $allCards = $this->service->getCards();
        $this->assertNotEmpty($allCards);

        $deleted = $this->service->deleteCard($cardId);
        $this->assertTrue($deleted);

        $cardAfterDelete = $this->service->getCard($cardId);
        $this->assertNull($cardAfterDelete);
    }

    public function test_duplicate_card(): void
    {
        $cardData = [
            'name' => 'Original Kit',
            'shopItemId' => 'orig-101',
            'enabled' => true,
            'items' => [
                [
                    'type' => 'vehicle',
                    'data' => ['vehicleName' => 'BP_Laika'],
                ],
            ],
        ];

        $origId = $this->service->saveCard($cardData);
        $this->assertNotNull($origId);

        $dupId = $this->service->duplicateCard($origId);
        $this->assertNotNull($dupId);
        $this->assertNotEquals($origId, $dupId);

        $dupCard = $this->service->getCard($dupId);
        $this->assertSame('Original Kit (copy)', $dupCard['name']);
        $this->assertFalse($dupCard['enabled']);
        $this->assertCount(1, $dupCard['items']);
        $this->assertSame('BP_Laika', $dupCard['items'][0]['data']['vehicleName']);

        // Cleanup
        $this->service->deleteCard($origId);
        $this->service->deleteCard($dupId);
    }

    public function test_get_card_returns_null_for_non_existent(): void
    {
        $res = $this->service->getCard(9999999);
        $this->assertNull($res);
    }
}
