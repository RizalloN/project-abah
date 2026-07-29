<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TrustedSpreadsheetUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!self::isTrusted((string) $value)) {
            $fail('Link spreadsheet harus berupa URL Google Sheets HTTPS yang valid.');
        }
    }

    public static function isTrusted(string $value): bool
    {
        $url = trim($value);
        $parts = parse_url($url);

        return !(
            $parts === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'docs.google.com'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || preg_match('~^/spreadsheets/d/[A-Za-z0-9_-]+(?:/|$)~', (string) ($parts['path'] ?? '')) !== 1
        );
    }
}
