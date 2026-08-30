<?php

namespace App\Support\KnowledgeGraph;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class RepositoryKnowledgeGraphBuilder
{
    public const SCHEMA_VERSION = 1;

    /** @var array<int, string> */
    private const SOURCE_DIRECTORIES = [
        'app', 'bootstrap', 'config', 'database', 'resources/views', 'routes',
        'scripts', 'tests', 'tools',
    ];

    /** @var array<int, string> */
    private const ROOT_FILES = [
        'AGENTS.md', 'PROJECT_MAP.md', 'composer.json', 'package.json', 'phpunit.xml', 'vite.config.js',
    ];

    /** @var array<int, string> */
    private const EXTENSIONS = [
        'php', 'blade.php', 'js', 'mjs', 'json', 'md', 'ps1', 'py', 'sql', 'xml',
    ];

    /** @var array<string, array<int, string>> */
    private const DOMAIN_RULES = [
        'knowledge-graph' => ['knowledgegraph', 'knowledge-graph', 'project_map'],
        'import' => ['import', 'brimo', 'brilink', 'merchant', 'delimiter', 'csv', 'rar'],
        'dashboard-harian' => ['dashboardharian', 'dashboard-harian', 'keragaan', 'hourlydpk', 'hourly-dpk'],
        'dashboard-simpanan' => ['dashboardsimpanan', 'dashboard-simpanan', 'dashboarddana', 'dashboard-dana', 'simpanan'],
        'dashboard-pinjaman' => ['dashboardpinjaman', 'dashboard-pinjaman', 'ssa-pinjaman', 'dailyloan', 'daily-loan', 'lw325', 'kinerjarm', 'matrix'],
        'marketshare' => ['marketshare', 'market-share', 'cras', 'sektoral', 'mapping'],
        'almafacts' => ['almafacts', 'financial-highlight', 'kinerja-laba-rugi'],
        'kpi' => ['kpi', 'scorecard', 'personnelreference'],
        'prognosa' => ['prognosa', 'weekly'],
        'bank-pipeline' => ['driveasix', 'drive-asix', 'bankpipeline', 'bank-pipeline', 'onlyoffice', 'workbook'],
        'jobs-snapshots' => ['snapshot', 'queue', 'jobmanagement', 'job-management', 'worker', 'backfill'],
        'access-control' => ['auth', 'login', 'user-management', 'usermanagement', 'role', 'branchscope', 'branch-scope', 'security'],
        'presentation' => ['presentation', 'ppt', 'powerpoint'],
        'input-management' => ['bod-boc', 'businesscluster', 'business-cluster', 'app/http/controllers/input'],
        'database' => ['database/migrations', 'database/seeders', 'schema', 'backup'],
        'tests' => ['tests/', 'test.php'],
        'routing' => ['routes/', 'route:'],
        'platform' => ['bootstrap/', 'config/', 'provider', 'middleware'],
    ];

