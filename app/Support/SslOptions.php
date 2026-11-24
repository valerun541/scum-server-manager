<?php

namespace App\Support;

/**
 * Общие опции SSL для исходящих HTTP-запросов (Lolka, Steam, автоматизация).
 * Если в storage/app лежит cacert.pem — верифицируем цепочку через него,
 * иначе отключаем проверку сертификата (Windows/локальная среда без корней).
 */
final class SslOptions
{
    public static function get(): array
    {
        $ca = storage_path('app/cacert.pem');
        if (is_file($ca)) {
            return ['verify' => $ca];
        }

        return ['verify' => false];
    }
}
