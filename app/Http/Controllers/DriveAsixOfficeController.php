<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveAsixOfficeException;
use App\Models\DriveAsixFile;
use App\Services\DriveAsix\OnlyOfficeDocumentStorageService;
use App\Services\DriveAsix\OnlyOfficeEditorService;
use App\Services\DriveAsix\OnlyOfficeJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DriveAsixOfficeController extends Controller
{
    private const DISK = 'local';

    private const BASE_PATH = 'drive_asix';

    private const MAX_CALLBACK_BYTES = 2_097_152;

    private const REGISTERED_CALLBACK_CLAIMS = [
        'aud',
        'exp',
        'iat',
        'iss',
        'jti',
        'nbf',
        'sub',
    ];

    /**
     * Open the self-hosted full-fidelity Office editor for authenticated users.
     */
    public function editor(
        Request $request,
        DriveAsixFile $file,
        OnlyOfficeEditorService $office,
        OnlyOfficeDocumentStorageService $documents
    ): View {
        abort_unless(
            $file->supportsFullFidelityEditor(),
            415,
            'Editor penuh hanya tersedia untuk DOCX, PPTX, dan XLSX.'
        );

        $viewData = [
            'file' => $file,
            'available' => false,
            'unavailableReason' => 'Document Server belum dikonfigurasi.',
            'backUrl' => route('drive.index', ['folderId' => $file->folder_id]),
            'fallbackUrl' => $this->fallbackUrl($file),
            'editorScriptUrl' => null,
            'editorConfig' => null,
        ];

        if (! $office->isConfigured()) {
            return view('drive.office-editor', $viewData);
        }

        try {
            $documents->validateEditableSource($file);
        } catch (DriveAsixOfficeException $exception) {
            abort(422, $exception->getMessage());
        }

        $health = $office->health();
        if (! ($health['ok'] ?? false)) {
            $viewData['unavailableReason'] =
                'Document Server sedang tidak dapat dijangkau. File asli tetap aman dan dapat dibuka melalui mode kompatibel.';

            return view('drive.office-editor', $viewData);
        }

        try {
            $revision = $office->revision($file);
            $session = $office->openOrReuseSession(
                $file,
                $revision,
                (string) Auth::id()
            );
            $documentKey = (string) $session['document_key'];
            $editorConfig = $office->buildEditorConfig(
                $file,
                $session,
                $revision,
                (string) Auth::id(),
                (string) (Auth::user()?->name ?? 'Pengguna DriveASIX'),
                route('drive.office.source', [
                    'file' => $file,
                    'documentKey' => $documentKey,
                ], false),
                route('drive.office.callback', [
                    'file' => $file,
                    'documentKey' => $documentKey,
                ], false),
                $file->extension(),
                $this->editorType($request)
            );

            return view('drive.office-editor', array_replace($viewData, [
                'available' => true,
                'unavailableReason' => '',
                'editorScriptUrl' => $office->editorScriptUrl(),
                'editorConfig' => $editorConfig,
            ]));
        } catch (DriveAsixOfficeException $exception) {
            report($exception);
            $viewData['unavailableReason'] =
                'Editor penuh gagal menyiapkan sesi yang aman. File asli tidak diubah; gunakan mode kompatibel atau coba lagi.';

            return view('drive.office-editor', $viewData);
        }
    }

    /**
     * Serve the private source file only to a matching, signed editor session.
     */
    public function source(
        Request $request,
        DriveAsixFile $file,
        string $documentKey,
        OnlyOfficeEditorService $office,
        OnlyOfficeJwtService $jwt,
        OnlyOfficeDocumentStorageService $documents
    ): BinaryFileResponse {
        abort_unless($office->isConfigured(), 503, 'Document Server belum dikonfigurasi.');
        abort_unless($file->supportsFullFidelityEditor(), 415);

        try {
            $session = $office->validateSession($file, $documentKey);
            $accessRevision = $this->sessionAccessRevision($session);
            $jwt->validateAccessToken(
                $this->accessToken($request),
                'source',
                $file->getKey(),
                $documentKey,
                $accessRevision
            );

            $currentRevision = $office->revision($file);
            if (! hash_equals(
                (string) ($session['last_revision'] ?? ''),
                $currentRevision
            )) {
                abort(409, 'File berubah setelah sesi editor dibuat.');
            }

            $documents->validateEditableSource($file);
        } catch (DriveAsixOfficeException) {
            abort(403, 'Akses sumber dokumen tidak valid atau telah kedaluwarsa.');
        }

        $path = $this->physicalPath($file);

        $response = response()->file($path, [
            'Content-Type' => $this->officeMimeType($file->extension()),
            'X-Content-Type-Options' => 'nosniff',
            'Pragma' => 'no-cache',
        ])->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $file->original_name,
            $this->safeDispositionFallback($file->original_name)
        );

        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->removeCacheControlDirective('public');

        return $response;
    }

    /**
     * Receive the signed ONLYOFFICE callback and persist status 2/6 saves.
     */
    public function callback(
        Request $request,
        DriveAsixFile $file,
        string $documentKey,
        OnlyOfficeEditorService $office,
        OnlyOfficeJwtService $jwt,
        OnlyOfficeDocumentStorageService $documents
    ): JsonResponse {
        if (! $office->isConfigured()) {
            return $this->callbackError('Document Server belum dikonfigurasi.', 503);
        }

        $declaredLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($declaredLength > self::MAX_CALLBACK_BYTES
            || strlen((string) $request->getContent()) > self::MAX_CALLBACK_BYTES) {
            return $this->callbackError('Payload callback terlalu besar.', 413);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || array_is_list($payload)) {
            return $this->callbackError('Payload callback tidak valid.', 422);
        }

        try {
            $claims = $jwt->verifyCallbackToken(
                $payload,
                $request->header($jwt->jwtHeaderName())
            );
            $payload = $this->effectiveCallbackPayload($payload, $claims);
        } catch (DriveAsixOfficeException $exception) {
            report($exception);

            return $this->callbackError('Callback tidak terautentikasi.', 403);
        }

        $status = $this->callbackStatus($payload['status'] ?? null);
        if ($status === null) {
            return $this->callbackError('Status callback tidak valid.', 422);
        }

        try {
            $session = $office->validateSession(
                $file,
                $documentKey,
                null,
                ! in_array($status, [2, 3, 4], true)
            );
            $accessRevision = $this->sessionAccessRevision($session);
            $jwt->validateAccessToken(
                $this->accessToken($request),
                'callback',
                $file->getKey(),
                $documentKey,
                $accessRevision
            );
            $this->assertCallbackIdentity($payload, $documentKey, $status, $file);
        } catch (DriveAsixOfficeException $exception) {
            report($exception);

            return $this->callbackError('Callback tidak terautentikasi.', 403);
        }

        try {
            if ($status === 1) {
                $office->updateSession($file->getKey(), $documentKey, [
                    'last_callback_status' => 1,
                    'last_callback_at' => now()->toIso8601String(),
                ]);

                return response()->json(['error' => 0]);
            }

            if ($status === 4) {
                $office->updateSession($file->getKey(), $documentKey, [
                    'last_callback_status' => 4,
                    'last_callback_at' => now()->toIso8601String(),
                ]);
                $office->closeSession($file->getKey(), $documentKey);

                return response()->json(['error' => 0]);
            }

            if (in_array($status, [3, 7], true)) {
                $office->updateSession($file->getKey(), $documentKey, [
                    'last_callback_status' => $status,
                    'last_callback_at' => now()->toIso8601String(),
                    'last_error' => $status === 3
                        ? 'Document Server melaporkan kegagalan final save.'
                        : 'Document Server melaporkan kegagalan force-save.',
                ]);

                report(new DriveAsixOfficeException(
                    'ONLYOFFICE melaporkan status save '.$status
                    .' untuk file DriveASIX #'.$file->getKey().'.'
                ));

                return response()->json(['error' => 0]);
            }

            $downloadUrl = $payload['url'] ?? null;
            if (! is_string($downloadUrl) || trim($downloadUrl) === '') {
                throw new DriveAsixOfficeException(
                    'URL file hasil edit tidak tersedia pada callback.'
                );
            }

            $expectedRevision = (string) ($session['last_revision'] ?? '');
            $result = $documents->persistEditedFile(
                $file,
                $downloadUrl,
                $expectedRevision,
                $documentKey
            );
            $office->updateSession($file->getKey(), $documentKey, [
                'last_revision' => $result['revision'],
                'last_callback_status' => $status,
                'last_callback_at' => now()->toIso8601String(),
                'last_saved_at' => now()->toIso8601String(),
                'last_saved_sha256' => $result['sha256'],
                'last_error' => null,
            ]);

            if ($status === 2) {
                $office->closeSession(
                    $file->getKey(),
                    $documentKey,
                    $result['revision']
                );
            }

            return response()->json(['error' => 0]);
        } catch (DriveAsixOfficeException $exception) {
            report($exception);

            return $this->callbackError(
                'Hasil edit gagal disimpan; file asli dipertahankan.',
                500
            );
        } catch (\Throwable $exception) {
            report($exception);

            return $this->callbackError(
                'Callback gagal diproses; file asli dipertahankan.',
                500
            );
        }
    }

    /**
     * Materialize token-in-body callbacks and keep signed values authoritative
     * when both signed and open parameters are present.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     */
    private function effectiveCallbackPayload(array $body, array $claims): array
    {
        $signedPayload = isset($claims['payload']) && is_array($claims['payload'])
            ? $claims['payload']
            : array_diff_key(
                $claims,
                array_flip(self::REGISTERED_CALLBACK_CLAIMS)
            );

        unset($body['token'], $signedPayload['token']);

        return array_replace($body, $signedPayload);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionAccessRevision(array $session): string
    {
        $revision = $session['access_revision']
            ?? $session['last_revision']
            ?? null;

        if (! is_string($revision) || $revision === '') {
            throw new DriveAsixOfficeException('Revision akses sesi tidak tersedia.');
        }

        return $revision;
    }

    private function accessToken(Request $request): string
    {
        $token = $request->query('access_token');

        if (! is_string($token) || $token === '' || strlen($token) > 65_536) {
            throw new DriveAsixOfficeException('Token akses endpoint tidak tersedia.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertCallbackIdentity(
        array $payload,
        string $documentKey,
        int $status,
        DriveAsixFile $file
    ): void {
        if (! isset($payload['key'])
            || ! is_string($payload['key'])
            || ! hash_equals($documentKey, $payload['key'])) {
            throw new DriveAsixOfficeException(
                'Document key callback tidak cocok dengan endpoint.'
            );
        }

        if ((int) $payload['status'] !== $status) {
            throw new DriveAsixOfficeException('Status callback tidak konsisten.');
        }

        if (isset($payload['filetype'])) {
            if (! is_string($payload['filetype'])
                || strtolower(ltrim($payload['filetype'], '.')) !== $file->extension()) {
                throw new DriveAsixOfficeException(
                    'Format file callback berbeda dari file DriveASIX.'
                );
            }
        }
    }

    private function callbackStatus(mixed $value): ?int
    {
        if (is_int($value)) {
            $status = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $status = (int) $value;
        } else {
            return null;
        }

        return in_array($status, [1, 2, 3, 4, 6, 7], true)
            ? $status
            : null;
    }

    private function callbackError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => 1,
            'message' => $message,
        ], $status);
    }

    private function fallbackUrl(DriveAsixFile $file): ?string
    {
        return match ($file->fallbackOpenMode()) {
            'spreadsheet' => route('drive.file.editor', $file),
            'document' => route('drive.file.document-preview', $file),
            default => null,
        };
    }

    private function editorType(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        return preg_match(
            '/Android|iPhone|iPad|iPod|Mobile|IEMobile|Opera Mini/i',
            $userAgent
        ) === 1 ? 'mobile' : 'desktop';
    }

    private function physicalPath(DriveAsixFile $file): string
    {
        if (basename($file->stored_name) !== $file->stored_name) {
            abort(404);
        }

        $relativePath = self::BASE_PATH.'/'.$file->stored_name;
        abort_unless(Storage::disk(self::DISK)->exists($relativePath), 404);

        return Storage::disk(self::DISK)->path($relativePath);
    }

    private function officeMimeType(string $extension): string
    {
        return match ($extension) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    private function safeDispositionFallback(string $name): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        $fallback = trim((string) $fallback, '._-');

        return $fallback !== '' ? $fallback : 'drive-asix-office';
    }
}