    public function __construct(
        private readonly string $basePath,
        private readonly ?string $outputPath = null
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @return array{manifest: array<string, mixed>, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function build(array $routes = []): array
    {
        $graph = new KnowledgeGraph;
        $analyzer = new PhpSourceAnalyzer(fn (string $text): string => $this->domainFor($text));
        $files = $this->sourceFiles();

        foreach ($files as $relativePath) {
            $absolutePath = $this->absolutePath($relativePath);
            $source = file_get_contents($absolutePath);
            if ($source === false) {
                continue;
            }

            $fileId = 'file:'.$relativePath;
            $graph->addNode($fileId, 'file', basename($relativePath), [
                'path' => $relativePath,
                'language' => $this->languageFor($relativePath),
                'domain' => $this->domainFor($relativePath),
                'lines' => substr_count($source, "\n") + 1,
                'bytes' => strlen($source),
            ]);

            if (str_ends_with(strtolower($relativePath), '.blade.php')) {
                $this->analyzeBlade($relativePath, $source, $graph);
            } elseif (str_ends_with(strtolower($relativePath), '.php')) {
                $analyzer->analyze($relativePath, $source, $graph);
            } else {
                $this->analyzeOperationalFile($relativePath, $source, $graph);
            }
        }

        $this->analyzeRuntimeRoutes($routes, $graph);
        $this->attachDomains($graph);

        $nodes = $graph->nodes();
        $edges = $graph->edges();
        $manifest = $this->manifest($files, $nodes, $edges);

        return compact('manifest', 'nodes', 'edges');
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @return array<string, mixed>
     */
    public function write(array $routes = []): array
    {
        $result = $this->build($routes);
        $outputPath = $this->resolvedOutputPath();
        $domainPath = $outputPath.DIRECTORY_SEPARATOR.'domains';
        $this->ensureDirectory($outputPath);
        $this->ensureDirectory($domainPath);

        $this->writeJson($outputPath.DIRECTORY_SEPARATOR.'manifest.json', $result['manifest']);
        $this->writeJsonLines($outputPath.DIRECTORY_SEPARATOR.'nodes.jsonl', $result['nodes']);
        $this->writeJsonLines($outputPath.DIRECTORY_SEPARATOR.'edges.jsonl', $result['edges']);
        $this->writeText($outputPath.DIRECTORY_SEPARATOR.'README.md', $this->readme($result));
        $this->writeText($outputPath.DIRECTORY_SEPARATOR.'ARCHITECTURE.md', $this->architectureDocument($result));
        $this->writeText($outputPath.DIRECTORY_SEPARATOR.'ROUTES.md', $this->routesDocument($result));
        $this->writeText($outputPath.DIRECTORY_SEPARATOR.'DATA.md', $this->dataDocument($result));
        $this->writeDomainDocuments($domainPath, $result);

        return $result['manifest'];
    }

    public function currentFingerprint(): string
    {
        return $this->fingerprint($this->sourceFiles());
    }

    public function graphIsFresh(): bool
    {
        $manifestPath = $this->resolvedOutputPath().DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            return false;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($manifest)
            && ($manifest['schema_version'] ?? null) === self::SCHEMA_VERSION
            && hash_equals((string) ($manifest['source_fingerprint'] ?? ''), $this->currentFingerprint());
    }

    public function graphIsValid(): bool
    {
        $graphPath = $this->resolvedOutputPath();
        $manifestPath = $graphPath.DIRECTORY_SEPARATOR.'manifest.json';
        $nodesPath = $graphPath.DIRECTORY_SEPARATOR.'nodes.jsonl';
        $edgesPath = $graphPath.DIRECTORY_SEPARATOR.'edges.jsonl';
        if (! is_file($manifestPath) || ! is_file($nodesPath) || ! is_file($edgesPath)) {
            return false;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return false;
        }

        $nodeIds = [];
        $nodeCount = 0;
        $nodeHandle = fopen($nodesPath, 'rb');
        if ($nodeHandle === false) {
            return false;
        }
        $nodesAreValid = true;
        while (($line = fgets($nodeHandle)) !== false) {
            $node = json_decode(trim($line), true);
            if (! is_array($node) || ! isset($node['id']) || isset($nodeIds[$node['id']])) {
                $nodesAreValid = false;
                break;
            }
            $nodeIds[$node['id']] = true;
            $nodeCount++;
        }
        fclose($nodeHandle);
        if (! $nodesAreValid || $nodeCount !== (int) ($manifest['node_count'] ?? -1)) {
            return false;
        }

        $edgeKeys = [];
        $edgeCount = 0;
        $edgeHandle = fopen($edgesPath, 'rb');
        if ($edgeHandle === false) {
            return false;
        }
        $edgesAreValid = true;
        while (($line = fgets($edgeHandle)) !== false) {
            $edge = json_decode(trim($line), true);
            if (! is_array($edge) || ! isset($edge['from'], $edge['relation'], $edge['to'])
                || ! isset($nodeIds[$edge['from']], $nodeIds[$edge['to']])) {
                $edgesAreValid = false;
                break;
            }
            $key = $edge['from']."\0".$edge['relation']."\0".$edge['to'];
            if (isset($edgeKeys[$key])) {
                $edgesAreValid = false;
                break;
            }
            $edgeKeys[$key] = true;
            $edgeCount++;
        }
        fclose($edgeHandle);

        return $edgesAreValid && $edgeCount === (int) ($manifest['edge_count'] ?? -1);
    }

    public function resolvedOutputPath(): string
    {
        return $this->outputPath ?: $this->basePath.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'knowledge-graph';
    }

    /** @return array<int, string> */
    private function sourceFiles(): array
    {
        $files = [];
        foreach (self::SOURCE_DIRECTORIES as $directory) {
            $absolute = $this->absolutePath($directory);
            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $relative = $this->relativePath($file->getPathname());
                if ($this->isIncludedSource($relative)) {
                    $files[] = $relative;
                }
            }
        }

        foreach (self::ROOT_FILES as $file) {
            if (is_file($this->absolutePath($file))) {
                $files[] = $file;
            }
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    private function isIncludedSource(string $relativePath): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $relativePath));
        if (str_starts_with($normalized, 'docs/knowledge-graph/')) {
            return false;
        }

        foreach (self::EXTENSIONS as $extension) {
            if (str_ends_with($normalized, '.'.$extension)) {
                return true;
            }
        }

        return false;
    }

    private function analyzeBlade(string $relativePath, string $source, KnowledgeGraph $graph): void
    {
        $viewName = str_replace('/', '.', substr(str_replace('\\', '/', $relativePath), strlen('resources/views/')));
        $viewName = preg_replace('/\.blade\.php$/', '', $viewName) ?? $viewName;
        $viewId = 'view:'.$viewName;
        $fileId = 'file:'.$relativePath;
        $domain = $this->domainFor($relativePath.' '.$viewName);
        $graph->addNode($viewId, 'view', $viewName, [
            'path' => $relativePath,
            'domain' => $domain,
            'status' => 'defined',
        ]);
        $graph->addEdge($fileId, 'defines', $viewId, ['path' => $relativePath, 'line' => 1]);

        $patterns = [
            'extends_view' => '/@extends\(\s*[\'\"](?<name>[A-Za-z0-9_.\/-]+)[\'\"]/',
            'includes_view' => '/@(?:include|includeIf|includeWhen|component|each)\(\s*[\'\"](?<name>[A-Za-z0-9_.\/-]+)[\'\"]/',
            'references_route' => '/route\(\s*[\'\"](?<name>[A-Za-z0-9_.-]+)[\'\"]/',
        ];

        foreach ($patterns as $relation => $pattern) {
            if (! preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches as $match) {
                $name = $match['name'][0];
                $targetId = $relation === 'references_route' ? 'route:'.$name : 'view:'.$name;
                $kind = $relation === 'references_route' ? 'route' : 'view';
                $graph->addNode($targetId, $kind, $name, [
                    'domain' => $this->domainFor($name),
                    'status' => 'referenced',
                ]);
                $graph->addEdge($viewId, $relation, $targetId, [
                    'path' => $relativePath,
                    'line' => $this->lineAt($source, $match[0][1]),
                ]);
            }
        }

        if (preg_match_all('/<x-(?<name>[A-Za-z0-9_.:-]+)/', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $name = 'components.'.str_replace([':', '-'], '.', $match['name'][0]);
                $targetId = 'view:'.$name;
                $graph->addNode($targetId, 'view', $name, [
                    'domain' => $this->domainFor($name),
                    'status' => 'referenced',
                ]);
                $graph->addEdge($viewId, 'uses_component', $targetId, [
                    'path' => $relativePath,
                    'line' => $this->lineAt($source, $match[0][1]),
                ]);
            }
        }
    }

    private function analyzeOperationalFile(string $relativePath, string $source, KnowledgeGraph $graph): void
    {
        $fileId = 'file:'.$relativePath;
        if (preg_match_all('/(?:php(?:\.exe)?\s+)?artisan\s+(?<command>[A-Za-z0-9:_-]+)/i', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $command = $match['command'][0];
                $commandId = 'command:'.$command;
                $graph->addNode($commandId, 'command', $command, [
                    'domain' => $this->domainFor($command),
                    'status' => 'referenced',
                ]);
                $graph->addEdge($fileId, 'invokes_command', $commandId, [
                    'path' => $relativePath,
                    'line' => $this->lineAt($source, $match[0][1]),
                ]);
            }
        }

        if (str_ends_with(strtolower($relativePath), '.sql')) {
            $sql = preg_replace_callback(
                '/--[^\r\n]*|#[^\r\n]*|\/\*.*?\*\//s',
                static fn (array $match): string => str_repeat(' ', strlen($match[0])),
                $source
            ) ?? $source;
        }

        if (isset($sql)
            && preg_match_all(
                '/\b(?:from|join|into|update|table)\s+(?:if\s+(?:not\s+)?exists\s+)?[\x60\[]?(?<table>[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)/i',
                $sql,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            )) {
            foreach ($matches as $match) {
                $table = $match['table'][0];
                if (in_array(strtolower($table), ['if', 'on', 'select', 'set', 'values', 'where'], true)) {
                    continue;
                }
                $tableId = 'table:'.strtolower($table);
                $graph->addNode($tableId, 'table', $table, ['domain' => $this->domainFor($table)]);
                $graph->addEdge($fileId, 'uses_table', $tableId, [
                    'path' => $relativePath,
                    'line' => $this->lineAt($source, $match[0][1]),
                ]);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $routes */
    private function analyzeRuntimeRoutes(array $routes, KnowledgeGraph $graph): void
    {
        foreach ($routes as $route) {
            $name = trim((string) ($route['name'] ?? ''));
            $uri = trim((string) ($route['uri'] ?? ''));
            $methods = array_values(array_filter((array) ($route['methods'] ?? [])));
            $routeId = 'route:'.($name !== '' ? $name : implode('|', $methods).' '.$uri);
            $domain = $this->domainFor($name.' '.$uri.' '.($route['action'] ?? ''));
            $graph->addNode($routeId, 'route', $name !== '' ? $name : $uri, [
                'route_name' => $name ?: null,
                'uri' => $uri,
                'methods' => $methods,
                'middleware' => array_values((array) ($route['middleware'] ?? [])),
                'domain' => $domain,
                'status' => 'defined',
            ]);

            $action = trim((string) ($route['action'] ?? ''));
            if ($action !== '' && $action !== 'Closure' && str_contains($action, '@')) {
                [$class, $method] = explode('@', $action, 2);
                $class = ltrim($class, '\\');
                $methodId = 'method:'.$class.'::'.$method;
                $graph->addNode('symbol:'.$class, 'unresolved_symbol', $this->shortName($class), [
                    'fqn' => $class,
                    'domain' => $this->domainFor($class),
                    'status' => str_starts_with($class, 'App\\') ? 'unresolved' : 'external',
                ]);
                $graph->addNode($methodId, 'unresolved_symbol', $method, [
                    'fqn' => $class.'::'.$method,
                    'domain' => $this->domainFor($class),
                    'status' => 'unresolved',
                ]);
                $graph->addEdge('symbol:'.$class, 'contains', $methodId);
                $graph->addEdge($routeId, 'dispatches_to', $methodId);
            }

            foreach ((array) ($route['middleware'] ?? []) as $middleware) {
                $middlewareName = preg_replace('/:.+$/', '', (string) $middleware) ?? (string) $middleware;
                if ($middlewareName === '') {
                    continue;
                }
                $middlewareId = 'middleware:'.$middlewareName;
                $graph->addNode($middlewareId, 'middleware', $middlewareName, [
                    'domain' => 'access-control',
                ]);
                $graph->addEdge($routeId, 'protected_by', $middlewareId);
            }
        }
    }

    private function attachDomains(KnowledgeGraph $graph): void
    {
        foreach ($graph->nodes() as $node) {
            if ($node['kind'] === 'domain') {
                continue;
            }
            $domain = (string) ($node['domain'] ?? 'core');
            $domainId = 'domain:'.$domain;
            $graph->addNode($domainId, 'domain', $domain, ['domain' => $domain]);
            $graph->addEdge($node['id'], 'belongs_to', $domainId);
        }
    }

    /**
     * @param  array<int, string>  $files
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, mixed>
     */
    private function manifest(array $files, array $nodes, array $edges): array
    {
        $nodeKinds = $this->countBy($nodes, 'kind');
        $relations = $this->countBy($edges, 'relation');
        $domains = $this->countBy(array_filter($nodes, fn (array $node): bool => $node['kind'] !== 'domain'), 'domain', 'core');

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'project' => basename($this->basePath),
            'source_fingerprint' => $this->fingerprint($files),
            'source_files' => count($files),
            'node_count' => count($nodes),
            'edge_count' => count($edges),
            'node_kinds' => $nodeKinds,
            'relations' => $relations,
            'domains' => $domains,
            'entrypoint' => 'docs/knowledge-graph/README.md',
            'query_command' => 'php artisan knowledge:graph <query> --depth=1 --limit=60',
            'build_command' => 'php artisan knowledge:graph --build',
            'check_command' => 'php artisan knowledge:graph --check',
        ];
    }

    /** @param array{manifest: array<string, mixed>, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>} $result */
    private function readme(array $result): string
    {
        $manifest = $result['manifest'];
        $hubs = $this->topHubs($result['nodes'], $result['edges'], 20);
        $lines = [
            '# Project ABAH Knowledge Graph',
            '',
            'Generated, deterministic repository map for coding assistants and maintainers. It connects files, PHP symbols, methods, routes, views, database tables, commands, queues, and their source-backed relations.',
            '',
            '## Fast Start',
            '',
            '1. Read this file, then open only the relevant domain file under `domains/`.',
            '2. Query a focused neighborhood instead of reading the whole graph:',
            '',
            '```powershell',
            'php artisan knowledge:graph DashboardHarianController',
            'php artisan knowledge:graph ssa_pinjaman --depth=2 --limit=80',
            'php artisan knowledge:graph import --domain=import --limit=60',
            '```',
            '',
            '3. Before relying on generated knowledge, verify freshness and structural integrity:',
            '',
            '```powershell',
            'php artisan knowledge:graph --check',
            '```',
            '',
            'If stale, rebuild with `php artisan knowledge:graph --build`.',
            '',
            '## Repository Scale',
            '',
            '| Metric | Count |',
            '| --- | ---: |',
            '| Source files | '.number_format((int) $manifest['source_files']).' |',
            '| Graph nodes | '.number_format((int) $manifest['node_count']).' |',
            '| Graph edges | '.number_format((int) $manifest['edge_count']).' |',
            '| Domains | '.number_format(count((array) $manifest['domains'])).' |',
            '',
            '## Read The Right Artifact',
            '',
            '| Need | Open |',
            '| --- | --- |',
            '| System-level mental model | [ARCHITECTURE.md](ARCHITECTURE.md) |',
            '| HTTP entry points | [ROUTES.md](ROUTES.md) |',
            '| Tables and data consumers | [DATA.md](DATA.md) |',
            '| One bounded business area | `domains/<domain>.md` |',
            '| Exact machine lookup | `nodes.jsonl` and `edges.jsonl` |',
            '| Counts and freshness hash | `manifest.json` |',
            '',
            '## Highest Connectivity Symbols',
            '',
            '| Node | Kind | Domain | Degree |',
            '| --- | --- | --- | ---: |',
        ];
        foreach ($hubs as $hub) {
            $lines[] = '| `'.$this->escapeMarkdown((string) $hub['name']).'` | '.$hub['kind'].' | '.$hub['domain'].' | '.$hub['degree'].' |';
        }
        $lines = array_merge($lines, [
            '',
            '## Edge Semantics',
            '',
            '`dispatches_to` connects routes to controller methods; `calls` connects methods; `injects` and `accepts` show typed dependencies; `renders`, `includes_view`, and `references_route` connect UI flow; table relations identify reads, writes, schema definitions, and model mappings; `dispatches` and `uses_queue` expose asynchronous flow.',
            '',
            'Every relation with source evidence includes a repository path and line. The graph is navigation evidence, not a replacement for validating business rules or runtime data.',
            '',
        ]);

        return implode("\n", $lines);
    }

    /** @param array{manifest: array<string, mixed>, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>} $result */
    private function architectureDocument(array $result): string
    {
        $domainEdges = [];
        $nodesById = array_column($result['nodes'], null, 'id');
        foreach ($result['edges'] as $edge) {
            if ($edge['relation'] === 'belongs_to') {
                continue;
            }
            $fromNode = $nodesById[$edge['from']] ?? null;
            $toNode = $nodesById[$edge['to']] ?? null;
            if ($fromNode === null || $toNode === null
                || $fromNode['kind'] === 'unresolved_symbol'
                || $toNode['kind'] === 'unresolved_symbol') {
                continue;
            }
            $fromDomain = $fromNode['domain'] ?? 'core';
            $toDomain = $toNode['domain'] ?? 'core';
            if ($fromDomain === $toDomain) {
                continue;
            }
            $key = $fromDomain.'|'.$toDomain;
            $domainEdges[$key] = ($domainEdges[$key] ?? 0) + 1;
        }
        arsort($domainEdges);

        $lines = [
            '# Architecture Map',
            '',
            'This is a compact cross-domain view. Edge labels are static relation counts, not runtime traffic.',
            '',
            '```mermaid',
            'flowchart LR',
        ];
        foreach (array_slice($domainEdges, 0, 40, true) as $key => $count) {
            [$from, $to] = explode('|', $key, 2);
            $lines[] = '    '.$this->mermaidId($from).'["'.$from.'"] -->|'.$count.'| '.$this->mermaidId($to).'["'.$to.'"]';
        }
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## Domain Index';
        $lines[] = '';
        $lines[] = '| Domain | Nodes | Detail |';
        $lines[] = '| --- | ---: | --- |';
        foreach ((array) $result['manifest']['domains'] as $domain => $count) {
            $lines[] = '| '.$domain.' | '.$count.' | [open](domains/'.$domain.'.md) |';
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>} $result */
    private function routesDocument(array $result): string
    {
        $routes = array_values(array_filter($result['nodes'], fn (array $node): bool => $node['kind'] === 'route' && ($node['status'] ?? '') === 'defined'));
        usort($routes, fn (array $a, array $b): int => [($a['domain'] ?? ''), ($a['route_name'] ?? ''), ($a['uri'] ?? '')] <=> [($b['domain'] ?? ''), ($b['route_name'] ?? ''), ($b['uri'] ?? '')]);
        $dispatches = [];
        foreach ($result['edges'] as $edge) {
            if ($edge['relation'] === 'dispatches_to') {
                $dispatches[$edge['from']][] = $edge['to'];
            }
        }

        $lines = ['# Route Index', '', '| Methods | URI | Name | Domain | Handler |', '| --- | --- | --- | --- | --- |'];
        foreach ($routes as $route) {
            $handlers = implode('<br>', array_map(fn (string $id): string => '`'.$this->escapeMarkdown(preg_replace('/^method:/', '', $id) ?? $id).'`', $dispatches[$route['id']] ?? []));
            $lines[] = '| '.implode(',', (array) ($route['methods'] ?? [])).' | `'.$this->escapeMarkdown((string) ($route['uri'] ?? '')).'` | `'.$this->escapeMarkdown((string) ($route['route_name'] ?? '-')).'` | '.($route['domain'] ?? 'core').' | '.($handlers ?: 'Closure').' |';
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>} $result */
    private function dataDocument(array $result): string
    {
        $tables = array_values(array_filter($result['nodes'], fn (array $node): bool => $node['kind'] === 'table'));
        $edgesByTable = [];
        foreach ($result['edges'] as $edge) {
            if (str_starts_with($edge['to'], 'table:')) {
                $edgesByTable[$edge['to']][] = $edge;
            }
        }
        usort($tables, function (array $left, array $right) use ($edgesByTable): int {
            return count($edgesByTable[$right['id']] ?? []) <=> count($edgesByTable[$left['id']] ?? []);
        });

        $lines = ['# Data Surface', '', 'Tables are discovered from schema calls, query builders, joins, model mappings, and SQL tooling.', '', '| Table | Domain | Relations | Main consumers |', '| --- | --- | ---: | --- |'];
        foreach ($tables as $table) {
            $edges = $edgesByTable[$table['id']] ?? [];
            $consumers = array_slice(array_values(array_unique(array_map(fn (array $edge): string => preg_replace('/^(method|symbol|file):/', '', $edge['from']) ?? $edge['from'], $edges))), 0, 8);
            $lines[] = '| `'.$this->escapeMarkdown($table['name']).'` | '.($table['domain'] ?? 'core').' | '.count($edges).' | '.implode('<br>', array_map(fn (string $value): string => '`'.$this->escapeMarkdown($value).'`', $consumers)).' |';
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array{manifest: array<string, mixed>, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>} $result */
    private function writeDomainDocuments(string $domainPath, array $result): void
    {
        $expected = [];
        foreach (array_keys((array) $result['manifest']['domains']) as $domain) {
            $expected[] = $domain.'.md';
            $this->writeText($domainPath.DIRECTORY_SEPARATOR.$domain.'.md', $this->domainDocument($domain, $result));
        }

        foreach (glob($domainPath.DIRECTORY_SEPARATOR.'*.md') ?: [] as $existing) {
            if (! in_array(basename($existing), $expected, true)) {
                @unlink($existing);
            }
        }
    }

    /** @param array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>} $result */
    private function domainDocument(string $domain, array $result): string
    {
        $domainNodes = array_values(array_filter($result['nodes'], fn (array $node): bool => ($node['domain'] ?? 'core') === $domain && $node['kind'] !== 'domain'));
        $ids = array_fill_keys(array_column($domainNodes, 'id'), true);
        $domainEdges = array_values(array_filter($result['edges'], fn (array $edge): bool => isset($ids[$edge['from']]) || isset($ids[$edge['to']])));
        $hubs = $this->topHubs($domainNodes, $domainEdges, 30);
        $byKind = [];
        foreach ($domainNodes as $node) {
            $byKind[$node['kind']][] = $node;
        }

        $lines = [
            '# Domain: '.$domain,
            '',
            'Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain='.$domain.' --depth=2`.',
            '',
            '## Scale',
            '',
            '| Kind | Count |',
            '| --- | ---: |',
        ];
        foreach ($byKind as $kind => $nodes) {
            $lines[] = '| '.$kind.' | '.count($nodes).' |';
        }
        $lines = array_merge($lines, ['', '## Main Hubs', '', '| Node | Kind | Degree | Source |', '| --- | --- | ---: | --- |']);
        foreach ($hubs as $hub) {
            $source = isset($hub['path']) ? '`'.$this->escapeMarkdown($hub['path']).':'.($hub['line'] ?? 1).'`' : '-';
            $lines[] = '| `'.$this->escapeMarkdown($hub['name']).'` | '.$hub['kind'].' | '.$hub['degree'].' | '.$source.' |';
        }

        foreach (['route', 'class', 'interface', 'trait', 'command', 'job', 'view', 'table', 'queue'] as $kind) {
            $nodes = $byKind[$kind] ?? [];
            if ($nodes === []) {
                continue;
            }
            usort($nodes, fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
            $lines[] = '';
            $lines[] = '## '.ucwords(str_replace('_', ' ', $kind)).' Nodes';
            $lines[] = '';
            foreach (array_slice($nodes, 0, 80) as $node) {
                $detail = $node['path'] ?? $node['uri'] ?? $node['fqn'] ?? '';
                $lines[] = '- `'.$this->escapeMarkdown($node['name']).'`'.($detail !== '' ? ' - `'.$this->escapeMarkdown((string) $detail).'`' : '');
            }
        }

        $cross = [];
        $nodesById = array_column($result['nodes'], null, 'id');
        foreach ($domainEdges as $edge) {
            $fromDomain = $nodesById[$edge['from']]['domain'] ?? 'core';
            $toDomain = $nodesById[$edge['to']]['domain'] ?? 'core';
            if ($fromDomain === $toDomain) {
                continue;
            }
            $key = $fromDomain.' -> '.$toDomain.' ('.$edge['relation'].')';
            $cross[$key] = ($cross[$key] ?? 0) + 1;
        }
        arsort($cross);
        if ($cross !== []) {
            $lines = array_merge($lines, ['', '## Cross-Domain Links', '', '| Direction | Count |', '| --- | ---: |']);
            foreach (array_slice($cross, 0, 30, true) as $label => $count) {
                $lines[] = '| '.$label.' | '.$count.' |';
            }
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array<int, array<string, mixed>> $nodes @param array<int, array<string, mixed>> $edges @return array<int, array<string, mixed>> */
    private function topHubs(array $nodes, array $edges, int $limit): array
    {
        $degree = [];
        foreach ($edges as $edge) {
            $degree[$edge['from']] = ($degree[$edge['from']] ?? 0) + 1;
            $degree[$edge['to']] = ($degree[$edge['to']] ?? 0) + 1;
        }
        $hubs = [];
        foreach ($nodes as $node) {
            if (in_array($node['kind'], ['file', 'domain', 'middleware', 'unresolved_symbol'], true)) {
                continue;
            }
            $node['degree'] = $degree[$node['id']] ?? 0;
            $hubs[] = $node;
        }
        usort($hubs, fn (array $a, array $b): int => [$b['degree'], $a['name']] <=> [$a['degree'], $b['name']]);

        return array_slice($hubs, 0, $limit);
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, int> */
    private function countBy(array $rows, string $key, string $fallback = 'unknown'): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = (string) ($row[$key] ?? $fallback);
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    /** @param array<int, string> $files */
    private function fingerprint(array $files): string
    {
        $context = hash_init('sha256');
        hash_update($context, 'project-abah-knowledge-graph-v'.self::SCHEMA_VERSION."\n");
        foreach ($files as $relativePath) {
            $absolute = $this->absolutePath($relativePath);
            hash_update($context, $relativePath."\0".(hash_file('sha256', $absolute) ?: '')."\n");
        }

        return hash_final($context);
    }

    private function domainFor(string $text): string
    {
        $pathLike = strtolower(str_replace('\\', '/', $text));
        if (str_starts_with($pathLike, 'database/')) {
            return 'database';
        }

        $normalized = strtolower(str_replace(['\\', '_', ' '], ['/', '-', ''], $text));
        foreach (self::DOMAIN_RULES as $domain => $needles) {
            foreach ($needles as $needle) {
                $needleNormalized = strtolower(str_replace(['\\', '_', ' '], ['/', '-', ''], $needle));
                if (str_contains($normalized, $needleNormalized) || str_contains($pathLike, strtolower($needle))) {
                    return $domain;
                }
            }
        }

        return 'core';
    }

    private function languageFor(string $relativePath): string
    {
        $path = strtolower($relativePath);

        return match (true) {
            str_ends_with($path, '.blade.php') => 'blade',
            str_ends_with($path, '.php') => 'php',
            str_ends_with($path, '.ps1') => 'powershell',
            str_ends_with($path, '.py') => 'python',
            str_ends_with($path, '.sql') => 'sql',
            str_ends_with($path, '.md') => 'markdown',
            str_ends_with($path, '.json') => 'json',
            str_ends_with($path, '.xml') => 'xml',
            default => 'javascript',
        };
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->basePath, '\\/').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    private function relativePath(string $absolutePath): string
    {
        $base = rtrim(str_replace('\\', '/', $this->basePath), '/').'/';
        $path = str_replace('\\', '/', $absolutePath);

        return str_starts_with(strtolower($path), strtolower($base)) ? substr($path, strlen($base)) : $path;
    }

    private function lineAt(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, max(0, $offset)), "\n") + 1;
    }

    private function shortName(string $fqn): string
    {
        $parts = explode('\\', $fqn);

        return (string) end($parts);
    }

    private function mermaidId(string $value): string
    {
        return 'd_'.substr(hash('sha256', $value), 0, 10);
    }

    private function escapeMarkdown(string $value): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], $value);
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create knowledge graph directory: '.$path);
        }
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $this->writeText($path, (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function writeJsonLines(string $path, array $rows): void
    {
        $lines = array_map(
            static fn (array $row): string => (string) json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $rows
        );
        $this->writeText($path, implode("\n", $lines)."\n");
    }

    private function writeText(string $path, string $contents): void
    {
        $temporary = $path.'.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write knowledge graph file: '.$path);
        }
        if (is_file($path) && ! unlink($path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to replace knowledge graph file: '.$path);
        }
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to finalize knowledge graph file: '.$path);
        }
    }
}
