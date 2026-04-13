<?php

namespace Mhamed\Logman\Support;

class TextHighlighter
{
    public static function highlight(string $text, string $search, bool $isRegex = false): string
    {
        if (!$search) return $text;

        // Use placeholders to avoid XSS — text is already e()-escaped by the caller,
        // so we insert safe placeholders first, then convert to HTML at the end.
        $open = "\x00HL_OPEN\x00";
        $close = "\x00HL_CLOSE\x00";

        if ($isRegex) {
            // Wrap in a delimiter-safe way: use \x01 as delimiter to avoid conflicts
            $pattern = "\x01(" . $search . ")\x01iu";
            if (@preg_match($pattern, '') !== false) {
                $oldLimit = ini_get('pcre.backtrack_limit');
                ini_set('pcre.backtrack_limit', 10000);

                $result = @preg_replace($pattern, $open . '$1' . $close, $text);
                if ($result !== null) {
                    $text = $result;
                }

                ini_set('pcre.backtrack_limit', $oldLimit);
            }
        } else {
            $terms = array_filter(explode(' ', $search));
            foreach ($terms as $term) {
                $escaped = preg_quote($term, '/');
                $text = preg_replace('/(' . $escaped . ')/i', $open . '$1' . $close, $text);
            }
        }

        return str_replace([$open, $close], ['<mark>', '</mark>'], $text);
    }
}
