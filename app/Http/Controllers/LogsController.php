<?php

namespace App\Http\Controllers;

use App\Services\Scum\RconService;
use App\Services\Security\AuditService;
use App\Support\TailReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogsController extends Controller
{
    public function __construct(
        private RconService $rcon,
        private AuditService $audit,
    ) {}

    public function sendChat(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'in:announce,say'],
        ]);

        $message = trim($validated['message']);
        $type = $validated['type'] ?? 'announce';
        $user = auth()->user();
        $senderName = $user ? $user->name : 'Администратор';

        $cmd = $type === 'say' ? "#say {$message}" : "#announce {$message}";
        $rconResult = $this->rcon->sendCommand($cmd);

        DB::table('chat_events')->insert([
            'steam_id' => null,
            'player_name' => "{$senderName} [Panel]",
            'channel' => $type === 'say' ? 'Global' : 'Announce',
            'message' => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->log(
            'chat_broadcast',
            'chat',
            "Отправка сообщения в игровой чат ({$type}): \"{$message}\"",
            ['command' => $cmd, 'rcon_status' => $rconResult['connected'] ?? false],
            'success',
            $senderName
        );

        return back()->with('success', 'Сообщение успешно отправлено в игровой чат сервера');
    }

    public function chat(): View
    {
        return $this->tableLog('chat_events', 'logs.chat', 'events');
    }

    public function events(): View
    {
        return $this->tableLog('log_events', 'logs.events', 'events');
    }

    public function rconCommands(): View
    {
        return $this->tableLog('rcon_command_logs', 'logs.rcon', 'commands');
    }

    public function audit(Request $request): View
    {
        $query = DB::table('audit_logs')->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', (string) $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('ip', 'like', $search);
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('logs.audit', [
            'logs' => $logs,
            'currentCategory' => (string) $request->input('category', ''),
            'currentStatus' => (string) $request->input('status', ''),
            'search' => (string) $request->input('search', ''),
        ]);
    }

    private function tableLog(string $table, string $view, string $var): View
    {
        $rows = DB::table($table)->orderByDesc('created_at')->orderByDesc('id')->paginate(30);
        $maxId = (int) DB::table($table)->max('id');

        return view($view, [
            $var => $rows,
            'maxId' => $maxId,
        ]);
    }

    public function system(): View
    {
        $logDir = storage_path('logs');
        $today = $logDir.DIRECTORY_SEPARATOR.'scum-'.date('Y-m-d').'.log';
        $legacy = $logDir.DIRECTORY_SEPARATOR.'scum.log';
        $path = is_file($today) ? $today : $legacy;

        $raw = TailReader::lines($path, 500);
        // TailReader возвращает строки от конца файла (новейшие в начале).
        // Для терминального вида (сверху старые -> внизу свежие) инвертируем:
        $raw = array_reverse($raw);

        $lines = [];
        foreach ($raw as $l) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[^]]+)\]\s+(\w+)\.\s+(.*)$/', trim($l), $m)) {
                $lines[] = ['time' => $m[1], 'level' => strtolower($m[2]), 'message' => $m[3]];
            } else {
                $lines[] = ['time' => '', 'level' => 'info', 'message' => trim($l)];
            }
        }

        return view('logs.system', ['lines' => $lines]);
    }

    public function admin(): View
    {
        $events = DB::table('log_events')
            ->where('type', 'admin')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        $maxId = (int) DB::table('log_events')->where('type', 'admin')->max('id');

        return view('logs.admin', [
            'events' => $events,
            'title' => 'Админ-действия',
            'empty' => 'Нет записей админ-лога.',
            'logType' => 'admin',
            'maxId' => $maxId,
        ]);
    }

    public function login(): View
    {
        return $this->byType('login', 'Входы и выходы', 'Нет записей о входе/выходе игроков.');
    }

    public function vehicle(): View
    {
        return $this->byType('vehicle', 'Транспорт', 'Нет событий транспорта.');
    }

    public function online(): View
    {
        return $this->byType('online', 'Онлайн', 'Нет записей счётчика онлайна.');
    }

    public function chest(): View
    {
        return $this->byType('chest', 'Сундуки', 'Нет событий владения сундуками.');
    }

    public function economy(): View
    {
        return $this->byType('economy', 'Экономика', 'Нет записей экономики (торговля).');
    }

    public function loot(): View
    {
        return $this->byType('loot', 'Лут', 'Нет записей о луте.');
    }

    public function violations(): View
    {
        return $this->byType('violations', 'Нарушения', 'Нет записей нарушений.');
    }

    public function famepoints(): View
    {
        return $this->byType('famepoints', 'Очки славы', 'Нет записей о начислении очков славы.');
    }

    public function gameplay(): View
    {
        return $this->byType('gameplay', 'Игровой процесс', 'Нет записей игрового процесса.');
    }

    public function kill(): View
    {
        return $this->byType('kill', 'Убийства', 'Нет записей об убийствах.');
    }

    public function quests(): View
    {
        return $this->byType('quests', 'Квесты', 'Нет записей о квестах.');
    }

    public function sentry(): View
    {
        return $this->byType('sentry', 'Турели', 'Нет записей о турелях.');
    }

    private function byType(string $type, string $title, string $empty): View
    {
        $events = DB::table('log_events')
            ->where('type', $type)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        $maxId = (int) DB::table('log_events')->where('type', $type)->max('id');

        return view('logs.type', [
            'events' => $events,
            'title' => $title,
            'empty' => $empty,
            'logType' => $type,
            'maxId' => $maxId,
        ]);
    }

    /* ===== AJAX: real-time polling endpoints ===== */

    public function pollChat(Request $request): JsonResponse
    {
        $lastId = (int) $request->query('last_id', 0);
        $rows = DB::table('chat_events')
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        return response()->json(['rows' => $rows]);
    }

    public function pollEvents(Request $request): JsonResponse
    {
        $lastId = (int) $request->query('last_id', 0);
        $rows = DB::table('log_events')
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        return response()->json(['rows' => $rows]);
    }

    public function pollType(Request $request, string $type): JsonResponse
    {
        $lastId = (int) $request->query('last_id', 0);
        $rows = DB::table('log_events')
            ->where('type', $type)
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        return response()->json(['rows' => $rows]);
    }

    public function pollRcon(Request $request): JsonResponse
    {
        $lastId = (int) $request->query('last_id', 0);
        $rows = DB::table('rcon_command_logs')
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        return response()->json(['rows' => $rows]);
    }

    public function pollSystem(Request $request): JsonResponse
    {
        $totalLines = (int) $request->query('total', 0);
        $logDir = storage_path('logs');
        $today = $logDir.DIRECTORY_SEPARATOR.'scum-'.date('Y-m-d').'.log';
        $legacy = $logDir.DIRECTORY_SEPARATOR.'scum.log';
        $path = is_file($today) ? $today : $legacy;

        if (! is_file($path)) {
            return response()->json(['lines' => [], 'total' => 0]);
        }

        $all = TailReader::lines($path, 500);
        $all = array_reverse($all);
        $newLines = array_slice($all, $totalLines);

        $lines = [];
        foreach ($newLines as $l) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[^]]+)\]\s+(\w+)\.\s+(.*)$/', trim($l), $m)) {
                $lines[] = ['time' => $m[1], 'level' => strtolower($m[2]), 'message' => $m[3]];
            } else {
                $lines[] = ['time' => '', 'level' => 'info', 'message' => trim($l)];
            }
        }

        return response()->json(['lines' => $lines, 'total' => count($all)]);
    }

    /**
     * Enterprise Server-Sent Events (SSE) log streaming endpoint.
     */
    public function stream(Request $request): StreamedResponse
    {
        $channel = (string) $request->query('channel', 'chat');
        $type = $request->query('type');
        $lastId = (int) $request->query('last_id', 0);

        return new StreamedResponse(function () use ($channel, $type, $lastId) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $currentId = $lastId;
            $start = time();
            $timeout = 25; // Reconnect interval for load balancers

            while (time() - $start < $timeout) {
                if (connection_aborted()) {
                    break;
                }

                $query = match ($channel) {
                    'chat' => DB::table('chat_events'),
                    'rcon' => DB::table('rcon_command_logs'),
                    default => DB::table('log_events')->when($type, fn ($q) => $q->where('type', $type)),
                };

                $rows = $query->where('id', '>', $currentId)
                    ->orderBy('id', 'asc')
                    ->limit(50)
                    ->get();

                if ($rows->isNotEmpty()) {
                    $currentId = (int) $rows->last()->id;
                    echo "event: log\n";
                    echo 'data: '.json_encode(['rows' => $rows, 'last_id' => $currentId])."\n\n";
                    flush();
                } else {
                    echo ": ping\n\n";
                    flush();
                }

                usleep(500000); // 500ms
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
