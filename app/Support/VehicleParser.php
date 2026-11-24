<?php

namespace App\Support;

class VehicleParser
{
    /**
     * Парсит текстовый вывод RCON-команды ListSpawnedVehicles.
     *
     * @return array<int, array{
     *     entityId: int|null,
     *     asset: string|null,
     *     customName: string|null,
     *     x: float|null,
     *     y: float|null,
     *     z: float|null,
     *     ownerName: string|null,
     *     ownerDbId: int|null
     * }>
     */
    public static function parseList(string $output): array
    {
        $vehicles = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $v = [
                'entityId' => null,
                'asset' => null,
                'customName' => null,
                'x' => null,
                'y' => null,
                'z' => null,
                'ownerName' => null,
                'ownerDbId' => null,
            ];

            // ID 12345
            if (preg_match('/ID\s+(\d+)/i', $line, $m)) {
                $v['entityId'] = (int) $m[1];
            }

            // Asset name: | BPC_WolfWagon |
            if (preg_match('/\|\s*([A-Za-z0-9_]+)\s*\|/', $line, $m)) {
                $v['asset'] = trim($m[1]);
            }

            // Custom name: | name: MyCar |
            if (preg_match('/\|\s*name:\s*([^|]+?)\s*\|/iu', $line, $m)) {
                $cn = trim($m[1]);
                if ($cn !== '-' && $cn !== '') {
                    $v['customName'] = $cn;
                }
            }

            // Coordinates: (123.45, 678.90, 12.34)
            if (preg_match('/\(?([\d.+-]+),\s*([\d.+-]+),\s*([\d.+-]+)\)?/', $line, $m)) {
                $v['x'] = (float) $m[1];
                $v['y'] = (float) $m[2];
                $v['z'] = (float) $m[3];
            }

            // Owner: | owner: PlayerName (db id 42)
            if (preg_match('/\|\s*owner:\s*(.+?)(?:\s*\(db id (\d+)\))?\s*$/i', $line, $m)) {
                $raw = trim($m[1]);
                if (! in_array($raw, ['-', 'None', '', 'N/A'], true)) {
                    $v['ownerName'] = $raw;
                }
                if (isset($m[2])) {
                    $v['ownerDbId'] = (int) $m[2];
                }
            }

            if ($v['entityId'] === null && $v['asset'] === null) {
                continue;
            }

            $vehicles[] = $v;
        }

        return $vehicles;
    }

    /**
     * Преобразует список в ассоциативную карту по entityId.
     *
     * @param  array<int, array<string, mixed>>  $vehicles
     * @return array<int, array<string, mixed>>
     */
    public static function toMap(array $vehicles): array
    {
        $map = [];
        foreach ($vehicles as $v) {
            if (isset($v['entityId']) && is_numeric($v['entityId'])) {
                $map[(int) $v['entityId']] = $v;
            }
        }

        return $map;
    }
}
