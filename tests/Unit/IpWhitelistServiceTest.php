<?php

namespace Tests\Unit;

use App\Services\Security\IpWhitelistService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IpWhitelistServiceTest extends TestCase
{
    protected IpWhitelistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = app(IpWhitelistService::class);
    }

    public function test_validates_ip_and_cidr_formats(): void
    {
        $this->assertTrue($this->service->validateIpOrCidr('127.0.0.1'));
        $this->assertTrue($this->service->validateIpOrCidr('192.168.1.100'));
        $this->assertTrue($this->service->validateIpOrCidr('10.0.0.0/8'));
        $this->assertTrue($this->service->validateIpOrCidr('172.16.0.0/16'));
        $this->assertTrue($this->service->validateIpOrCidr('::1'));

        $this->assertFalse($this->service->validateIpOrCidr(''));
        $this->assertFalse($this->service->validateIpOrCidr('invalid-ip'));
        $this->assertFalse($this->service->validateIpOrCidr('999.999.999.999'));
        $this->assertFalse($this->service->validateIpOrCidr('192.168.1.0/35'));
    }

    public function test_matches_exact_ips(): void
    {
        $this->assertTrue($this->service->matchIp('192.168.1.50', '192.168.1.50'));
        $this->assertFalse($this->service->matchIp('192.168.1.51', '192.168.1.50'));
    }

    public function test_matches_cidr_subnets(): void
    {
        $this->assertTrue($this->service->matchIp('192.168.1.15', '192.168.1.0/24'));
        $this->assertTrue($this->service->matchIp('192.168.1.254', '192.168.1.0/24'));
        $this->assertFalse($this->service->matchIp('192.168.2.1', '192.168.1.0/24'));

        $this->assertTrue($this->service->matchIp('10.20.30.40', '10.0.0.0/8'));
        $this->assertFalse($this->service->matchIp('11.0.0.1', '10.0.0.0/8'));
    }

    public function test_add_and_remove_entries(): void
    {
        $testIp = '203.0.113.'.rand(10, 240);

        $result = $this->service->addEntry($testIp, 'Unit Test Label');
        $this->assertTrue($result['ok'] ?? false, $result['message'] ?? 'No message');

        $allowed = $this->service->getAllowedEntries();
        $hasIp = false;
        foreach ($allowed as $entry) {
            if ($entry['ip'] === $testIp) {
                $hasIp = true;
                break;
            }
        }
        $this->assertTrue($hasIp);

        $removed = $this->service->removeEntry($testIp);
        $this->assertTrue($removed['ok'] ?? false);
    }
}
