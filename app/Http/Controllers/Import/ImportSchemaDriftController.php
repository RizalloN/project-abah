<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SchemaDriftSourceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImportSchemaDriftController extends Controller
{
    public function __construct(
        private readonly SchemaDriftAnalyzer $analyzer,
        private readonly SchemaDriftRegistry $registry,
    ) {
    }

    public function analyze(Request $request): JsonResponse
    {
        $this->releaseSessionLockIfNeeded();
        @set_time_limit(120);

        $source = SchemaDriftSourceToken::read((string) $request->input('source'));
        if ($source === null) {
            return response()->json(['status' => 'error', 'message' => 'Sesi analisis struktur sudah kedaluwarsa. Muat ulang halaman preview.'], 422);
        }

        if (!is_file($source['path'])) {
            return response()->json(['status' => 'error', 'message' => 'File sumber sudah tidak tersedia untuk dianalisis.'], 404);
        }

        try {
            $report = $this->analyzer->analyze($source['table'], $source['path'], [
                'source_name' => $source['name'],
            ]);

            return response()->json(['status' => 'ok', 'report' => $report]);
        } catch (Throwable $e) {
            Log::warning('Schema drift analysis failed.', ['table' => $source['table'], 'error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => 'Analisis struktur gagal: ' . $e->getMessage()], 422);
        }
    }

    public function notifications(Request $request): JsonResponse
    {
        $this->releaseSessionLockIfNeeded();

        return response()->json($this->registry->notifications(min(50, max(1, (int) $request->query('limit', 15)))));
    }

    public function accept(Request $request, int $event): JsonResponse
    {
        $validated = $request->validate([
            'columns' => ['sometimes', 'array', 'max:100'],
            'columns.*.proposed_column' => ['required', 'string', 'max:64'],
            'columns.*.name' => ['sometimes', 'string', 'max:64'],
            'columns.*.type' => ['sometimes', 'string', 'max:40'],
            'columns.*.include' => ['sometimes'],
        ]);

        return $this->decide(fn () => $this->registry->accept($event, $this->actor($request), (array) ($validated['columns'] ?? [])), 'Kolom baru berhasil ditambahkan ke tabel. Import berikutnya akan langsung mengisi kolom tersebut.');
    }

    public function reject(Request $request, int $event): JsonResponse
    {
        return $this->decide(fn () => $this->registry->reject($event, $this->actor($request)), 'Kolom baru ditolak. Import tetap berjalan tanpa kolom tersebut dan sistem tidak akan menanyakannya lagi.');
    }

    public function acknowledge(Request $request, int $event): JsonResponse
    {
        return $this->decide(fn () => $this->registry->acknowledge($event, $this->actor($request)), 'Notifikasi ditandai sudah dibaca.');
    }

    private function decide(callable $action, string $message): JsonResponse
    {
        try {
            $event = $action();

            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'event' => $event ? $this->registry->presentNotification($event) : null,
                'feed' => $this->registry->notifications(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Schema drift decision failed.', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => 'Keputusan gagal diproses: ' . $e->getMessage()], 500);
        }
    }

    private function actor(Request $request): string
    {
        $user = $request->user();

        return trim(implode(' · ', array_filter([
            (string) ($user->name ?? ''),
            (string) ($user->pn ?? ''),
        ]))) ?: 'system';
    }
}
