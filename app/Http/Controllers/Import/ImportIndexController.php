<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NamaReport;

class ImportIndexController extends Controller
{
    private const TEMPLATE_DEFINITIONS = [
        'input_rekanan' => [
            'label' => 'Input Rekanan',
            'filename' => 'template-input-rekanan.xlsx',
            'aliases' => ['input-rekanan', 'template-input-rekanan'],
        ],
        'nasabah_prioritas_bod_boc' => [
            'label' => 'Nasabah Prioritas BOD BOC',
            'filename' => 'template-nasabah-prioritas-bod-boc.xlsx',
            'aliases' => [
                'nasabah-prioritas-bod-boc',
                'template-nasabah-prioritas-bod-boc',
                'nasabah prioritas bod boc',
            ],
        ],
    ];

    public function index()
    {
        $reports = NamaReport::where('active', 1)->get();
        $downloadTemplates = $this->downloadTemplateOptions();

        return view('import.index', compact('reports', 'downloadTemplates'));
    }

    public function downloadTemplate(Request $request)
    {
        $templateKey = (string) $request->query('report', '');
        $requestedFilename = (string) $request->query('file', '');
        $template = $this->resolveTemplateOption($templateKey, $requestedFilename);

        if (!$template) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Template report yang dipilih tidak tersedia.');
        }

        $templatePath = resource_path('templates/import/' . $template['filename']);

        if (!is_file($templatePath)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'File template untuk report tersebut belum tersedia di project.');
        }

        return response()->download(
            $templatePath,
            $template['filename'],
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function downloadTemplateOptions(): array
    {
        return collect(self::TEMPLATE_DEFINITIONS)
            ->map(function (array $template) {
                return [
                    'label' => $template['label'],
                    'filename' => $template['filename'],
                ];
            })
            ->all();
    }

    private function resolveTemplateOption(string $templateKey, string $requestedFilename = ''): ?array
    {
        $normalizedKey = $this->normalizeTemplateKey($templateKey);
        $normalizedFilename = $this->normalizeTemplateKey($requestedFilename);

        foreach (self::TEMPLATE_DEFINITIONS as $key => $template) {
            $filename = (string) ($template['filename'] ?? '');

            if ($normalizedFilename !== '' && $normalizedFilename === $this->normalizeTemplateKey($filename)) {
                return [
                    'label' => $template['label'],
                    'filename' => $filename,
                ];
            }

            $candidates = array_filter([
                $key,
                $template['label'] ?? null,
                $filename,
                ...($template['aliases'] ?? []),
            ]);

            foreach ($candidates as $candidate) {
                if ($normalizedKey === $this->normalizeTemplateKey((string) $candidate)) {
                    return [
                        'label' => $template['label'],
                        'filename' => $template['filename'],
                    ];
                }
            }
        }

        return null;
    }

    private function normalizeTemplateKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\.xlsx$/', '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
