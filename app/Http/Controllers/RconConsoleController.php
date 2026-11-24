<?php

namespace App\Http\Controllers;

use App\Http\Requests\RconCommandRequest;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumJobService;
use App\Services\Scum\SettingsService;
use App\Services\Security\AuditService;
use Illuminate\Support\Facades\Log;

class RconConsoleController extends Controller
{
    public function __construct(
        private RconService $rcon,
        private SettingsService $settings,
        private ScumJobService $jobs,
        private AuditService $audit,
    ) {}

    public function index()
    {
        return view('rcon.console', [
            'status' => $this->rcon->status(),
            'history' => $this->rcon->getCommandHistory(10),
            'config' => $this->settings->getAppConfig()['rcon'] ?? [],
        ]);
    }

    /**
     * Принять команду из веб-консоли и поставить в очередь scum_jobs.
     * Исполняет RCON-демон (единственный владелец сокета), результат читаем через jobStatus().
     */
    public function send(RconCommandRequest $request)
    {
        $command = trim((string) $request->input('command', ''));
        if ($command === '') {
            return response()->json(['success' => false, 'error' => 'Введите команду'], 422);
        }
        $status = $this->rcon->status();
        if (! $status['connected']) {
            $this->audit->log('rcon.command.failed', 'rcon', "Попытка отправки команды при отключённом RCON: {$command}", ['command' => $command], 'failed');

            return response()->json(['success' => false, 'error' => 'RCON не подключён'], 409);
        }
        try {
            $jobId = $this->jobs->enqueue('rcon_command', ['command' => $command], priority: 10);
            Log::channel('scum')->info('[rcon-console] enqueued #'.$jobId.': '.$command);

            $this->audit->log('rcon.command.sent', 'rcon', "Команда RCON отправлена в очередь: {$command}", [
                'command' => $command,
                'job_id' => $jobId,
            ], 'success');
        } catch (\Throwable $e) {
            Log::channel('scum')->error('[rcon-console] enqueue: '.$e->getMessage());

            $this->audit->log('rcon.command.error', 'rcon', "Ошибка постановки команды RCON в очередь: {$command}", [
                'command' => $command,
                'error' => $e->getMessage(),
            ], 'failed');

            return response()->json(['success' => false, 'error' => 'Не удалось поставить команду в очередь'], 500);
        }

        return response()->json(['success' => true, 'job_id' => $jobId]);
    }

    /**
     * Получить статус задачи по id. Если статус done/error — вернуть результат.
     */
    public function jobStatus(int $id)
    {
        $job = $this->jobs->find($id);
        if (! $job) {
            return response()->json(['success' => false, 'error' => 'Задача не найдена'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $job['status'],
            'response' => $job['response'] ?? '',
            'error' => $job['error'] ?? null,
            'type' => $job['type'] ?? '',
        ]);
    }

    public function connect()
    {
        $config = $this->settings->getAppConfig()['rcon'] ?? [];
        if (empty($config['host'])) {
            return back()->with('error', 'Укажите адрес RCON в настройках');
        }
        $result = $this->rcon->connect($config['host'], (int) $config['port'], $config['password'] ?? '');

        $this->audit->log('rcon.connect', 'rcon', $result['success'] ? 'RCON успешно подключён' : 'Ошибка подключения RCON', [
            'host' => $config['host'],
            'port' => (int) $config['port'],
            'error' => $result['error'] ?? null,
        ], $result['success'] ? 'success' : 'failed');

        return back()->with($result['success'] ? 'success' : 'error', $result['success'] ? 'RCON подключён' : ($result['error'] ?? 'Ошибка подключения'));
    }

    public function disconnect()
    {
        $this->rcon->disconnect();

        $this->audit->log('rcon.disconnect', 'rcon', 'RCON отключён пользователем', null, 'success');

        return back()->with('success', 'RCON отключён');
    }
}
