<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class LandingSimpananDomSecurityTest extends TestCase
{
    #[DataProvider('untrustedFields')]
    public function test_nominatif_renderers_escape_source_fields(string $renderer, string $field, ?string $primaryField = null): void
    {
        $record = $this->record($renderer);
        $payload = '<img src="data:image/png;base64,invalid" onerror="window.securityProbe=true"> & " \' <script>probe</script>';
        $record[$field] = $payload;
        if ($primaryField !== null) {
            unset($record[$primaryField]);
        }

        $result = $this->render($renderer, $record);

        $this->assertStringNotContainsString('<img', $result['html']);
        $this->assertStringNotContainsString('<script>', $result['html']);
        $this->assertStringContainsString('&lt;img', $result['html']);
        $this->assertStringContainsString('&amp;', $result['html']);
        $this->assertStringContainsString('&quot;', $result['html']);
        $this->assertStringContainsString('&#39;', $result['html']);
    }

    public static function untrustedFields(): iterable
    {
        foreach (['partner', 'bidang_usaha', 'kc', 'perusahaan_anak', 'status', 'cif', 'saldo_juli_fmt', 'saldo_agustus_fmt', 'saldo_september_fmt'] as $field) {
            yield 'perusahaan anak '.$field => ['company', $field];
        }
        foreach (['namaPartner' => 'partner', 'bidangUsaha' => 'bidang_usaha', 'branchOffice' => 'kc', 'perusahaanAnak' => 'perusahaan_anak', 'cifNo' => 'cif'] as $alias => $primary) {
            yield 'perusahaan anak alias '.$alias => ['company', $alias, $primary];
        }
        foreach (['cabang', 'labelEkosistem', 'nama', 'norek', 'saldo_fmt'] as $field) {
            yield 'ecosystem '.$field => ['ecosystem', $field];
        }
        yield 'ecosystem category fallback' => ['ecosystem', 'ekosistem', 'labelEkosistem'];
    }

    public function test_company_renderer_preserves_values_totals_and_empty_state(): void
    {
        $result = $this->render('company', $this->record('company'));

        $this->assertStringContainsString('Trade &amp; Services', $result['html']);
        $this->assertStringContainsString('0012345', $result['html']);
        $this->assertStringContainsString('Rp 1.250', $result['html']);
        $this->assertSame('Total Saldo (Sep): Rp 1.250', $result['summary']);
        $this->assertSame('Menampilkan 1 mitra', $result['count']);

        $empty = $this->render('company', null);
        $this->assertStringContainsString('Tidak ada data mitra perusahaan anak yang sesuai filter.', $empty['html']);
        $this->assertSame('Menampilkan 0 mitra', $empty['count']);
    }

    public function test_ecosystem_renderer_preserves_values_totals_pagination_and_empty_state(): void
    {
        $result = $this->render('ecosystem', $this->record('ecosystem'));

        $this->assertStringContainsString('School &amp; Partners', $result['html']);
        $this->assertStringContainsString('0001234567', $result['html']);
        $this->assertStringContainsString('Rp 1.250', $result['html']);
        $this->assertStringContainsString('badge-eco-pendidikan', $result['html']);
        $this->assertSame('Total Saldo: Rp 1.250', $result['summary']);
        $this->assertSame('Menampilkan 1 - 1 dari 1 nasabah', $result['count']);
        $this->assertSame(1, $result['pages']);

        $empty = $this->render('ecosystem', null);
        $this->assertStringContainsString('Tidak ada data nasabah ekosistem yang sesuai filter.', $empty['html']);
        $this->assertSame('Menampilkan 0 nasabah', $empty['count']);
    }

    private function record(string $renderer): array
    {
        if ($renderer === 'company') {
            return [
                'partner' => 'Mitra A',
                'bidang_usaha' => 'Trade & Services',
                'kc' => 'KC Madiun',
                'perusahaan_anak' => 'PA',
                'status' => 'Sudah Terakuisisi',
                'cif' => '0012345',
                'saldo_september' => 1250,
                'saldo_juli_fmt' => 'Rp 1.000',
                'saldo_agustus_fmt' => 'Rp 1.100',
                'saldo_september_fmt' => 'Rp 1.250',
            ];
        }

        return [
            'cabang' => 'KC Madiun',
            'ekosistem' => 'Pendidikan',
            'labelEkosistem' => 'Pendidikan',
            'nama' => 'School & Partners',
            'norek' => '0001234567',
            'saldo' => 1250,
            'saldo_fmt' => 'Rp 1.250',
        ];
    }

    private function render(string $renderer, ?array $record): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute the actual nominatif JavaScript renderers.');
        }

        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard/simpanan.blade.php');
        $functionNames = ['escapeNominatifHtml'];
        $functionNames = array_merge($functionNames, $renderer === 'company'
            ? ['filterAndRenderPerusahaanAnakNominatif']
            : ['formatIdrCurrency', 'renderEcoCurrentPage']);
        $functions = [];
        foreach ($functionNames as $name) {
            $matched = preg_match('/^  function '.preg_quote($name, '/').'\([^)]*\) \{.*?^  \}/ms', $source, $matches);
            $this->assertSame(1, $matched, 'Actual renderer function is missing: '.$name);
            $functions[] = $matches[0];
        }

        $records = json_encode($record === null ? [] : [$record], JSON_THROW_ON_ERROR);
        $setup = <<<'JS'
const table = {innerHTML: ''}, summary = {textContent: ''}, count = {textContent: ''};
let pages = null;
JS;
        if ($renderer === 'company') {
            $setup .= "\n".'global.window = {perusahaanAnakRowsData: '.$records.'};'."\n";
            $setup .= <<<'JS'
const paModalTableBody = table, paModalSummaryText = summary, paModalCountText = count;
let activePaBranch = 'ALL', activePaEntity = 'ALL', activePaStatus = 'ALL', activePaSearch = '';
JS;
            $invoke = 'filterAndRenderPerusahaanAnakNominatif();';
        } else {
            $setup .= "\n".'let filteredEcoRecords = '.$records.';'."\n";
            $setup .= <<<'JS'
const ecoModalTableBody = table, ecoModalSummaryText = summary, ecoModalCountText = count;
let activeEcoPage = 1;
const ecoPageSize = 25;
function renderEcoPaginationControls(value) { pages = value; }
JS;
            $invoke = 'renderEcoCurrentPage();';
        }

        $script = $setup."\n".implode("\n", $functions)."\n".$invoke."\n"
            .'process.stdout.write(JSON.stringify({html: table.innerHTML, summary: summary.textContent, count: count.textContent, pages}));';
        $process = new Process([$node, '-e', $script]);
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
