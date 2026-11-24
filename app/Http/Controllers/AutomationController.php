<?php

namespace App\Http\Controllers;

use App\Http\Requests\AutomationSaveRequest;
use App\Http\Requests\AutomationTestRequest;
use App\Services\Scum\AutomationService;
use Illuminate\Support\Str;

class AutomationController extends Controller
{
    private const TRIGGER_TYPES = ['chat_command', 'event', 'schedule', 'webhook'];

    private const NODE_TYPES = ['rcon_command', 'lolka_webhook', 'condition', 'delay', 'variable', 'log', 'http_request', 'for_each', 'comment'];

    private const WEBHOOK_KEYS = ['admin', 'chat', 'vehicle', 'login', 'status'];

    public function __construct(private AutomationService $automation) {}

    public function index()
    {
        return view('automation.index', [
            'workflows' => $this->automation->getWorkflows(),
            'triggerTypes' => self::TRIGGER_TYPES,
        ]);
    }

    public function create()
    {
        return view('automation.edit', [
            'workflow' => [
                'id' => (string) Str::uuid(),
                'name' => 'Новый воркфлоу',
                'enabled' => true,
                'trigger' => ['type' => 'chat_command', 'config' => ['trigger' => '', 'cooldownSeconds' => 0]],
                'nodes' => [],
                'connections' => [],
            ],
            'isNew' => true,
            'triggerTypes' => self::TRIGGER_TYPES,
            'nodeTypes' => self::NODE_TYPES,
            'webhookKeys' => self::WEBHOOK_KEYS,
        ]);
    }

    public function edit(string $id)
    {
        $workflow = $this->automation->getWorkflow($id);
        if (! $workflow) {
            return redirect()->route('automation.index')->with('error', 'Воркфлоу не найден');
        }

        return view('automation.edit', [
            'workflow' => $workflow,
            'isNew' => false,
            'triggerTypes' => self::TRIGGER_TYPES,
            'nodeTypes' => self::NODE_TYPES,
            'webhookKeys' => self::WEBHOOK_KEYS,
        ]);
    }

    public function save(AutomationSaveRequest $request, ?string $id = null)
    {
        $data = $request->validated();

        $triggerConfig = [];
        switch ($data['trigger_type']) {
            case 'chat_command':
                $triggerConfig = [
                    'trigger' => $request->input('trigger_chat_trigger', ''),
                    'aliases' => array_values(array_filter(array_map('trim', explode(',', $request->input('trigger_chat_aliases', ''))))),
                    'cooldownSeconds' => (int) $request->input('trigger_chat_cooldown', 0),
                ];
                break;
            case 'event':
                $triggerConfig = ['event' => $request->input('trigger_event_event', 'player_join')];
                break;
            case 'schedule':
                $triggerConfig = [
                    'type' => $request->has('trigger_schedule_interval') ? 'interval' : 'manual',
                    'intervalMinutes' => (int) $request->input('trigger_schedule_minutes', 60),
                ];
                break;
            case 'webhook':
                $triggerConfig = ['path' => $request->input('trigger_webhook_path', '')];
                break;
        }

        $nodes = json_decode($request->input('nodes_json', '[]'), true);
        if (! is_array($nodes)) {
            $nodes = [];
        }

        // Собираем подключения из нод (fromNode/fromPort/toNode/toPort в данных)
        $connections = [];
        $nodeId = $request->input('workflow_id', (string) Str::uuid());
        if ($request->input('connections_json') !== null) {
            $connections = json_decode($request->input('connections_json', '[]'), true) ?: [];
        } else {
            foreach ($nodes as $n) {
                if (! empty($n['data']['fromNode'])) {
                    $connections[] = [
                        'fromNodeId' => $n['data']['fromNode'],
                        'fromPort' => $n['data']['fromPort'] ?? 'main',
                        'toNodeId' => $n['data']['toNode'] ?? $nodeId,
                        'toPort' => $n['data']['toPort'] ?? 'main',
                    ];
                }
            }
        }

        $this->automation->saveWorkflow([
            'id' => $nodeId,
            'name' => $data['name'],
            'enabled' => $request->has('enabled') && $request->input('enabled') === '1',
            'trigger' => ['type' => $data['trigger_type'], 'config' => $triggerConfig],
            'nodes' => $nodes,
            'connections' => $connections,
        ]);

        return redirect()->route('automation.edit', $nodeId)->with('success', 'Воркфлоу сохранён');
    }

    public function delete(string $id)
    {
        $this->automation->deleteWorkflow($id);

        return redirect()->route('automation.index')->with('success', 'Воркфлоу удалён');
    }

    public function toggle(string $id)
    {
        $wf = $this->automation->getWorkflow($id);
        if (! $wf) {
            return redirect()->route('automation.index')->with('error', 'Воркфлоу не найден');
        }
        $wf['enabled'] = ! $wf['enabled'];
        $this->automation->saveWorkflow($wf);

        return back()->with('success', $wf['enabled'] ? 'Воркфлоу включён' : 'Воркфлоу отключён');
    }

    public function test(AutomationTestRequest $request, string $id)
    {
        $wf = $this->automation->getWorkflow($id);
        if (! $wf) {
            return redirect()->route('automation.index')->with('error', 'Воркфлоу не найден');
        }
        $result = $this->automation->testWorkflow($wf, [
            'steamId' => (string) $request->input('steam_id', '76561198000000000'),
            'playerName' => (string) $request->input('player_name', 'TestPlayer'),
            'message' => (string) $request->input('message', ''),
            'args' => array_values(array_filter(explode(' ', (string) $request->input('message', '')))),
        ]);
        $msg = $result['success'] ? 'Тест пройден успешно' : ('Ошибка: '.($result['error'] ?? 'unknown'));
        if (! empty($result['rconCommands'])) {
            $msg .= ' — RCON: '.implode(' | ', array_map(fn ($c) => trim($c), $result['rconCommands']));
            $hasEmpty = array_filter($result['rconCommands'], fn ($c) => preg_match('/^SendChat\s+\d+\s+"\s*"$/', trim($c)));
            if ($hasEmpty) {
                $msg .= ' ⚠ (SendChat с пустым текстом — введите сообщение в поле)';
            }
        }

        return back()->with('success', $msg);
    }

    public function resetStats(string $id)
    {
        $wf = $this->automation->getWorkflow($id);
        if (! $wf) {
            return redirect()->route('automation.index')->with('error', 'Воркфлоу не найден');
        }
        $stats = $wf['stats'] ?? ['runs' => 0, 'lastRun' => '', 'errors' => 0];
        $stats['errors'] = 0;
        $wf['stats'] = $stats;
        $this->automation->saveWorkflow($wf);

        return back()->with('success', 'Счётчик ошибок обнулён');
    }

    public function runSchedules()
    {
        $this->automation->processSchedules();

        return back()->with('success', 'Расписания проверены');
    }
}
