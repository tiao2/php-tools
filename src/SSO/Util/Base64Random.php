<?php

declare(strict_types=1);

namespace PhpTools\SSO\Util;

class Base64Random
{
    private const CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-';

    public static function generate(int $length = 16): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, strlen(self::CHARS) - 1);
            $result .= self::CHARS[$index];
        }
        return $result;
    }
}