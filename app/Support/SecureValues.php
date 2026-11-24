<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Хранение секретов (пароли, API-ключи) в зашифрованном виде.
 * Значения, не начинающиеся с префикса, считаются legacy-plaintext
 * и возвращаются как есть — обратная совместимость с ранее
 * сохранёнными открытыми данными.
 */
class SecureValues
{
    private const PREFIX = 'enc:';

    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '' || str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        return self::PREFIX.Crypt::encryptString($value);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '' || ! str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public static function isEncrypted(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }

    public static function hashPassword(?string $value): ?string
    {
        if ($value === null || $value === '' || self::isHash($value)) {
            return $value;
        }

        return Hash::make($value);
    }

    /**
     * Проверка пароля: принимает и bcrypt/argon2-хэш, и legacy-plaintext.
     */
    public static function verifyPassword(string $plain, ?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        if (self::isHash($stored)) {
            return Hash::check($plain, $stored);
        }

        return hash_equals($stored, $plain);
    }

    private static function isHash(string $value): bool
    {
        return str_starts_with($value, '$2y$') || str_starts_with($value, '$argon');
    }
}
