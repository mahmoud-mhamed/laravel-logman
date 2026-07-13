<?php

namespace MahmoudMhamed\Logman\Support;

class Sanitizer
{
    /**
     * Recursively mask sensitive fields (by key name) in an array, replacing
     * their values with '********'. Field names are read from the
     * logman.hidden_fields config and matched case-insensitively.
     */
    public static function mask(array $data, ?array $hidden = null): array
    {
        $hidden ??= config('logman.hidden_fields', [
            'password', 'password_confirmation', 'token', 'secret',
            'credit_card', 'card_number', 'cvv', 'ssn',
            'authorization', 'api_key', 'api_secret', 'access_token',
            'refresh_token', 'private_key',
        ]);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::mask($value, $hidden);
            } elseif (is_string($key) && in_array(strtolower($key), $hidden, true)) {
                $data[$key] = '********';
            }
        }

        return $data;
    }
}
