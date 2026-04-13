<?php

// Thin wrapper for backward compatibility — delegates to the namespaced class.
if (!function_exists('highlightSearch')) {
    function highlightSearch(string $text, string $search, bool $isRegex = false): string
    {
        return \MahmoudMhamed\Logman\Support\TextHighlighter::highlight($text, $search, $isRegex);
    }
}
