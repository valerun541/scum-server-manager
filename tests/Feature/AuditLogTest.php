<?php

namespace Tests\Feature;

use App\Services\Security\AuditService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    public function test_audit_service_records_entry_in_database(): void
    {
        $service = app(AuditService::class);
        $service->log(
            action: 'test.action',
            category: 'test',
            description: 'Тестовое действие аудита',
            payload: ['key' => 'value', 'num' => 42],
            status: 'success',
            username: 'test_admin',
            ip: '192.168.1.100'
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.action',
            'category' => 'test',
            'username' => 'test_admin',
            'ip' => '192.168.1.100',
            'status' => 'success',
        ]);
    }

    public function test_audit_logs_page_requires_authentication(): void
    {
        $response = $this->get(route('logs.audit'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_audit_logs(): void
    {
        DB::table('audit_logs')->insert([
            'action' => 'system.startup',
            'category' => 'system',
            'username' => 'system',
            'ip' => '127.0.0.1',
            'description' => 'Система запущена',
            'payload' => json_encode(['version' => '2.0']),
            'status' => 'success',
            'created_at' => now(),
        ]);

        $response = $this->withSession(['scum_auth' => true])->get(route('logs.audit'));

        $response->assertStatus(200);
        $response->assertSee('Журнал аудита действий');
        $response->assertSee('system.startup');
    }

    public function test_audit_logs_can_be_filtered_by_category(): void
    {
        DB::table('audit_logs')->insert([
            [
                'action' => 'auth.test',
                'category' => 'auth',
                'username' => 'admin',
                'ip' => '127.0.0.1',
                'description' => 'Auth test entry',
                'payload' => null,
                'status' => 'success',
                'created_at' => now(),
            ],
            [
                'action' => 'rcon.test',
                'category' => 'rcon',
                'username' => 'admin',
                'ip' => '127.0.0.1',
                'description' => 'Rcon test entry',
                'payload' => null,
                'status' => 'success',
                'created_at' => now(),
            ],
        ]);

        $response = $this->withSession(['scum_auth' => true])->get(route('logs.audit', ['category' => 'auth']));

        $response->assertStatus(200);
        $response->assertSee('auth.test');
        $response->assertDontSee('rcon.test');
    }
}
