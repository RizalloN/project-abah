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
            'columns.*.action' => ['sometimes', 'string', 'in:add,map,skip'],
            'columns.*.target' => ['sometimes', 'nullable', 'string', 'max:64'],
            'adjustments' => ['sometimes', 'array', 'max:100'],
            'adjustments.*.column' => ['required', 'string', 'max:64'],
            'adjustments.*.to_type' => ['sometimes', 'string', 'max:40'],
            'adjustments.*.include' => ['sometimes'],
            'reviews' => ['sometimes', 'array', 'max:200'],
            'reviews.*.key' => ['required', 'string', 'max:160'],
            'reviews.*.action' => ['required', 'string', 'in:continue,abort'],
            'source_fingerprint' => ['sometimes', 'nullable', 'string', 'size:64'],
            'source' => ['sometimes', 'string'],
        ]);

        return $this->decide(
            function () use ($request, $event, $validated) {
                $this->assertDecisionSource($event, $validated);

                return $this->registry->accept(
                    $event,
                    $this->actor($request),
                    (array) ($validated['columns'] ?? []),
                    (array) ($validated['adjustments'] ?? []),
                    (array) ($validated['reviews'] ?? []),
                    $validated['source_fingerprint'] ?? null
                );
            },
            'Keputusan diterapkan. Pindai ulang untuk memastikan seluruh kolom dan perubahan sumber sudah diselesaikan sebelum import.'
        );
    }

    public function reject(Request $request, int $event): JsonResponse
    {
        $validated = $request->validate(['source_fingerprint' => ['sometimes', 'nullable', 'string', 'size:64']]);

        return $this->decide(fn () => $this->registry->reject($event, $this->actor($request), $validated['source_fingerprint'] ?? null), 'Kolom baru dilewati sesuai keputusan Anda. Pemeriksaan bentuk/isi sumber tetap harus diselesaikan sebelum import.');
    }

    public function acknowledge(Request $request, int $event): JsonResponse
    {
        return $this->decide(fn () => $this->registry->acknowledge($event, $this->actor($request)), 'Notifikasi ditandai sudah dibaca.');
    }

    private function assertDecisionSource(int $eventId, array $validated): void
    {
        // Legacy notification decisions have no source token. Preview decisions
        // carry it and must still describe the file that was actually scanned.
        if (!array_key_exists('source', $validated)) {
            return;
        }
        $source = SchemaDriftSourceToken::read($validated['source']);
        $event = $this->registry->find($eventId);
        if ($source === null || $event === null
            || strtolower($source['table']) !== strtolower((string) $event->table_name)) {
            throw new RuntimeException('Referensi file tidak cocok dengan keputusan ini atau sudah kedaluwarsa. Pindai ulang preview.');
        }
        if (!is_file($source['path']) || !is_readable($source['path'])) {
            throw new RuntimeException('File sumber untuk keputusan ini sudah tidak tersedia. Buka ulang preview.');
        }
        $expected = (string) ($validated['source_fingerprint'] ?? $event->payload['source_fingerprint'] ?? '');
        $current = @hash_file('sha256', $source['path']);
        if ($expected === '' || !is_string($current) || !hash_equals($expected, $current)) {
            throw new RuntimeException('Isi file sumber sudah berubah sejak pemindaian. Pindai ulang preview sebelum menerapkan perubahan kolom.');
        }
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
