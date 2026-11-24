<?php

namespace Tests\Unit;

use App\Services\Scum\AutomationService;
use App\Services\Scum\LolkaService;
use App\Services\Scum\RconService;
use App\Services\Scum\WargmService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutomationServiceTest extends TestCase
{
    private AutomationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $rcon = $this->createMock(RconService::class);
        $lolka = $this->createMock(LolkaService::class);
        $wargm = $this->createMock(WargmService::class);

        $this->service = new AutomationService($rcon, $lolka, $wargm);
        Cache::forget('automation_workflows');
    }

    public function test_save_and_get_workflow(): void
    {
        $id = 'wf-test-'.uniqid();
        $payload = [
            'id' => $id,
            'name' => 'Auto Welcome Workflow',
            'enabled' => true,
            'trigger' => [
                'type' => 'chat_command',
                'config' => [
                    'trigger' => '!starter',
                    'aliases' => ['!start', '!bonus'],
                ],
            ],
            'nodes' => [
                [
                    'id' => 'node-1',
                    'type' => 'rcon_command',
                    'config' => ['command' => 'Announce Welcome'],
                ],
            ],
            'connections' => [],
            'stats' => ['executions' => 0, 'successes' => 0],
        ];

        $this->service->saveWorkflow($payload);

        $fetched = $this->service->getWorkflow($id);
        $this->assertNotNull($fetched);
        $this->assertSame($id, $fetched['id']);
        $this->assertSame('Auto Welcome Workflow', $fetched['name']);
        $this->assertTrue($fetched['enabled']);
        $this->assertSame('chat_command', $fetched['trigger']['type']);
        $this->assertSame('!starter', $fetched['trigger']['config']['trigger']);
        $this->assertCount(1, $fetched['nodes']);

        $all = $this->service->getWorkflows();
        $this->assertNotEmpty($all);

        $deleted = $this->service->deleteWorkflow($id);
        $this->assertTrue($deleted);

        $afterDelete = $this->service->getWorkflow($id);
        $this->assertNull($afterDelete);
    }

    public function test_delete_workflow(): void
    {
        $id = 'wf-del-'.uniqid();
        $this->service->saveWorkflow([
            'id' => $id,
            'name' => 'Workflow To Delete',
            'enabled' => false,
            'trigger' => ['type' => 'event', 'config' => ['event' => 'login']],
            'nodes' => [],
            'connections' => [],
        ]);

        $this->assertNotNull($this->service->getWorkflow($id));
        $this->assertTrue($this->service->deleteWorkflow($id));
        $this->assertNull($this->service->getWorkflow($id));
    }
}
