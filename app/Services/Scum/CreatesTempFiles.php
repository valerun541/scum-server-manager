<?php

namespace App\Services\Scum;

/**
 * Безопасное создание временного файла: на Windows sys_get_temp_dir() может
 * указывать на неписываемый каталог (PHP 8.4: warning "file created in the
 * system's temporary directory", который Laravel превращает в исключение).
 * Приоритет — гарантированно записываемый каталог storage/app/scum.
 */
trait CreatesTempFiles
{
    private function scumTempFile(): string
    {
        $dirs = [storage_path('app/scum'), sys_get_temp_dir()];
        foreach ($dirs as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                $f = @tempnam($dir, 'scum_');
                if ($f !== false) {
                    return $f;
                }
            }
        }
        throw new \RuntimeException('Не удалось создать временный файл');
    }
}
