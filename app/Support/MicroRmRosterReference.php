<?php

namespace App\Support;

final class MicroRmRosterReference
{
    public function data(): array
    {
        $path = resource_path('data/micro-rm-roster-reference.json');
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function fingerprint(): string
    {
        return hash_file('sha256', resource_path('data/micro-rm-roster-reference.json'));
    }

    public function people(string $period, array $fallback): array
    {
        $data = $this->data();
        $branches = array_column($data['sources'], 'branch');
        $people = array_values(array_filter($fallback, static fn (array $person): bool => !in_array(strtoupper(trim($person['branch'])), $branches, true)));
        foreach ($data['people'] as $person) {
            if (!empty($person['effective_from']) && $person['effective_from'] > $period) {
                continue;
            }
            $person['branch_code'] = '';
            $person['roster_source'] = 'sdm_reference';
            $people[] = $person;
        }

        return $people;
    }
}
