<?php

namespace App\Support;

/**
 * Читает последние N строк файла, не загружая его целиком в память.
 * Возвращает строки в обратном порядке (последняя первой) — как array_reverse(file(...)).
 */
class TailReader
{
    public static function lines(string $path, int $lines = 200): array
    {
        if (! is_file($path) || $lines <= 0) {
            return [];
        }

        $handle = @fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $result = [];
        $buffer = '';
        $chunkSize = 8192;
        $pos = filesize($path);

        while ($pos > 0 && count($result) < $lines) {
            $readSize = min($chunkSize, $pos);
            $pos -= $readSize;
            fseek($handle, $pos);
            $buffer = fread($handle, $readSize).$buffer;

            while (count($result) < $lines) {
                $nl = strrpos($buffer, "\n");
                if ($nl === false) {
                    break;
                }
                $line = substr($buffer, $nl + 1);
                if ($line !== '') {
                    $result[] = $line;
                }
                $buffer = substr($buffer, 0, $nl);
            }
        }

        if ($buffer !== '' && count($result) < $lines) {
            $result[] = $buffer;
        }

        fclose($handle);

        return array_slice($result, 0, $lines);
    }
}
