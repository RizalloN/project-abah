<?php

namespace Tests\Unit;

use App\Support\MicroRmRosterReference;
use Tests\TestCase;

class MicroRmRosterReferenceTest extends TestCase
{
    public function test_reference_uses_official_pns_locations_and_effective_dates(): void
    {
        $reference = new MicroRmRosterReference();
        $people = collect($reference->people('2026-10-07', []));
        $this->assertCount(14, $people);
        $this->assertCount(14, $people->pluck('pn')->unique());
        $this->assertCount(12, $people->where('office_type', 'kc'));
        $this->assertCount(2, $people->where('office_type', 'kcp'));
        $this->assertSame('KCP DOLOPO', $people->firstWhere('pn', '00121911')['unit']);
        $this->assertSame('KCP CARUBAN', $people->firstWhere('pn', '00151454')['unit']);
        $this->assertSame('Catur Priyatnanda Aditya', $people->firstWhere('pn', '00223311')['name']);
        $before = collect($reference->people('2026-09-30', []));
        $this->assertNull($before->firstWhere('pn', '00223311'));
        $this->assertCount(13, $before);
        $this->assertSame(64, strlen($reference->fingerprint()));
    }

    public function test_supplied_branches_replace_stale_roster_and_other_branches_are_preserved(): void
    {
        $result = (new MicroRmRosterReference())->people('2026-10-07', [
            ['pn' => '999', 'name' => 'Stale', 'branch' => 'KC MADIUN'],
            ['pn' => '888', 'name' => 'Outside reference', 'branch' => 'KC LAIN'],
        ]);
        $this->assertCount(15, $result);
        $this->assertNotContains('999', array_column($result, 'pn'));
        $this->assertContains('888', array_column($result, 'pn'));
    }
}
