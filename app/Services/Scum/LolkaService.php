<?php

namespace App\Services\Scum;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lolka webhook уведомления — замена Discord (порт discordWebhook.ts).
 * API Lolka совместим с Discord-форматом: POST https://lolka.app/api/webhooks/{id}/{token}
 * с полями content / embeds (title, description, color, timestamp...).
 */
class LolkaService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Всегда читаем свежий конфиг из SettingsService — LolkaService живёт в
     * долгоживущем демоне (scum:rcon/scum:watch), и кэширование конфига в
     * конструкторе приводило к тому, что добавленный в панели webhook не
     * подхватывался до перезапуска демона.
     */
    public function getConfig(): array
    {
        $cfg = $this->settings->getAppConfig()['lolka'] ?? [];
        $cfg['enabled'] = true;
        if (! empty($cfg['webhooks']) && is_array($cfg['webhooks'])) {
            foreach ($cfg['webhooks'] as $wh) {
                if (! empty($wh['key']) && ! empty($wh['url'])) {
                    $cfg[$wh['key']] = $wh['url'];
                }
            }
        }

        return $cfg;
    }

    private function send(string $webhookUrl, string $content, ?string $title = null): bool
    {
        if (! $webhookUrl) {
            return false;
        }
        $payload = ['content' => $content, 'allowed_mentions' => ['parse' => []]];
        if ($title) {
            $payload = ['embeds' => [[
                'title' => $title,
                'description' => $content,
                'color' => 0x00FF00,
                'timestamp' => now()->toISOString(),
            ]]];
        }

        return $this->postJson($webhookUrl, $payload);
    }

    private function postJson(string $webhookUrl, array $payload): bool
    {
        try {
            Http::timeout(10)
                ->retry(2, 100, fn ($e) => $e instanceof ConnectionException)
                ->post($webhookUrl, $payload)
                ->throw();

            return true;
        } catch (\Throwable $e) {
            Log::channel('scum')->error("[Lolka] send error: {$e->getMessage()}");

            return false;
        }
    }

    public function sendAdminLog(string $message): bool
    {
        return $this->send($this->getConfig()['adminLogWebhook'] ?? '', $message, 'Журнал администратора');
    }

    public function sendChatMessage(string $player, string $message): bool
    {
        return $this->send($this->getConfig()['chatWebhook'] ?? '', "**{$player}**: {$message}");
    }

    public function sendVehicleEvent(string $event): bool
    {
        return $this->send($this->getConfig()['vehicleWebhook'] ?? '', $event, 'Транспорт');
    }

    public function sendLoginEvent(string $player, string $steamId, string $type = 'join'): bool
    {
        $webhook = $this->getConfig()['loginWebhook'] ?? '';
        if (! $webhook) {
            return false;
        }
        $emoji = $type === 'join' ? '🟢' : '🔴';
        $title = $type === 'join' ? 'Игрок зашёл на сервер' : 'Игрок покинул сервер';
        $color = $type === 'join' ? 0x3FB950 : 0xF85149;
        $payload = ['embeds' => [[
            'title' => "{$emoji} {$title}",
            'description' => implode("\n", array_filter([
                "**Игрок:** {$player}",
                $steamId ? "**Steam ID:** `{$steamId}`" : '',
                '**Время:** '.now()->format('d.m.Y H:i:s'),
            ])),
            'color' => $color,
            'footer' => ['text' => 'SCUM Server Manager'],
            'timestamp' => now()->toISOString(),
        ]]];

        return $this->postJson($webhook, $payload);
    }

    public function sendStatusUpdate(string $status): bool
    {
        $webhook = $this->getConfig()['serverStatusWebhook'] ?? '';
        if (! $webhook) {
            return false;
        }
        $map = [
            'started' => ['label' => 'Запущен', 'emoji' => '🟢', 'color' => 0x3FB950],
            'stopped' => ['label' => 'Остановлен', 'emoji' => '🔴', 'color' => 0xF85149],
            'restarted' => ['label' => 'Перезапущен', 'emoji' => '🔄', 'color' => 0xD29922],
            'unknown' => ['label' => 'Неизвестно', 'emoji' => '❓', 'color' => 0x8B949E],
        ];
        $s = $map[$status] ?? $map['unknown'];
        $payload = ['embeds' => [[
            'title' => "{$s['emoji']} Статус сервера",
            'description' => implode("\n", [
                "**Состояние:** {$s['emoji']} {$s['label']}",
                '**Время:** '.now()->format('d.m.Y H:i:s'),
            ]),
            'color' => $s['color'],
            'footer' => ['text' => 'SCUM Server Manager'],
            'timestamp' => now()->toISOString(),
        ]]];

        return $this->postJson($webhook, $payload);
    }

    public function test(string $webhookUrl): bool
    {
        return $this->send($webhookUrl, 'Вебхук работает!', 'Проверка');
    }
}
