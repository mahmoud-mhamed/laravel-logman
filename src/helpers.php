<?php

// Thin wrapper for backward compatibility — delegates to the namespaced class.
if (!function_exists('highlightSearch')) {
    function highlightSearch(string $text, string $search, bool $isRegex = false): string
    {
        return \MahmoudMhamed\Logman\Support\TextHighlighter::highlight($text, $search, $isRegex);
    }
}

/**
 * Log an HTTP request on demand to Logman's request-logging channel.
 *
 * Use anywhere you want to capture a request explicitly:
 *
 *     logman_log_request();          // logs the current request
 *     logman_log_request($request);  // logs a specific request
 *     logman_log_request($request, $response);
 *
 * This is a manual write — it ignores the request_logging.enabled switch and
 * the skip/allowlist filters, since the call itself is the intent.
 */
if (!function_exists('logman_log_request')) {
    function logman_log_request(
        ?\Illuminate\Http\Request $request = null,
        ?\Symfony\Component\HttpFoundation\Response $response = null
    ): void {
        \MahmoudMhamed\Logman\Support\RequestLogger::write(
            $request ?? request(),
            $response,
            respectFilters: false
        );
    }
}
