<?php

namespace App\Support\KnowledgeGraph;

final class KnowledgeGraph
{
    /** @var array<string, array<string, mixed>> */
    private array $nodes = [];

    /** @var array<string, array<string, mixed>> */
    private array $edges = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addNode(string $id, string $kind, string $name, array $attributes = []): string
    {
        $attributes = array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );

        if (isset($this->nodes[$id])) {
            $existing = $this->nodes[$id];
            $this->nodes[$id] = array_merge($existing, $attributes, [
                'id' => $id,
                'kind' => $existing['kind'] === 'unresolved_symbol' ? $kind : $existing['kind'],
                'name' => $existing['name'] ?: $name,
            ]);

            return $id;
        }

        $this->nodes[$id] = array_merge([
            'id' => $id,
            'kind' => $kind,
            'name' => $name,
        ], $attributes);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addEdge(string $from, string $relation, string $to, array $attributes = []): void
    {
        if ($from === '' || $to === '' || $from === $to || $relation === '') {
            return;
        }

        $attributes = array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
        $key = hash('sha256', json_encode([$from, $relation, $to], JSON_UNESCAPED_SLASHES));
        if (isset($this->edges[$key])) {
            $edge = $this->edges[$key];
            $edge['occurrences'] = (int) ($edge['occurrences'] ?? 1) + 1;
            if (isset($attributes['path'], $attributes['line'])
                && (($edge['path'] ?? null) !== $attributes['path'] || ($edge['line'] ?? null) !== $attributes['line'])) {
                $evidence = (array) ($edge['additional_evidence'] ?? []);
                $candidate = ['path' => $attributes['path'], 'line' => $attributes['line']];
                if (! in_array($candidate, $evidence, true) && count($evidence) < 5) {
                    $evidence[] = $candidate;
                }
                $edge['additional_evidence'] = $evidence;
            }
            $this->edges[$key] = $edge;

            return;
        }

        $this->edges[$key] = array_merge([
            'from' => $from,
            'relation' => $relation,
            'to' => $to,
        ], $attributes);
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /** @return array<string, mixed>|null */
    public function node(string $id): ?array
    {
        return $this->nodes[$id] ?? null;
    }

    /** @return array<int, array<string, mixed>> */
    public function nodes(): array
    {
        $nodes = array_values($this->nodes);
        usort($nodes, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));

        return $nodes;
    }

    /** @return array<int, array<string, mixed>> */
    public function edges(): array
    {
        $edges = array_values($this->edges);
        usort($edges, static function (array $left, array $right): int {
            return [$left['from'], $left['relation'], $left['to']]
                <=> [$right['from'], $right['relation'], $right['to']];
        });

        return $edges;
    }
}
