<?php

if (!function_exists('highlightSearch')) {
    function highlightSearch(string $text, string $search, bool $isRegex = false): string
    {
        if (!$search) return $text;

        if ($isRegex) {
            // Use the regex pattern directly for highlighting
            if (@preg_match('/' . $search . '/i', '') !== false) {
                $text = @preg_replace('/(' . $search . ')/i', '<mark>$1</mark>', $text) ?? $text;
            }
            return $text;
        }

        $terms = array_filter(explode(' ', $search));
        foreach ($terms as $term) {
            $escaped = preg_quote($term, '/');
            $text = preg_replace('/(' . $escaped . ')/i', '<mark>$1</mark>', $text);
        }
        return $text;
    }
}
