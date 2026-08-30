<?php

namespace App\Support\KnowledgeGraph;

use RuntimeException;

final class KnowledgeGraphIndex
{
    /** @var array<string, array<string, mixed>> */
    private array $nodes = [];

    /** @var array<int, array<string, mixed>> */
    private array $edges = [];

    /** @var array<string, array<int, int>> */
    private array $adjacency = [];

    /** @var array<string, mixed> */
    private array $manifest = [];

    public function __construct(private readonly string $graphPath)
    {
        $this->load();
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return $this->manifest;
    }

    /**
     * @return array{query: string, domain: ?string, matches: array<int, array<string, mixed>>, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function query(string $query = '', ?string $domain = null, int $depth = 1, int $limit = 60): array
    {
        $query = trim($query);
        $domain = $domain !== null && trim($domain) !== '' ? trim($domain) : null;
        $depth = max(0, min(4, $depth));
        $limit = max(5, min(500, $limit));
        $scored = [];

        foreach ($this->nodes as $id => $node) {
            if ($node['kind'] === 'domain') {
                continue;
            }
            if ($domain !== null && ($node['domain'] ?? 'core') !== $domain) {
                continue;
            }

            $score = $query === '' ? $this->connectivity($id) : $this->searchScore($node, $query);
            if ($score > 0) {
                $scored[] = ['score' => $score, 'node' => $node];
            }
        }

        usort($scored, static fn (array $left, array $right): int => [$right['score'], $left['node']['id']] <=> [$left['score'], $right['node']['id']]);
        $bestScore = (int) ($scored[0]['score'] ?? 0);
        if ($query !== '' && $bestScore >= 10000) {
            $scored = array_values(array_filter($scored, static fn (array $item): bool => $item['score'] >= 9000));
        }
        $matches = array_map(static fn (array $item): array => $item['node'], array_slice($scored, 0, min(12, $limit)));
        $selected = [];
        $frontier = [];
        foreach ($matches as $match) {
            $selected[$match['id']] = 0;
            $frontier[] = $match['id'];
        }

        for ($level = 0; $level < $depth && $frontier !== [] && count($selected) < $limit; $level++) {
            $next = [];
            foreach ($frontier as $nodeId) {
                foreach ($this->adjacency[$nodeId] ?? [] as $edgeIndex) {
                    $edge = $this->edges[$edgeIndex];
                    if ($edge['relation'] === 'belongs_to') {
                        continue;
                    }
                    $neighbor = $edge['from'] === $nodeId ? $edge['to'] : $edge['from'];
                    if (isset($selected[$neighbor]) || ! isset($this->nodes[$neighbor])) {
                        continue;
                    }
                    if ($domain !== null && ($this->nodes[$neighbor]['domain'] ?? 'core') !== $domain) {
                        continue;
                    }
                    $selected[$neighbor] = $level + 1;
                    $next[] = $neighbor;
                    if (count($selected) >= $limit) {
                        break 2;
                    }
                }
            }
            $frontier = array_values(array_unique($next));
        }

        $nodes = array_map(fn (string $id): array => $this->nodes[$id] + ['distance' => $selected[$id]], array_keys($selected));
        usort($nodes, static fn (array $left, array $right): int => [$left['distance'], $left['kind'], $left['name']] <=> [$right['distance'], $right['kind'], $right['name']]);
        $selectedIds = array_fill_keys(array_column($nodes, 'id'), true);
        $edges = array_values(array_filter(
            $this->edges,
            static fn (array $edge): bool => $edge['relation'] !== 'belongs_to'
                && isset($selectedIds[$edge['from']], $selectedIds[$edge['to']])
        ));

        return compact('query', 'domain', 'matches', 'nodes', 'edges');
    }

    /** @param array{query: string, domain: ?string, matches: array<int, array<string, mixed>>, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>} $result */
    public function format(array $result): string
    {
        if ($result['matches'] === []) {
            return 'No knowledge graph nodes matched the query.';
        }

        $lines = [
            'Knowledge graph query: '.($result['query'] !== '' ? $result['query'] : '(top hubs)'),
            'Domain: '.($result['domain'] ?: 'all'),
            '',
            'MATCHES',
        ];
        foreach ($result['matches'] as $node) {
            $lines[] = $this->formatNode($node);
        }

        $lines[] = '';
        $lines[] = 'NEIGHBORHOOD';
        foreach ($result['nodes'] as $node) {
            if ((int) ($node['distance'] ?? 0) === 0) {
                continue;
            }
            $lines[] = $this->formatNode($node);
        }

        $lines[] = '';
        $lines[] = 'RELATIONS';
        foreach ($result['edges'] as $edge) {
            $from = $this->nodes[$edge['from']]['name'] ?? $edge['from'];
            $to = $this->nodes[$edge['to']]['name'] ?? $edge['to'];
            $evidence = isset($edge['path'])
                ? ' @ '.$edge['path'].(isset($edge['line']) ? ':'.$edge['line'] : '')
                : '';
            $lines[] = '- '.$from.' --'.$edge['relation'].'--> '.$to.$evidence;
        }

        return implode("\n", $lines);
    }

