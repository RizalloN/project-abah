<?php

namespace App\Console\Commands;

use App\Support\KnowledgeGraph\KnowledgeGraphIndex;
use App\Support\KnowledgeGraph\RepositoryKnowledgeGraphBuilder;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Throwable;

final class KnowledgeGraphCommand extends Command
{
    protected $signature = 'knowledge:graph
        {query? : Symbol, method, path, route, view, table, command, or domain to inspect}
        {--build : Rebuild all generated knowledge graph artifacts}
        {--check : Exit successfully only when the generated graph matches current sources}
        {--domain= : Restrict query results to one generated domain}
        {--depth=1 : Neighbor traversal depth from 0 to 4}
        {--limit=60 : Maximum number of nodes returned}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Build, validate, and query the Project ABAH repository knowledge graph';

    public function handle(Router $router): int
    {
        $builder = new RepositoryKnowledgeGraphBuilder(base_path());

        try {
            if ((bool) $this->option('build')) {
                $manifest = $builder->write($this->routes($router));
                if ((bool) $this->option('json')) {
                    $this->line((string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                    return self::SUCCESS;
                }

                $this->components->info('Knowledge graph rebuilt.');
                $this->table(['Metric', 'Count / Value'], [
                    ['Source files', number_format((int) $manifest['source_files'])],
                    ['Nodes', number_format((int) $manifest['node_count'])],
                    ['Edges', number_format((int) $manifest['edge_count'])],
                    ['Domains', number_format(count((array) $manifest['domains']))],
                    ['Fingerprint', (string) $manifest['source_fingerprint']],
                    ['Entry point', (string) $manifest['entrypoint']],
                ]);

                return self::SUCCESS;
            }

            if ((bool) $this->option('check')) {
                $valid = $builder->graphIsValid();
                $fresh = $valid && $builder->graphIsFresh();
                $payload = [
                    'fresh' => $fresh,
                    'valid' => $valid,
                    'graph_path' => $builder->resolvedOutputPath(),
                    'current_fingerprint' => $builder->currentFingerprint(),
                ];
                if ((bool) $this->option('json')) {
                    $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } elseif ($fresh) {
                    $this->components->info('Knowledge graph is current.');
                } elseif (! $valid) {
                    $this->components->error('Knowledge graph is missing or structurally invalid. Run: php artisan knowledge:graph --build');
                } else {
                    $this->components->error('Knowledge graph is stale. Run: php artisan knowledge:graph --build');
                }

                return $fresh ? self::SUCCESS : self::FAILURE;
            }

            if (! $builder->graphIsValid()) {
                $this->components->error('Knowledge graph is missing or structurally invalid. Run: php artisan knowledge:graph --build');

                return self::FAILURE;
            }

            $index = new KnowledgeGraphIndex($builder->resolvedOutputPath());
            if (! $builder->graphIsFresh()) {
                $this->components->warn('Knowledge graph is stale. Results remain searchable, but rebuild before relying on them.');
            }

            $query = trim((string) ($this->argument('query') ?? ''));
            $domain = $this->option('domain');
            if ($query === '' && ($domain === null || trim((string) $domain) === '')) {
                return $this->showOverview($index);
            }

            $result = $index->query(
                $query,
                $domain !== null ? trim((string) $domain) : null,
                (int) $this->option('depth'),
                (int) $this->option('limit')
            );
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                $this->line($index->format($result));
            }

            return $result['matches'] === [] ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function showOverview(KnowledgeGraphIndex $index): int
    {
        $manifest = $index->manifest();
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('Project ABAH knowledge graph');
        $this->table(['Metric', 'Count'], [
            ['Source files', number_format((int) ($manifest['source_files'] ?? 0))],
            ['Nodes', number_format((int) ($manifest['node_count'] ?? 0))],
            ['Edges', number_format((int) ($manifest['edge_count'] ?? 0))],
        ]);
        $this->table(
            ['Domain', 'Nodes'],
            collect((array) ($manifest['domains'] ?? []))
                ->map(fn (int $count, string $domain): array => [$domain, number_format($count)])
                ->values()
                ->all()
        );
        $this->line('Query example: php artisan knowledge:graph DashboardHarianController --depth=2');

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function routes(Router $router): array
    {
        $routes = [];
        foreach ($router->getRoutes() as $route) {
            $action = $route->getActionName();
            if ($action !== 'Closure' && ! str_contains($action, '@') && class_exists($action)) {
                $action .= '@__invoke';
            }
            $routes[] = [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'action' => $action,
                'middleware' => array_values($route->middleware()),
            ];
        }

        return $routes;
    }
}
