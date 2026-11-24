<?php

namespace App\Support;

/**
 * Разбирает многострочный список предметов из текстовой формы.
 * Форматы строк: "id" "название" | id,количество | id.
 */
class ItemParser
{
    public static function parse(string $text): array
    {
        $items = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if (! $line) {
                continue;
            }
            if (preg_match('/^"([^"]+)"\s+"([^"]+)"$/', $line, $m)) {
                $items[] = ['id' => $m[1], 'name' => $m[2], 'amount' => 1];
            } elseif (preg_match('/^([^:,\s]+)\s*,\s*(\d+)\s*$/', $line, $m)) {
                $items[] = ['id' => $m[1], 'name' => $m[1], 'amount' => (int) $m[2]];
            } elseif (preg_match('/^([^:,\s]+)\s*$/', $line, $m)) {
                $items[] = ['id' => $m[1], 'name' => $m[1], 'amount' => 1];
            }
        }

        return $items;
    }
}