    private function load(): void
    {
        $manifestPath = $this->graphPath.DIRECTORY_SEPARATOR.'manifest.json';
        $nodesPath = $this->graphPath.DIRECTORY_SEPARATOR.'nodes.jsonl';
        $edgesPath = $this->graphPath.DIRECTORY_SEPARATOR.'edges.jsonl';
        if (! is_file($manifestPath) || ! is_file($nodesPath) || ! is_file($edgesPath)) {
            throw new RuntimeException('Knowledge graph is not built. Run: php artisan knowledge:graph --build');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            throw new RuntimeException('Knowledge graph manifest is invalid. Rebuild the graph.');
        }
        $this->manifest = $manifest;

        $nodeHandle = fopen($nodesPath, 'rb');
        if ($nodeHandle === false) {
            throw new RuntimeException('Unable to open knowledge graph nodes.');
        }
        while (($line = fgets($nodeHandle)) !== false) {
            $node = json_decode(trim($line), true);
            if (is_array($node) && isset($node['id'])) {
                $this->nodes[$node['id']] = $node;
            }
        }
        fclose($nodeHandle);

        $edgeHandle = fopen($edgesPath, 'rb');
        if ($edgeHandle === false) {
            throw new RuntimeException('Unable to open knowledge graph edges.');
        }
        while (($line = fgets($edgeHandle)) !== false) {
            $edge = json_decode(trim($line), true);
            if (! is_array($edge) || ! isset($edge['from'], $edge['to'], $edge['relation'])) {
                continue;
            }
            $index = count($this->edges);
            $this->edges[] = $edge;
            $this->adjacency[$edge['from']][] = $index;
            $this->adjacency[$edge['to']][] = $index;
        }
        fclose($edgeHandle);
    }

    /** @param array<string, mixed> $node */
    private function searchScore(array $node, string $query): int
    {
        $needle = $this->normalize($query);
        $id = $this->normalize((string) ($node['id'] ?? ''));
        $name = $this->normalize((string) ($node['name'] ?? ''));
        $fqn = $this->normalize((string) ($node['fqn'] ?? ''));
        $path = $this->normalize((string) ($node['path'] ?? ''));
        $uri = $this->normalize((string) ($node['uri'] ?? ''));
        $haystack = implode(' ', [$id, $name, $fqn, $path, $uri, $this->normalize((string) ($node['domain'] ?? ''))]);

        if ($needle === $id || $needle === $name || $needle === $fqn) {
            return 10000 + $this->connectivity((string) $node['id']);
        }
        if (str_starts_with($name, $needle) || str_ends_with($fqn, $needle)) {
            return 8000 + $this->connectivity((string) $node['id']);
        }
        if (str_contains($name, $needle) || str_contains($fqn, $needle)) {
            return 6000 + $this->connectivity((string) $node['id']);
        }
        if (str_contains($path, $needle) || str_contains($uri, $needle)) {
            return 4000 + $this->connectivity((string) $node['id']);
        }

        $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $needle) ?: []));
        if ($tokens !== [] && count(array_filter($tokens, fn (string $token): bool => str_contains($haystack, $token))) === count($tokens)) {
            return 2000 + $this->connectivity((string) $node['id']);
        }

        return 0;
    }

    private function connectivity(string $id): int
    {
        return count(array_filter(
            $this->adjacency[$id] ?? [],
            fn (int $edgeIndex): bool => ($this->edges[$edgeIndex]['relation'] ?? '') !== 'belongs_to'
        ));
    }

    /** @param array<string, mixed> $node */
    private function formatNode(array $node): string
    {
        $location = isset($node['path'])
            ? ' @ '.$node['path'].(isset($node['line']) ? ':'.$node['line'] : '')
            : '';

        return '- ['.$node['kind'].'] '.$node['name'].' {'.$node['domain'].'}'.$location;
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(str_replace(['\\', '_', '-', '/', ':'], ' ', $value)));
    }
}
