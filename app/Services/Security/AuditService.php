<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function log(
        string $action,
        string $category,
        string $description,
        ?array $payload = null,
        string $status = 'success',
        ?string $username = null,
        ?string $ip = null,
    ): void {
        try {
            $req = request();
            $username = $username ?? (session('2fa_pending_username') ?? (session()->has('scum_auth') ? 'admin' : 'guest'));
            $ip = $ip ?? ($req->ip() ?? '127.0.0.1');
            $ua = (string) $req->userAgent();

            DB::table('audit_logs')->insert([
                'action' => $action,
                'category' => $category,
                'username' => $username,
                'ip' => $ip,
                'user_agent' => $ua !== '' ? substr($ua, 0, 255) : null,
                'description' => $description,
                'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'status' => $status,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('scum')->warning("[Audit] Failed to log action {$action}: {$e->getMessage()}");
        }
    }
}
