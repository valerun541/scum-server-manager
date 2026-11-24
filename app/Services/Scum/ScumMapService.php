<?php

namespace App\Services\Scum;

/**
 * Карта SCUM: система координат и секторов — порт из webPanel.ts
 */
class ScumMapService
{
    public const MIN_X = -905000;

    public const MAX_X = 619000;

    public const MIN_Y = -905000;

    public const MAX_Y = 619000;

    public const MAP_SIZE = 4096;

    public const CALIBRATION_POINTS_COUNT = 125;

    private const ROW_LABELS = ['D', 'C', 'B', 'A', 'Z'];

    private const COL_LABELS = ['4', '3', '2', '1', '0'];

    private const OFFSETS = [[0.5, 0.5], [0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]];

    public function span(): int
    {
        return self::MAX_X - self::MIN_X;
    }

    /** Мировые координаты → пиксели карты (4096×4096) */
    public function worldToPixel(float $x, float $y): array
    {
        $px = 4096 - (($x - self::MIN_X) / $this->span()) * 4096;
        $py = ((self::MAX_Y - $y) / $this->span()) * 4096;

        return ['x' => $px, 'y' => $py];
    }

    /** Пиксели → мировые координаты */
    public function pixelToWorld(float $px, float $py): array
    {
        $x = self::MAX_X - ($px / 4096) * $this->span();
        $y = self::MIN_Y + ($py / 4096) * $this->span();

        return ['x' => $x, 'y' => $y];
    }

    public function sectorFor(float $px, float $py): string
    {
        $step = 4096 / 5;
        $col = (int) floor($px / $step);
        $row = (int) floor($py / $step);
        $col = max(0, min(4, $col));
        $row = max(0, min(4, $row));

        return self::ROW_LABELS[$row].self::COL_LABELS[$col];
    }

    /** Генерация 125 калибровочных точек (5×5 секторов × 5 смещений) */
    public function generateCalibrationPoints(): array
    {
        $points = [];
        $step = 4096 / 5;
        foreach (self::ROW_LABELS as $ri => $row) {
            foreach (self::COL_LABELS as $ci => $col) {
                $baseX = $ci * $step;
                $baseY = $ri * $step;
                foreach (self::OFFSETS as $off) {
                    $px = $baseX + $off[0] * $step;
                    $py = $baseY + $off[1] * $step;
                    $world = $this->pixelToWorld($px, $py);
                    $points[] = [
                        'x' => (int) round($world['x']),
                        'y' => (int) round($world['y']),
                        'z' => 0,
                        'sector' => $row.$col,
                    ];
                }
            }
        }

        return $points;
    }
}
