<?php

namespace Tests\Unit;

use App\Support\WhoisParser;
use PHPUnit\Framework\TestCase;

class WhoisParserTest extends TestCase
{
    public function test_parses_whois_output(): void
    {
        $raw = <<<'EOT'
Whois: CommanderAlex (SteamID 76561198012345678)
Fame: 1250.50 Money: 50000.00 Gold: 120.00
Kills: 45 Deaths: 10 K/D: 4.50 Puppets: 320 HS: 28
Vehicles owned: 2
Playtime: 142h 30m
Squad: RedDragons
EOT;

        $whois = WhoisParser::parse($raw);

        $this->assertSame('CommanderAlex', $whois['name']);
        $this->assertSame('76561198012345678', $whois['steamId']);
        $this->assertEquals(1250.50, $whois['fame']);
        $this->assertEquals(50000.00, $whois['money']);
        $this->assertEquals(120.00, $whois['gold']);
        $this->assertSame(45, $whois['kills']);
        $this->assertSame(10, $whois['deaths']);
        $this->assertEquals(4.50, $whois['kd']);
        $this->assertSame(320, $whois['puppetKills']);
        $this->assertSame(28, $whois['headshotKills']);
        $this->assertSame(2, $whois['vehiclesCount']);
        $this->assertSame('142h 30m', $whois['playtime']);
        $this->assertSame('RedDragons', $whois['squad']);
    }

    public function test_extracts_fame_and_name_convenience_methods(): void
    {
        $raw = "Whois: LoneWolf (SteamID 76561198111222333)\nFame: 345.80 Money: 1000";

        $this->assertEquals(345.80, WhoisParser::extractFame($raw));
        $this->assertSame('LoneWolf', WhoisParser::extractName($raw));
    }

    public function test_handles_empty_or_malformed_string(): void
    {
        $this->assertSame([], WhoisParser::parse(''));
        $this->assertEquals(0.0, WhoisParser::extractFame(''));
        $this->assertNull(WhoisParser::extractName(''));
    }
}
