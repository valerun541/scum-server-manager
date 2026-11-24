<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ItemParser;
use PHPUnit\Framework\TestCase;

class ItemParserTest extends TestCase
{
    public function test_parses_empty_string_to_empty_array(): void
    {
        $this->assertSame([], ItemParser::parse(''));
        $this->assertSame([], ItemParser::parse("  \n  \r\n  "));
    }

    public function test_parses_quoted_id_and_name_format(): void
    {
        $input = '"Weapon_AK47" "Автомат Калашникова"';
        $expected = [
            ['id' => 'Weapon_AK47', 'name' => 'Автомат Калашникова', 'amount' => 1],
        ];

        $this->assertSame($expected, ItemParser::parse($input));
    }

    public function test_parses_id_comma_amount_format(): void
    {
        $input = 'Weapon_Ammunition_556, 120';
        $expected = [
            ['id' => 'Weapon_Ammunition_556', 'name' => 'Weapon_Ammunition_556', 'amount' => 120],
        ];

        $this->assertSame($expected, ItemParser::parse($input));
    }

    public function test_parses_single_id_format(): void
    {
        $input = 'Medical_Bandage';
        $expected = [
            ['id' => 'Medical_Bandage', 'name' => 'Medical_Bandage', 'amount' => 1],
        ];

        $this->assertSame($expected, ItemParser::parse($input));
    }

    public function test_parses_multiline_mixed_formats(): void
    {
        $input = <<<'TEXT'
"Weapon_M16A4" "Винтовка M16"
Weapon_Ammunition_556, 60

Medical_Bandage
TEXT;

        $result = ItemParser::parse($input);

        $this->assertCount(3, $result);
        $this->assertSame('Weapon_M16A4', $result[0]['id']);
        $this->assertSame('Винтовка M16', $result[0]['name']);
        $this->assertSame(60, $result[1]['amount']);
        $this->assertSame('Medical_Bandage', $result[2]['id']);
    }
}
