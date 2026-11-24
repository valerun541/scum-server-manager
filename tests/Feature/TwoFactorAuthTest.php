<?php

namespace Tests\Feature;

use App\Services\Security\TotpService;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    public function test_totp_service_generates_and_verifies_codes(): void
    {
        $service = new TotpService;
        $secret = $service->generateSecret();

        $this->assertNotEmpty($secret);
        $this->assertSame(16, strlen($secret));

        $code = $service->getCode($secret);
        $this->assertSame(6, strlen($code));

        $this->assertTrue($service->verify($secret, $code));
        $this->assertFalse($service->verify($secret, '000000'));
    }

    public function test_health_check_endpoint_returns_healthy_json(): void
    {
        $response = $this->get('/api/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'app',
            'checks' => [
                'database',
                'disk',
                'memory',
                'daemons',
            ],
            'metrics' => [
                'uptime',
                'cpu_load',
            ],
        ]);
    }
}
