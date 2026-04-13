<?php

if (!function_exists('highlightSearch')) {
    function highlightSearch(string $text, string $search): string
    {
        if (!$search) return $text;
        $terms = array_filter(explode(' ', $search));
        foreach ($terms as $term) {
            $escaped = preg_quote($term, '/');
            $text = preg_replace('/(' . $escaped . ')/i', '<mark>$1</mark>', $text);
        }
        return $text;
    }
}
