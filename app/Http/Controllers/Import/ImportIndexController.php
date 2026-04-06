<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NamaReport;

class ImportIndexController extends Controller
{
    public function index()
    {
        $reports = NamaReport::where('active', 1)->get();
        $downloadTemplates = $this->downloadTemplateOptions();

        return view('import.index', compact('reports', 'downloadTemplates'));
    }

    public function downloadTemplate(Request $request)
    {
        $templateKey = (string) $request->query('report', '');
        $template = $this->downloadTemplateOptions()[$templateKey] ?? null;

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
        return [
            'input_rekanan' => [
                'label' => 'Input Rekanan',
                'filename' => 'template-input-rekanan.xlsx',
            ],
            'nasabah_prioritas_bod_boc' => [
                'label' => 'Nasabah Prioritas BOD BOC',
                'filename' => 'template-nasabah-prioritas-bod-boc.xlsx',
            ],
        ];
    }
}
