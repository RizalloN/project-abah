<?php

namespace Tests\Unit;

use App\Services\Reports\KpiPersonnelReferenceSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KpiPersonnelReferenceSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::flush();
        Schema::dropIfExists('wilayah_mbm');
        Schema::dropIfExists('brihc_pemasar');
        Schema::dropIfExists('brihc');

        Schema::create('brihc', function (Blueprint $table): void {
            $table->string('uniqueid_brihc')->primary();
            $table->string('pn')->nullable();
            $table->string('nama')->nullable();
            $table->string('jabatan')->nullable();
            $table->timestamps();
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->string('completename')->nullable();
            $table->string('pernr')->nullable();
            $table->string('esgdesc')->nullable();
            $table->string('padesc')->nullable();
            $table->string('psadesc')->nullable();
            $table->string('orgdesc')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('jobgrade')->nullable();
            $table->string('bc')->nullable();
            $table->string('pn_mantri')->nullable();
            $table->string('status')->nullable();
            $table->string('jg')->nullable();
            $table->string('bln_2026')->nullable();
            $table->timestamps();
        });
        Schema::create('wilayah_mbm', function (Blueprint $table): void {
            $table->string('uniqueid_mbm')->primary();
            $table->string('bc')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('cabang')->nullable();
            $table->string('nama_mbm')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('wilayah_mbm');
        Schema::dropIfExists('brihc_pemasar');
        Schema::dropIfExists('brihc');

        parent::tearDown();
    }

    public function test_rm_sme_sync_updates_existing_person_and_adds_new_person_without_deleting_other_rows(): void
    {
        $now = now()->toDateTimeString();
        DB::table('brihc')->insert([
            [
                'uniqueid_brihc' => 'existing-rm',
                'pn' => '61445',
                'nama' => 'Nama Lama',
                'jabatan' => 'RM BISNIS KECIL',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uniqueid_brihc' => 'untouched-person',
                'pn' => '99999',
                'nama' => 'Tetap Ada',
                'jabatan' => 'PINCA',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uniqueid_brihc' => 'same-pn-other-role',
                'pn' => '61445',
                'nama' => 'Identitas Role Lain',
                'jabatan' => 'RM COLLECTION',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('brihc_pemasar')->insert([
            [
                'uniqueid_namareport' => 'existing-rm-pemasar',
                'completename' => 'Nama Lama',
                'pernr' => '61445',
                'padesc' => 'Region 13 Malang',
                'psadesc' => 'KC Ngawi',
                'orgdesc' => 'FUNGSI BISNIS KECIL',
                'positiondesc' => 'RM BISNIS KECIL',
                'jg' => 'JG06',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uniqueid_namareport' => 'same-pn-other-role-pemasar',
                'completename' => 'Identitas Role Lain',
                'pernr' => '61445',
                'padesc' => null,
                'psadesc' => 'KC Ngawi',
                'orgdesc' => null,
                'positiondesc' => 'RM COLLECTION',
                'jg' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $result = app(KpiPersonnelReferenceSyncService::class)->sync('rm-sme', [
            'header' => ['BO', 'Uker', 'JG', 'Pencapaian', 'Score'],
            'rows' => [
                ['00045 -- KC Madiun', '00061445 - Unung Dwi Hendrawati', 'JG07', '100%', '15'],
                ['00070 -- KC Ponorogo', '00300555 - RM Baru Juli', 'JG06', '95%', '14'],
            ],
        ]);

        $this->assertSame(2, $result['source_records']);
        $this->assertDatabaseHas('brihc', [
            'uniqueid_brihc' => 'existing-rm',
            'pn' => '61445',
            'nama' => 'Unung Dwi Hendrawati',
            'jabatan' => 'RM BISNIS KECIL',
        ]);
        $this->assertDatabaseHas('brihc_pemasar', [
            'uniqueid_namareport' => 'existing-rm-pemasar',
            'pernr' => '61445',
            'completename' => 'Unung Dwi Hendrawati',
            'psadesc' => 'KC Madiun',
            'jg' => 'JG07',
        ]);
        $this->assertDatabaseHas('brihc', ['pn' => '300555', 'nama' => 'RM Baru Juli']);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '300555', 'psadesc' => 'KC Ponorogo']);
        $this->assertDatabaseHas('brihc', ['uniqueid_brihc' => 'untouched-person', 'nama' => 'Tetap Ada']);
        $this->assertDatabaseHas('brihc', [
            'uniqueid_brihc' => 'same-pn-other-role',
            'nama' => 'Identitas Role Lain',
            'jabatan' => 'RM COLLECTION',
        ]);
        $this->assertDatabaseHas('brihc_pemasar', [
            'uniqueid_namareport' => 'same-pn-other-role-pemasar',
            'completename' => 'Identitas Role Lain',
            'positiondesc' => 'RM COLLECTION',
        ]);
        $this->assertSame(4, DB::table('brihc')->count());
    }

    public function test_mbm_sources_update_names_and_unit_assignments_without_fabricating_pn(): void
    {
        $now = now()->toDateTimeString();
        DB::table('brihc')->insert([
            'uniqueid_brihc' => 'rita-kaunit',
            'pn' => '61165',
            'nama' => 'Rita Auliasari',
            'jabatan' => 'KAUNIT',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('wilayah_mbm')->insert([
            'uniqueid_mbm' => 'existing-unit',
            'bc' => '6348',
            'nama_uker' => 'UNIT LAMA',
            'cabang' => 'NGAWI',
            'nama_mbm' => 'MBM Lama',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = app(KpiPersonnelReferenceSyncService::class);
        $service->sync('mbm', [
            'header' => ['BO', 'MBM', 'Score'],
            'rows' => [
                ['MAGETAN', 'Rita Auliasari', '90%'],
                ['MADIUN', 'Hendri Nur Wahyudi', '89%'],
            ],
        ]);
        $service->sync('ka-unit', [
            'header' => ['BO', 'MBM', 'BC', 'Unit Kerja'],
            'rows' => [
                ['MADIUN', 'Hendri Nur Wahyudi', '6348', '06348 -- UNIT UTERAN MADIUN'],
                ['MAGETAN', 'Rita Auliasari', '6360', '06360 -- UNIT KENONGOMULYO'],
            ],
        ]);

        $this->assertDatabaseHas('brihc', [
            'uniqueid_brihc' => 'rita-kaunit',
            'pn' => '61165',
            'jabatan' => 'MBM',
        ]);
        $this->assertDatabaseHas('brihc', [
            'nama' => 'Hendri Nur Wahyudi',
            'jabatan' => 'MBM',
            'pn' => null,
        ]);
        $this->assertDatabaseHas('wilayah_mbm', [
            'uniqueid_mbm' => 'existing-unit',
            'bc' => '6348',
            'cabang' => 'MADIUN',
            'nama_mbm' => 'Hendri Nur Wahyudi',
        ]);
        $this->assertDatabaseHas('wilayah_mbm', [
            'bc' => '6360',
            'cabang' => 'MAGETAN',
            'nama_mbm' => 'Rita Auliasari',
        ]);
        $this->assertSame(2, DB::table('wilayah_mbm')->count());
    }

    public function test_same_pn_from_rm_mikro_and_rm_sme_keeps_both_role_references(): void
    {
        $service = app(KpiPersonnelReferenceSyncService::class);

        $service->sync('rm-mikro', [
            'header' => ['Nama', 'BC Uker', 'Uker', 'JG'],
            'rows' => [
                ['00335871 - Aulia Rika Ramadhani', 'KC Madiun', 'KC Madiun', 'JG06'],
            ],
        ]);
        $service->sync('rm-sme', [
            'header' => ['BO', 'Uker', 'JG'],
            'rows' => [
                ['00045 -- KC Madiun', '00335871 - Aulia Rika Ramadhani', 'JG06'],
            ],
        ]);

        $this->assertDatabaseHas('brihc', ['pn' => '335871', 'jabatan' => 'RM MIKRO']);
        $this->assertDatabaseHas('brihc', ['pn' => '335871', 'jabatan' => 'RM BISNIS KECIL']);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '335871', 'positiondesc' => 'RM MIKRO']);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '335871', 'positiondesc' => 'RM BISNIS KECIL']);
        $this->assertSame(2, DB::table('brihc')->where('pn', '335871')->count());
        $this->assertSame(2, DB::table('brihc_pemasar')->where('pernr', '335871')->count());
    }
}
