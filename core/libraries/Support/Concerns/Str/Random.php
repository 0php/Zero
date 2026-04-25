<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Random
{
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        $hex = bin2hex($data);
        return vsprintf('%s-%s-%s-%s-%s', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ]);
    }

    public static function uuid7(?\DateTimeInterface $time = null): string
    {
        $unixTimeMs = $time !== null
            ? (int) ($time->format('U.u') * 1000)
            : (int) (microtime(true) * 1000);
        $bytes = random_bytes(10);
        $timeHex = str_pad(dechex($unixTimeMs), 12, '0', STR_PAD_LEFT);
        $bytes[0] = chr((ord($bytes[0]) & 0x0F) | 0x70);
        $bytes[2] = chr((ord($bytes[2]) & 0x3F) | 0x80);
        $rand = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            substr($rand, 0, 4),
            substr($rand, 4, 4),
            substr($rand, 8, 12)
        );
    }

    public static function orderedUuid(): string
    {
        return self::uuid7();
    }

    public static function ulid(): string
    {
        $time = (int) (microtime(true) * 1000);
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $timePart = '';
        for ($i = 9; $i >= 0; $i--) {
            $timePart = $alphabet[$time % 32] . $timePart;
            $time = intdiv($time, 32);
        }
        $randomPart = '';
        for ($i = 0; $i < 16; $i++) {
            $randomPart .= $alphabet[random_int(0, 31)];
        }
        return $timePart . $randomPart;
    }

    public static function random(int $length = 16, ?string $alphabet = null): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be greater than zero.');
        }

        $alphabet ??= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $alphabetLength = strlen($alphabet);

        if ($alphabetLength === 0) {
            throw new \InvalidArgumentException('Alphabet must not be empty.');
        }

        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $alphabetLength - 1);
            $result .= $alphabet[$index];
        }

        return $result;
    }

    public static function password(int $length = 32, bool $letters = true, bool $numbers = true, bool $symbols = true, bool $spaces = false): string
    {
        $alphabet = '';
        if ($letters) {
            $alphabet .= 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if ($numbers) {
            $alphabet .= '0123456789';
        }
        if ($symbols) {
            $alphabet .= '~!#$%^&*()-_.,<>?/\\{}[]|:;';
        }
        if ($spaces) {
            $alphabet .= ' ';
        }
        if ($alphabet === '') {
            return '';
        }
        $password = '';
        $alphaLen = strlen($alphabet);
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $alphaLen - 1)];
        }
        return $password;
    }
}
