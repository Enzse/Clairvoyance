<?php

namespace Domain;

/** English plurals for nouns people type (an organisation's word for its units, unit kinds). */
final class Words
{
    public static function plural(string $noun): string
    {
        if (preg_match('/(s|x|z|ch|sh)$/i', $noun)) {
            return $noun . 'es';
        }
        if (preg_match('/[^aeiou]y$/i', $noun)) {
            return substr($noun, 0, -1) . 'ies';
        }
        return $noun . 's';
    }
}
