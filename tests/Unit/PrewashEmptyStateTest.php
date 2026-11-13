<?php

namespace Tests\Unit;

use App\Support\LandingMicroPrewashBohService;
use App\Support\LandingMicroPrewashMbmService;
use App\Support\UserBranchScope;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrewashEmptyStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        foreach (['pipeline_prewash', 'pipeline_prewash_boh', 'monitoring_tl_pipeline_mbm'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->string('uniqueid_namareport')->primary();
                $table->unsignedSmallInteger('tahun');
                $table->string('nama_kanca');
                $table->string('cif')->nullable();
                $table->string('plafond')->nullable();
            });
        }
        DB::table('pipeline_prewash')->insert(['uniqueid_namareport' => 'original', 'tahun' => 2026, 'nama_kanca' => 'KC Madiun', 'cif' => 'ORIGINAL', 'plafond' => '9007199254740993.01']);
    }

    public static function reports(): array
    {
        return [
            'boh' => [LandingMicroPrewashBohService::class, 'pipeline_prewash_boh', 'PIC BOH'],
            'mbm' => [LandingMicroPrewashMbmService::class, 'monitoring_tl_pipeline_mbm', 'PIC MBM'],
        ];
    }

    #[DataProvider('reports')]
    public function test_empty_tl_report_directs_import_without_borrowing_original_pipeline(string $service, string $table, string $label): void
    {
        $original = (array) DB::table('pipeline_prewash')->first();

        $payload = app($service)->payload(null);

        $this->assertFalse($payload['available']);
        $this->assertSame('not_imported', $payload['empty_reason']);
        $this->assertStringContainsString($label, $payload['empty_title']);
        $this->assertStringContainsString('Impor laporan', $payload['message']);
        $this->assertStringNotContainsString('cabang yang dipilih', $payload['message']);
        $this->assertSame([], $payload['summary']);
        $this->assertSame(0, DB::table($table)->count());
        $this->assertSame($original, (array) DB::table('pipeline_prewash')->first());
        $xpath = $this->render($payload);
        $this->assertSame(0, $xpath->query('//table')->length);
        $this->assertSame(1, $xpath->query('//a[@class="pipeline-prewash__import"]')->length);
        $this->assertSame(route('import.index'), $xpath->evaluate('string(//a/@href)'));
        $this->assertStringContainsString($label, $xpath->evaluate('string(//a/@aria-label)'));
    }

    #[DataProvider('reports')]
    public function test_other_branch_data_is_not_labelled_unimported_and_is_preserved(string $service, string $table, string $label): void
    {
        DB::table($table)->insert(['uniqueid_namareport' => 'other-branch', 'tahun' => 2026, 'nama_kanca' => 'KC Ponorogo', 'cif' => 'C001', 'plafond' => '123456789.01']);
        $before = (array) DB::table($table)->first();

        $payload = app($service)->payload(UserBranchScope::forKey('madiun'));

        $this->assertFalse($payload['available']);
        $this->assertSame('no_matching_scope', $payload['empty_reason']);
        $this->assertStringContainsString('cabang yang dipilih', $payload['message']);
        $this->assertSame($before, (array) DB::table($table)->first());
        $this->assertSame(0, $this->render($payload)->query('//a[@class="pipeline-prewash__import"]')->length);
    }

    private function render(array $payload): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.view('dashboard.partials.pipeline-prewash', ['pipelinePrewash' => $payload])->render());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
