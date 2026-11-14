<?php

namespace App\Support;

use InvalidArgumentException;

final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', self::encode($value));
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_float($value) || is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('O contrato não aceita floats ou objetos no JSON canônico.');
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as &$item) {
                $item = self::normalize($item);
            }
        }
        return $value;
    }
}
