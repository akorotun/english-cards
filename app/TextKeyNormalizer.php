<?php

namespace App;

use InvalidArgumentException;
use Normalizer;

class TextKeyNormalizer
{
    public static function display(string $value): string
    {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

        if ($normalized === false) {
            throw new InvalidArgumentException('Text must be valid Unicode.');
        }

        return trim(preg_replace('/[\p{Z}\s]+/u', ' ', $normalized));
    }

    public static function key(string $value): string
    {
        $folded = mb_convert_case(self::display($value), MB_CASE_FOLD, 'UTF-8');
        $normalized = Normalizer::normalize($folded, Normalizer::FORM_C);

        if ($normalized === false) {
            throw new InvalidArgumentException('Text must be valid Unicode.');
        }

        return $normalized;
    }
}
