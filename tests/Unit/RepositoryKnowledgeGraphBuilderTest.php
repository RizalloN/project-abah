<?php

namespace Tests\Unit;

use App\Support\KnowledgeGraph\KnowledgeGraphIndex;
use App\Support\KnowledgeGraph\RepositoryKnowledgeGraphBuilder;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class RepositoryKnowledgeGraphBuilderTest extends TestCase
{
    private string $repositoryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'project-abah-knowledge-graph-'.bin2hex(random_bytes(6));
        mkdir($this->repositoryPath, 0777, true);

        $this->writeFixture('app/Services/ReportService.php', <<<'PHP'
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class ReportService
{
    public function rows(): array
    {
        $example = "DB::table('fake_table')";
        // DB::table('comment_table') must not become graph data.

        return DB::table('reports')->get()->all();
    }
}
PHP);

        $this->writeFixture('app/Http/Controllers/ReportController.php', <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Services\ReportService;

final class ReportController
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index()
    {
        $rows = $this->reports->rows();

        return view('report.index', compact('rows'));
    }
}
PHP);

        $this->writeFixture('resources/views/report/index.blade.php', <<<'BLADE'
@extends('layouts.admin')

<a href="{{ route('report.index') }}">Report</a>
BLADE);

        $this->writeFixture('database/schema.sql', <<<'SQL'
-- Read from the source table after deployment.
CREATE TABLE IF NOT EXISTS reports (id BIGINT PRIMARY KEY);
SELECT * FROM reports;
SQL);
    }

    protected function tearDown(): void
    {
        if (isset($this->repositoryPath) && is_dir($this->repositoryPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->repositoryPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($this->repositoryPath);
        }

        parent::tearDown();
    }

    public function test_builder_connects_source_backed_repository_relations(): void
    {
        $result = $this->builder()->build($this->routes());
        $nodes = array_column($result['nodes'], null, 'id');

        $this->assertArrayHasKey('symbol:App\\Services\\ReportService', $nodes);
        $this->assertArrayHasKey('method:App\\Services\\ReportService::rows', $nodes);
        $this->assertArrayHasKey('symbol:App\\Http\\Controllers\\ReportController', $nodes);
        $this->assertArrayHasKey('method:App\\Http\\Controllers\\ReportController::index', $nodes);
        $this->assertArrayHasKey('route:report.index', $nodes);
        $this->assertArrayHasKey('view:report.index', $nodes);
        $this->assertArrayHasKey('table:reports', $nodes);
        $this->assertArrayNotHasKey('table:fake_table', $nodes);
        $this->assertArrayNotHasKey('table:comment_table', $nodes);
        $this->assertArrayNotHasKey('table:the', $nodes);

        $this->assertGraphHasEdge(
            $result['edges'],
            'method:App\\Http\\Controllers\\ReportController::__construct',
            'injects',
            'symbol:App\\Services\\ReportService'
        );
        $this->assertGraphHasEdge(
            $result['edges'],
            'method:App\\Http\\Controllers\\ReportController::index',
            'calls',
            'method:App\\Services\\ReportService::rows'
        );
        $this->assertGraphHasEdge(
            $result['edges'],
            'method:App\\Services\\ReportService::rows',
            'reads_table',
            'table:reports'
        );
        $this->assertGraphHasEdge(
            $result['edges'],
            'method:App\\Http\\Controllers\\ReportController::index',
            'renders',
            'view:report.index'
        );
        $this->assertGraphHasEdge(
            $result['edges'],
            'route:report.index',
            'dispatches_to',
            'method:App\\Http\\Controllers\\ReportController::index'
        );
    }

    public function test_written_graph_is_queryable_and_detects_source_drift(): void
    {
        $builder = $this->builder();
        $manifest = $builder->write($this->routes());

        $this->assertSame(4, $manifest['source_files']);
        $this->assertTrue($builder->graphIsValid());
        $this->assertTrue($builder->graphIsFresh());

        $index = new KnowledgeGraphIndex($builder->resolvedOutputPath());
        $result = $index->query('ReportController', null, 2, 30);

        $this->assertSame('symbol:App\\Http\\Controllers\\ReportController', $result['matches'][0]['id']);
        $this->assertContains('route:report.index', array_column($result['nodes'], 'id'));
        $this->assertContains('view:report.index', array_column($result['nodes'], 'id'));

        file_put_contents(
            $this->repositoryPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'ReportService.php',
            "\n// Source changed after graph generation.\n",
            FILE_APPEND
        );

        $this->assertTrue($builder->graphIsValid());
        $this->assertFalse($builder->graphIsFresh());
    }

    /** @return array<int, array<string, mixed>> */
    private function routes(): array
    {
        return [[
            'name' => 'report.index',
            'uri' => 'reports',
            'methods' => ['GET', 'HEAD'],
            'middleware' => ['web', 'auth'],
            'action' => 'App\\Http\\Controllers\\ReportController@index',
        ]];
    }

    private function builder(): RepositoryKnowledgeGraphBuilder
    {
        return new RepositoryKnowledgeGraphBuilder(
            $this->repositoryPath,
            $this->repositoryPath.DIRECTORY_SEPARATOR.'knowledge-graph'
        );
    }

    private function writeFixture(string $relativePath, string $contents): void
    {
        $path = $this->repositoryPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
    }

    /** @param array<int, array<string, mixed>> $edges */
    private function assertGraphHasEdge(array $edges, string $from, string $relation, string $to): void
    {
        foreach ($edges as $edge) {
            if ($edge['from'] === $from && $edge['relation'] === $relation && $edge['to'] === $to) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("Missing graph edge: {$from} --{$relation}--> {$to}");
    }
}
