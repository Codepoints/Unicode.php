<?php


namespace Codepoints\Unicode;


/**
 *
 */
class Toolkit {

    const PARSE_STRING_MAXLENGTH = 256;

    /**
     *
     */
    public static function normalizeName($name) {
        return str_replace([' ', '_'], '', strtolower($name));
    }

    /**
     * return the codepoint for a single representation
     */
    public static function parseCodepoint($str) {
        preg_match('/^(?:U[\+-]|\\\\U|0x|U)?([0-9a-f]+)$/i', $str, $matches);
        if (count($matches) === 2) {
            return intval($matches[1], 16);
        }
        return NULL;
    }

    /**
     * parse a string of form U+A..U+B,U+C in a Range
     */
    public static function parseRange($str, $db) {
        $set = [];
        $junks = preg_split('/\s*(?:,\s*)+/', trim($str));
        foreach ($junks as $j) {
            $ranges = preg_split('/\s*(?:-|\.\.|:)\s*/', $j);
            switch (count($ranges)) {
                case 0:
                    break;
                case 1:
                    $tmp = Toolkit::parseCodepoint($ranges[0]);
                    if (is_int($tmp)) {
                        $set[] = $tmp;
                    }
                    break;
                case 2:
                    $low = Toolkit::parseCodepoint($ranges[0]);
                    $high = Toolkit::parseCodepoint($ranges[1]);
                    if (is_int($low) && is_int($high)) {
                        $set = array_merge($set, range(min($low, $high),
                                                       max($high, $low)));
                    }
                    break;
                default:
                    $max = -1;
                    $min = 0x110000;
                    foreach ($ranges as $r) {
                        $tmp = Toolkit::parseCodepoint($r);
                        if (is_int($tmp) && $tmp > $max) {
                            $max = $tmp;
                        }
                        if (is_int($tmp) && $tmp < $min) {
                            $min = $tmp;
                        }
                    }
                    if ($min < 0x110000 && $max > -1) {
                        $set = array_merge($set, range(min($min, $max),
                                                       max($max, $min)));
                    }
            }
        }
        return new Range($set, $db);
    }

    /**
     * get all codepoints of a string
     */
    public static function parseString($string, $db) {
        $cps = [];
        if (mb_strlen($string) < self::PARSE_STRING_MAXLENGTH) {
            foreach (preg_split('/(?<!^)(?!$)/u', $string) as $c) {
                $cc = unpack('N', mb_convert_encoding($c, 'UCS-4BE', 'UTF-8'));
                try {
                    $cx = Codepoint::getCP($cc[1], $db);
                    // test, if codepoint exists
                    $cx->getName();
                    $cps[] = $cx;
                } catch (Exception $e) {
                }
            }
        }
        return $cps;
    }

}


//EOF
