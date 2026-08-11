<?php

namespace App\Services\DriveAsix;

use App\Exceptions\DriveAsixOfficeException;
use App\Models\DriveAsixFile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnlyOfficeEditorService
{
    private const DISK = 'local';

    private const FILE_PATH = 'drive_asix';

    private const SESSION_PATH = 'drive_asix_office_sessions';

    private const SUPPORTED_TYPES = [
        'docx' => 'word',
        'xlsx' => 'cell',
        'pptx' => 'slide',
    ];

    private const IMMUTABLE_SESSION_FIELDS = [
        'file_id',
        'document_key',
        'access_revision',
        'created_at',
    ];

    public function __construct(private readonly OnlyOfficeJwtService $jwt) {}

    public function isConfigured(): bool
    {
        if (! filter_var(
            config('services.onlyoffice.enabled', false),
            FILTER_VALIDATE_BOOL
        )) {
            return false;
        }

        $secret = (string) config('services.onlyoffice.jwt_secret', '');

        return strlen($secret) >= 32
            && $this->isAbsoluteHttpUrl($this->configuredUrl('public_url'))
            && $this->isAbsoluteHttpUrl($this->configuredUrl('internal_url'))
            && $this->isAbsoluteHttpUrl($this->configuredUrl('app_url'));
    }

    public function editorScriptUrl(): string
    {
        $this->assertConfigured();

        return $this->configuredUrl('public_url')
            .'/web-apps/apps/api/documents/api.js';
    }

    /**
     * Probe the private server-to-server ONLYOFFICE health endpoint.
     *
     * @return array{ok: bool, status: int|null, latency_ms: int, message: string}
     */
    public function health(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'latency_ms' => 0,
                'message' => 'Konfigurasi ONLYOFFICE belum lengkap.',
            ];
        }

        $timeout = min(
            5,
            max(1, (int) config('services.onlyoffice.timeout_seconds', 3))
        );
        $startedAt = hrtime(true);

        try {
            $response = Http::acceptJson()
                ->connectTimeout(min(2, $timeout))
                ->timeout($timeout)
                ->get($this->configuredUrl('internal_url').'/healthcheck');
            $body = strtolower(trim($response->body()));
            $ok = $response->successful()
                && in_array($body, ['true', '"true"', 'ok', '"ok"'], true);

            return [
                'ok' => $ok,
                'status' => $response->status(),
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'message' => $ok
                    ? 'ONLYOFFICE siap digunakan.'
                    : 'ONLYOFFICE memberikan respons health yang tidak valid.',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'message' => 'ONLYOFFICE tidak dapat dijangkau: '.$exception->getMessage(),
            ];
        }
    }

    public function documentType(string $extension): string
    {
        $extension = $this->normalizeExtension($extension);

        if (! isset(self::SUPPORTED_TYPES[$extension])) {
            throw new DriveAsixOfficeException(
                'Format file tidak didukung oleh editor full-fidelity ONLYOFFICE.'
            );
        }

        return self::SUPPORTED_TYPES[$extension];
    }

    /**
     * Calculate the physical SHA-256 revision used by sessions and access JWTs.
     */
    public function revision(DriveAsixFile $file): string
    {
        $this->validatedFileId($file->getKey());
        $storedName = (string) $file->stored_name;
        if ($storedName === '' || basename($storedName) !== $storedName) {
            throw new DriveAsixOfficeException('Lokasi file DriveASIX tidak valid.');
        }

        $path = Storage::disk(self::DISK)->path(self::FILE_PATH.'/'.$storedName);
        if (! is_file($path) || ! is_readable($path)) {
            throw new DriveAsixOfficeException('File DriveASIX tidak ditemukan atau tidak dapat dibaca.');
        }

        $revision = hash_file('sha256', $path);
        if (! is_string($revision) || $revision === '') {
            throw new DriveAsixOfficeException('Revision file DriveASIX gagal dihitung.');
        }

        return 'sha256:'.$revision;
    }

    public function hasActiveSession(DriveAsixFile|int|string $file): bool
    {
        $fileId = $this->validatedFileId(
            $file instanceof DriveAsixFile ? $file->getKey() : $file
        );
        $now = CarbonImmutable::now();

        foreach ($this->sessionsForFile($fileId) as $session) {
            if ($this->isActiveSession($session, $now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve and validate a persisted editor session before a source,
     * callback, save, delete, or purge action.
     *
     * @return array<string, mixed>
     */
    public function validateSession(
        DriveAsixFile|int|string $file,
        string $documentKey,
        ?string $expectedRevision = null,
        bool $requireActive = true
    ): array {
        $fileId = $this->validatedFileId(
            $file instanceof DriveAsixFile ? $file->getKey() : $file
        );
        $this->assertDocumentKey($documentKey);
        if ($expectedRevision !== null) {
            $this->assertRevision($expectedRevision);
        }

        $session = $this->readSession($fileId, $documentKey);
        if ($session === null) {
            throw new DriveAsixOfficeException('Sesi ONLYOFFICE tidak ditemukan.');
        }
        if ($requireActive && ! $this->isActiveSession($session, CarbonImmutable::now())) {
            throw new DriveAsixOfficeException('Sesi ONLYOFFICE sudah tidak aktif atau kedaluwarsa.');
        }
        if ($expectedRevision !== null
            && (! isset($session['last_revision'])
                || ! hash_equals($expectedRevision, (string) $session['last_revision']))) {
            throw new DriveAsixOfficeException(
                'Revision sesi ONLYOFFICE tidak cocok dengan file aktif.'
            );
        }

        return $session;
    }

    /**
     * Open a new co-editing session or reuse the active session for the exact
     * same physical revision.
     *
     * @return array<string, mixed>
     */
    public function openOrReuseSession(
        DriveAsixFile $file,
        string $currentRevision,
        int|string $userId
    ): array {
        $fileId = $this->validatedFileId($file->getKey());
        $this->assertRevision($currentRevision);

        return $this->withSessionLock($fileId, function () use (
            $fileId,
            $currentRevision,
            $userId
        ): array {
            if (! DriveAsixFile::query()->whereKey($fileId)->exists()) {
                throw new DriveAsixOfficeException(
                    'File DriveASIX sudah tidak tersedia untuk membuka sesi editor.'
                );
            }

            $now = CarbonImmutable::now();
            $sessions = $this->sessionsForFile($fileId);

            usort(
                $sessions,
                static fn (array $left, array $right): int => strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''))
            );

            foreach ($sessions as $session) {
                if (! $this->isReusableSession($session, $currentRevision, $now)) {
                    continue;
                }

                $session['participants'] = $this->participants(
                    $session['participants'] ?? [],
                    $userId
                );
                // Keep the revision embedded in the already-issued callback
                // URL stable for the complete lifetime of this document key.
                $session['access_revision'] ??= (string) $session['last_revision'];
                $session['updated_at'] = $now->toIso8601String();
                $session['expires_at'] = $this->expiresAt($now);
                $this->writeSession($fileId, (string) $session['document_key'], $session);

                return $session;
            }

            $documentKey = $this->newDocumentKey($fileId, $currentRevision);
            $session = [
                'file_id' => $fileId,
                'document_key' => $documentKey,
                'last_revision' => $currentRevision,
                'access_revision' => $currentRevision,
                'opened_by' => (string) $userId,
                'participants' => [(string) $userId],
                'active' => true,
                'created_at' => $now->toIso8601String(),
                'updated_at' => $now->toIso8601String(),
                'expires_at' => $this->expiresAt($now),
            ];
            $this->writeSession($fileId, $documentKey, $session);

            return $session;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readSession(int|string $fileId, string $documentKey): ?array
    {
        $fileId = $this->validatedFileId($fileId);
        $this->assertDocumentKey($documentKey);
        $path = $this->sessionAbsolutePath($fileId, $documentKey);

        if (! is_file($path)) {
            return null;
        }

        return $this->decodeSession(
            (string) file_get_contents($path),
            $fileId,
            $documentKey
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function updateSession(
        int|string $fileId,
        string $documentKey,
        array $changes
    ): array {
        $fileId = $this->validatedFileId($fileId);
        $this->assertDocumentKey($documentKey);

        return $this->withSessionLock($fileId, function () use (
            $fileId,
            $documentKey,
            $changes
        ): array {
            $session = $this->readSession($fileId, $documentKey);
            if ($session === null) {
                throw new DriveAsixOfficeException('Sesi ONLYOFFICE tidak ditemukan.');
            }

            foreach (self::IMMUTABLE_SESSION_FIELDS as $field) {
                if (array_key_exists($field, $changes)
                    && $changes[$field] !== $session[$field]) {
                    throw new DriveAsixOfficeException(
                        'Identitas sesi ONLYOFFICE tidak boleh diubah.'
                    );
                }
                unset($changes[$field]);
            }

            if (isset($changes['document_key'])) {
                $this->assertDocumentKey((string) $changes['document_key']);
            }
            if (isset($changes['last_revision'])) {
                $this->assertRevision((string) $changes['last_revision']);
            }

            $session = array_replace($session, $changes);
            $session['updated_at'] = CarbonImmutable::now()->toIso8601String();
            $this->writeSession($fileId, $documentKey, $session);

            return $session;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function closeSession(
        int|string $fileId,
        string $documentKey,
        ?string $lastRevision = null
    ): ?array {
        if ($lastRevision !== null) {
            $this->assertRevision($lastRevision);
        }

        $changes = [
            'active' => false,
            'closed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
        if ($lastRevision !== null) {
            $changes['last_revision'] = $lastRevision;
        }

        $fileId = $this->validatedFileId($fileId);
        $this->assertDocumentKey($documentKey);
        if ($this->readSession($fileId, $documentKey) === null) {
            return null;
        }

        return $this->updateSession($fileId, $documentKey, $changes);
    }

    /**
     * Build the complete DocsAPI editor configuration. Relative source and
     * callback paths are resolved against services.onlyoffice.app_url. Passing
     * $actualFileType allows a caller to identify XLSX content whose stored
     * filename has a misleading .xls extension.
     *
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    public function buildEditorConfig(
        DriveAsixFile $file,
        array $session,
        string $currentRevision,
        int|string $userId,
        string $userName,
        string $sourcePathOrUrl,
        string $callbackPathOrUrl,
        ?string $actualFileType = null,
        string $editorType = 'desktop'
    ): array {
        $this->assertConfigured();
        $this->assertRevision($currentRevision);

        $fileId = $this->validatedFileId($file->getKey());
        $documentKey = (string) ($session['document_key'] ?? '');
        $this->assertDocumentKey($documentKey);
        $accessRevision = (string) ($session['access_revision'] ?? '');
        $this->assertRevision($accessRevision);
        if ((string) ($session['file_id'] ?? '') !== $fileId
            || ! $this->isActiveSession($session, CarbonImmutable::now())
            || ! isset($session['last_revision'])
            || ! hash_equals($currentRevision, (string) $session['last_revision'])) {
            throw new DriveAsixOfficeException(
                'Sesi ONLYOFFICE tidak sesuai dengan revision file aktif.'
            );
        }

        $fileType = $actualFileType !== null
            ? $this->normalizeExtension($actualFileType)
            : $this->normalizeExtension($file->extension());
        $documentType = $this->documentType($fileType);
        $sourceUrl = $this->absoluteAppUrl($sourcePathOrUrl);
        $callbackUrl = $this->absoluteAppUrl($callbackPathOrUrl);

        $sourceUrl = $this->appendQuery(
            $sourceUrl,
            'access_token',
            $this->jwt->issueAccessToken(
                'source',
                $fileId,
                $documentKey,
                $accessRevision
            )
        );
        $callbackUrl = $this->appendQuery(
            $callbackUrl,
            'access_token',
            $this->jwt->issueAccessToken(
                'callback',
                $fileId,
                $documentKey,
                $accessRevision
            )
        );

        $configuration = [
            'type' => in_array($editorType, ['desktop', 'mobile', 'embedded'], true)
                ? $editorType
                : 'desktop',
            'documentType' => $documentType,
            'document' => [
                'fileType' => $fileType,
                'key' => $documentKey,
                'title' => $file->original_name,
                'url' => $sourceUrl,
                'info' => [
                    'owner' => 'DriveASIX',
                    'uploaded' => optional($file->created_at)->toIso8601String(),
                ],
                'permissions' => [
                    'chat' => true,
                    'comment' => true,
                    'copy' => true,
                    'download' => true,
                    'edit' => true,
                    'fillForms' => true,
                    'modifyContentControl' => true,
                    'modifyFilter' => true,
                    'print' => true,
                    'review' => true,
                ],
            ],
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'lang' => 'id',
                'mode' => 'edit',
                'user' => [
                    'id' => (string) $userId,
                    'name' => trim($userName) !== '' ? trim($userName) : 'Pengguna DriveASIX',
                ],
                'coEditing' => [
                    'mode' => 'fast',
                    'change' => true,
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                    'help' => true,
                ],
            ],
            'width' => '100%',
            'height' => '100%',
        ];
        $configuration['token'] = $this->jwt->signConfiguration($configuration);

        return $configuration;
    }

    public function absoluteAppUrl(string $pathOrUrl): string
    {
        $this->assertConfigured();
        $pathOrUrl = trim($pathOrUrl);

        if ($this->isAbsoluteHttpUrl($pathOrUrl)) {
            if (! $this->sameOrigin($pathOrUrl, $this->configuredUrl('app_url'))) {
                throw new DriveAsixOfficeException(
                    'URL source/callback wajib menggunakan origin aplikasi DriveASIX.'
                );
            }

            return $pathOrUrl;
        }

        if ($pathOrUrl === '' || str_starts_with($pathOrUrl, '//')) {
            throw new DriveAsixOfficeException('Path source/callback ONLYOFFICE tidak valid.');
        }

        return $this->configuredUrl('app_url').'/'.ltrim($pathOrUrl, '/');
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new DriveAsixOfficeException(
                'Konfigurasi ONLYOFFICE belum lengkap atau tidak aman.'
            );
        }
    }

    private function configuredUrl(string $key): string
    {
        return rtrim(trim((string) config('services.onlyoffice.'.$key, '')), '/');
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && isset($parts['host'])
            && $parts['host'] !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    private function sameOrigin(string $left, string $right): bool
    {
        $leftParts = parse_url($left);
        $rightParts = parse_url($right);
        if (! is_array($leftParts) || ! is_array($rightParts)) {
            return false;
        }

        $origin = static function (array $parts): string {
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower((string) ($parts['host'] ?? ''));
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

            return $scheme.'://'.$host.':'.$port;
        };

        return hash_equals($origin($leftParts), $origin($rightParts));
    }

    private function normalizeExtension(string $extension): string
    {
        return strtolower(ltrim(trim($extension), '.'));
    }

    private function newDocumentKey(string $fileId, string $revision): string
    {
        $instance = substr(hash(
            'sha256',
            (string) config('app.key').'|'.$this->configuredUrl('app_url')
        ), 0, 16);
        $revisionPart = substr(hash('sha256', $revision), 0, 16);
        $random = str_replace('-', '', (string) Str::uuid());

        return substr(
            'dasix-'.$instance.'-'.$fileId.'-'.$revisionPart.'-'.$random,
            0,
            128
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sessionsForFile(string $fileId): array
    {
        $directory = $this->sessionDirectory($fileId);
        if (! is_dir($directory)) {
            return [];
        }

        $sessions = [];
        foreach ((new Filesystem)->files($directory) as $file) {
            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $key = $file->getBasename('.json');
            if (preg_match('/^[A-Za-z0-9._=-]{1,128}$/', $key) !== 1) {
                continue;
            }

            try {
                $sessions[] = $this->decodeSession(
                    (string) file_get_contents($file->getPathname()),
                    $fileId,
                    $key
                );
            } catch (DriveAsixOfficeException) {
                continue;
            }
        }

        return $sessions;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function isReusableSession(
        array $session,
        string $revision,
        CarbonImmutable $now
    ): bool {
        if (! $this->isActiveSession($session, $now)
            || ! isset($session['last_revision'])
            || ! hash_equals($revision, (string) $session['last_revision'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function isActiveSession(array $session, CarbonImmutable $now): bool
    {
        if (($session['active'] ?? false) !== true || ! isset($session['expires_at'])) {
            return false;
        }

        try {
            return CarbonImmutable::parse((string) $session['expires_at'])->isAfter($now);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function participants(mixed $existing, int|string $userId): array
    {
        $participants = is_array($existing)
            ? array_map('strval', array_filter($existing, 'is_scalar'))
            : [];
        $participants[] = (string) $userId;

        return array_values(array_unique($participants));
    }

    private function expiresAt(CarbonImmutable $from): string
    {
        return $from
            ->addMinutes($this->jwt->accessTtlMinutes())
            ->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function writeSession(string $fileId, string $documentKey, array $session): void
    {
        $this->assertDocumentKey($documentKey);
        $directory = $this->sessionDirectory($fileId);
        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists($directory);

        try {
            $json = json_encode(
                $session,
                JSON_PRETTY_PRINT
                    | JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException $exception) {
            throw new DriveAsixOfficeException(
                'Data sesi ONLYOFFICE tidak dapat diserialisasi.',
                previous: $exception
            );
        }

        if (strlen($json) > 262_144) {
            throw new DriveAsixOfficeException('Data sesi ONLYOFFICE melebihi batas aman.');
        }

        try {
            $filesystem->replace(
                $this->sessionAbsolutePath($fileId, $documentKey),
                $json.PHP_EOL
            );
        } catch (\Throwable $exception) {
            throw new DriveAsixOfficeException(
                'Sesi ONLYOFFICE gagal disimpan secara atomik.',
                previous: $exception
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSession(
        string $json,
        string $expectedFileId,
        string $expectedDocumentKey
    ): array {
        try {
            $session = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new DriveAsixOfficeException(
                'Data sesi ONLYOFFICE rusak.',
                previous: $exception
            );
        }

        if (! is_array($session)
            || array_is_list($session)
            || (string) ($session['file_id'] ?? '') !== $expectedFileId
            || ! isset($session['document_key'])
            || ! hash_equals($expectedDocumentKey, (string) $session['document_key'])) {
            throw new DriveAsixOfficeException('Identitas sesi ONLYOFFICE tidak valid.');
        }

        return $session;
    }

    private function sessionDirectory(string $fileId): string
    {
        return Storage::disk(self::DISK)->path(self::SESSION_PATH.'/'.$fileId);
    }

    private function sessionAbsolutePath(string $fileId, string $documentKey): string
    {
        return Storage::disk(self::DISK)->path(
            self::SESSION_PATH.'/'.$fileId.'/'.$documentKey.'.json'
        );
    }

    private function validatedFileId(mixed $fileId): string
    {
        $fileId = (string) $fileId;
        if (preg_match('/^[1-9][0-9]{0,18}$/', $fileId) !== 1) {
            throw new DriveAsixOfficeException('ID file DriveASIX tidak valid.');
        }

        return $fileId;
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

    private function appendQuery(string $url, string $key, string $value): string
    {
        return $url
            .(str_contains($url, '?') ? '&' : '?')
            .rawurlencode($key).'='.rawurlencode($value);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withSessionLock(string $fileId, callable $callback): mixed
    {
        try {
            return Cache::lock('drive-asix:office-session:'.$fileId, 15)
                ->block(5, $callback);
        } catch (LockTimeoutException $exception) {
            throw new DriveAsixOfficeException(
                'Sesi ONLYOFFICE sedang diperbarui. Silakan coba lagi.',
                previous: $exception
            );
        }
    }
}
