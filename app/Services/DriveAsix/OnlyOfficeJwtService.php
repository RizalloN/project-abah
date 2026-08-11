<?php

namespace App\Services\DriveAsix;

use App\Exceptions\DriveAsixOfficeException;

class OnlyOfficeJwtService
{
    private const REGISTERED_CLAIMS = [
        'aud',
        'exp',
        'iat',
        'iss',
        'jti',
        'nbf',
        'sub',
    ];

    private const MAX_TOKEN_BYTES = 65_536;

    /**
     * Sign an arbitrary ONLYOFFICE payload with HS256.
     *
     * @param  array<string, mixed>  $claims
     */
    public function sign(array $claims): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $encodedHeader = $this->base64UrlEncode($this->jsonEncode($header));
        $encodedClaims = $this->base64UrlEncode($this->jsonEncode($claims));
        $signature = hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedClaims,
            $this->secret(),
            true
        );

        return $encodedHeader.'.'.$encodedClaims.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function signConfiguration(array $configuration): string
    {
        unset($configuration['token']);

        return $this->sign($configuration);
    }

    public function issueAccessToken(
        string $purpose,
        int|string $fileId,
        string $documentKey,
        string $revision
    ): string {
        $purpose = $this->validatedPurpose($purpose);
        $this->assertDocumentKey($documentKey);
        $this->assertRevision($revision);

        $issuedAt = time();

        return $this->sign([
            'purpose' => $purpose,
            'file_id' => (string) $fileId,
            'key' => $documentKey,
            'revision' => $revision,
            'iat' => $issuedAt,
            'exp' => $issuedAt + ($this->accessTtlMinutes() * 60),
        ]);
    }

    /**
     * Validate the signature, lifetime, purpose, and exact document identity.
     *
     * @return array<string, mixed>
     */
    public function validateAccessToken(
        string $token,
        string $expectedPurpose,
        int|string $expectedFileId,
        string $expectedDocumentKey,
        string $expectedRevision
    ): array {
        $claims = $this->verify($token);
        $expectedPurpose = $this->validatedPurpose($expectedPurpose);
        $this->assertDocumentKey($expectedDocumentKey);
        $this->assertRevision($expectedRevision);

        $expected = [
            'purpose' => $expectedPurpose,
            'file_id' => (string) $expectedFileId,
            'key' => $expectedDocumentKey,
            'revision' => $expectedRevision,
        ];

        foreach ($expected as $claim => $value) {
            if (! isset($claims[$claim]) || ! is_scalar($claims[$claim])) {
                throw new DriveAsixOfficeException(
                    'Token akses ONLYOFFICE tidak memuat identitas dokumen yang lengkap.'
                );
            }

            if (! hash_equals($value, (string) $claims[$claim])) {
                throw new DriveAsixOfficeException(
                    'Token akses ONLYOFFICE tidak cocok dengan dokumen yang diminta.'
                );
            }
        }

        if (! isset($claims['exp']) || ! is_int($claims['exp'])) {
            throw new DriveAsixOfficeException('Token akses ONLYOFFICE tidak memiliki masa berlaku.');
        }

        return $claims;
    }

    /**
     * Verify a JWT and enforce its standard time claims.
     *
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        if ($token === '' || strlen($token) > self::MAX_TOKEN_BYTES) {
            throw new DriveAsixOfficeException('Token ONLYOFFICE tidak valid.');
        }

        $segments = explode('.', $token);
        if (count($segments) !== 3 || in_array('', $segments, true)) {
            throw new DriveAsixOfficeException('Format token ONLYOFFICE tidak valid.');
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $segments;
        $header = $this->decodeObject($encodedHeader, 'header');
        $claims = $this->decodeObject($encodedClaims, 'payload');
        $signature = $this->base64UrlDecode($encodedSignature);

        if (($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? 'JWT') !== 'JWT') {
            throw new DriveAsixOfficeException('Algoritma token ONLYOFFICE tidak diizinkan.');
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedClaims,
            $this->secret(),
            true
        );

        if (! hash_equals($expectedSignature, $signature)) {
            throw new DriveAsixOfficeException('Tanda tangan token ONLYOFFICE tidak valid.');
        }

        $now = time();
        if (array_key_exists('nbf', $claims)
            && (! is_int($claims['nbf']) || $claims['nbf'] > $now + 30)) {
            throw new DriveAsixOfficeException('Token ONLYOFFICE belum dapat digunakan.');
        }
        if (array_key_exists('iat', $claims)
            && (! is_int($claims['iat']) || $claims['iat'] > $now + 30)) {
            throw new DriveAsixOfficeException('Waktu penerbitan token ONLYOFFICE tidak valid.');
        }
        if (array_key_exists('exp', $claims)
            && (! is_int($claims['exp']) || $claims['exp'] <= $now)) {
            throw new DriveAsixOfficeException('Token ONLYOFFICE telah kedaluwarsa.');
        }

        return $claims;
    }

    /**
     * Accept the token supplied directly in the callback body or through a
     * Bearer header. Signed fields must match their open-body counterparts.
     * ONLYOFFICE can omit large non-critical fields from a header token, while
     * token-in-body mode can send only the token itself.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function verifyCallbackToken(array $body, ?string $authorizationHeader = null): array
    {
        $bodyToken = isset($body['token']) && is_string($body['token'])
            ? trim($body['token'])
            : '';
        $headerToken = $this->bearerToken($authorizationHeader);
        $token = $bodyToken !== '' ? $bodyToken : $headerToken;

        if ($token === '') {
            throw new DriveAsixOfficeException('Token callback ONLYOFFICE tidak tersedia.');
        }

        $claims = $this->verify($token);
        $signedPayload = isset($claims['payload']) && is_array($claims['payload'])
            ? $claims['payload']
            : array_diff_key($claims, array_flip(self::REGISTERED_CLAIMS));
        unset($signedPayload['token']);

        $callbackBody = $body;
        unset($callbackBody['token']);

        if ($signedPayload === []
            || ! isset($signedPayload['key'], $signedPayload['status'])
            || ! is_scalar($signedPayload['key'])
            || ! is_int($signedPayload['status'])) {
            throw new DriveAsixOfficeException('Payload callback ONLYOFFICE kosong.');
        }

        // With token-in-body enabled ONLYOFFICE may send only {"token":"..."}.
        // With a header token, fields such as history can be intentionally
        // omitted from the signed payload because of header-size limits.
        foreach ($callbackBody as $field => $value) {
            if (array_key_exists($field, $signedPayload)
                && $this->normalize($signedPayload[$field]) !== $this->normalize($value)) {
                throw new DriveAsixOfficeException(
                    'Payload callback ONLYOFFICE tidak cocok dengan token yang ditandatangani.'
                );
            }
        }

        if (in_array($signedPayload['status'], [2, 6], true)
            && (! isset($signedPayload['url'])
                || ! is_string($signedPayload['url'])
                || trim($signedPayload['url']) === '')) {
            throw new DriveAsixOfficeException(
                'Token callback ONLYOFFICE tidak menandatangani URL hasil edit.'
            );
        }

        return $claims;
    }

    public function jwtHeaderName(): string
    {
        $header = trim((string) config('services.onlyoffice.jwt_header', 'Authorization'));

        return preg_match('/^[A-Za-z0-9-]{1,64}$/', $header) === 1
            ? $header
            : 'Authorization';
    }

    public function accessTtlMinutes(): int
    {
        return min(
            1_440,
            max(1, (int) config('services.onlyoffice.access_ttl_minutes', 1440))
        );
    }

    private function secret(): string
    {
        $secret = (string) config('services.onlyoffice.jwt_secret', '');
        if (strlen($secret) < 32) {
            throw new DriveAsixOfficeException(
                'JWT secret ONLYOFFICE harus memiliki minimal 32 karakter.'
            );
        }

        return $secret;
    }

    private function validatedPurpose(string $purpose): string
    {
        if (! in_array($purpose, ['source', 'callback'], true)) {
            throw new DriveAsixOfficeException('Purpose token ONLYOFFICE tidak valid.');
        }

        return $purpose;
    }

    private function assertDocumentKey(string $documentKey): void
    {
        if (preg_match('/^[A-Za-z0-9._=-]{1,128}$/', $documentKey) !== 1) {
            throw new DriveAsixOfficeException('Document key ONLYOFFICE tidak valid.');
        }
    }

    private function assertRevision(string $revision): void
    {
        if ($revision === ''
            || strlen($revision) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $revision) === 1) {
            throw new DriveAsixOfficeException('Revision dokumen ONLYOFFICE tidak valid.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeObject(string $segment, string $label): array
    {
        try {
            $decoded = json_decode(
                $this->base64UrlDecode($segment),
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new DriveAsixOfficeException(
                'JSON '.$label.' token ONLYOFFICE tidak valid.',
                previous: $exception
            );
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new DriveAsixOfficeException(
                ucfirst($label).' token ONLYOFFICE harus berupa objek JSON.'
            );
        }

        return $decoded;
    }

    private function bearerToken(?string $authorizationHeader): string
    {
        if ($authorizationHeader === null) {
            return '';
        }

        if (preg_match('/^\s*Bearer\s+([^\s,]+)\s*$/i', $authorizationHeader, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    private function jsonEncode(array $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException $exception) {
            throw new DriveAsixOfficeException(
                'Payload token ONLYOFFICE tidak dapat diproses.',
                previous: $exception
            );
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new DriveAsixOfficeException('Encoding token ONLYOFFICE tidak valid.');
        }

        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new DriveAsixOfficeException('Encoding token ONLYOFFICE tidak valid.');
        }

        return $decoded;
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
