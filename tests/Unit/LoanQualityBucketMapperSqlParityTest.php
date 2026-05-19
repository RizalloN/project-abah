<?php

namespace Tests\Unit;

use App\Support\LoanQualityBucketMapper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoanQualityBucketMapperSqlParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // Register MySQL-compatible DATEDIFF and LEAST shims on the SQLite PDO
        // so the unified bucket SQL (which targets MySQL in production) parses
        // and runs identically in tests.
        $pdo = DB::connection('sqlite')->getPdo();
        $pdo->sqliteCreateFunction('DATEDIFF', static function ($a, $b): ?int {
            if ($a === null || $b === null) {
                return null;
            }
            $tsA = strtotime((string) $a);
            $tsB = strtotime((string) $b);
            if ($tsA === false || $tsB === false) {
                return null;
            }
            return (int) floor(($tsA - $tsB) / 86400);
        }, 2);
        $pdo->sqliteCreateFunction('LEAST', static function (...$args) {
            $filtered = array_filter($args, static fn ($v) => $v !== null);
            return $filtered ? min($filtered) : null;
        });

        Schema::dropAllTables();

        Schema::create('loan_quality_bucket_samples', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('kolek_detail')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('kol_adk1')->nullable();
            $table->string('kolek')->nullable();
            $table->date('periode')->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_pmt_int_date')->nullable();
        });
    }

    public function test_sql_expression_matches_php_mapper_for_sample_rows(): void
    {
        $rows = [
            ['id' => 1, 'kolek_detail' => 'L', 'umur_tunggakan' => 2, 'flag_restruk' => null, 'kol_adk1' => '9', 'kolek' => '2', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 2, 'kolek_detail' => 'L', 'umur_tunggakan' => 36, 'flag_restruk' => null, 'kol_adk1' => '1', 'kolek' => '2', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 3, 'kolek_detail' => 'M', 'umur_tunggakan' => 130, 'flag_restruk' => null, 'kol_adk1' => '5', 'kolek' => '4', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 4, 'kolek_detail' => 'KL', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => '1', 'kolek' => '1', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 5, 'kolek_detail' => 'DPK2', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => null, 'kolek' => null, 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 6, 'kolek_detail' => 'L', 'umur_tunggakan' => 0, 'flag_restruk' => 'Y', 'kol_adk1' => '1', 'kolek' => '1', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 7, 'kolek_detail' => '', 'umur_tunggakan' => 0, 'flag_restruk' => 'Y', 'kol_adk1' => '1', 'kolek' => '1', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 8, 'kolek_detail' => '0', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => '2', 'kolek' => '3', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 9, 'kolek_detail' => 'M', 'umur_tunggakan' => 150, 'flag_restruk' => null, 'kol_adk1' => '4', 'kolek' => '4', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 10, 'kolek_detail' => 'L', 'umur_tunggakan' => 0, 'flag_restruk' => null, 'kol_adk1' => '1', 'kolek' => '5', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 11, 'kolek_detail' => 'KL', 'umur_tunggakan' => 274, 'flag_restruk' => null, 'kol_adk1' => '2', 'kolek' => '2', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 12, 'kolek_detail' => 'M', 'umur_tunggakan' => 1323, 'flag_restruk' => null, 'kol_adk1' => '4', 'kolek' => '4', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 13, 'kolek_detail' => 'L', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => '2', 'kolek' => '2', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 14, 'kolek_detail' => 'M', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => '4', 'kolek' => '4', 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 15, 'kolek_detail' => 'SML 1', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => null, 'kolek' => null, 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 16, 'kolek_detail' => 'SML 2', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => null, 'kolek' => null, 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
            ['id' => 17, 'kolek_detail' => 'SML 3', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => null, 'kolek' => null, 'periode' => null, 'next_pmt_date' => null, 'next_pmt_int_date' => null],
        ];
        // Note: kasus umur_tunggakan NULL dengan fallback NEXT_PMT_DATE/NEXT_PMT_INT_DATE
        // dites khusus pada LoanQualityBucketMapperTest karena DATEDIFF/LEAST adalah
        // ekspresi MySQL yang tidak portabel ke SQLite (driver default test ini).

        DB::table('loan_quality_bucket_samples')->insert($rows);

        $expected = [];
        foreach ($rows as $row) {
            $expected[$row['id']] = LoanQualityBucketMapper::map(
                $row['kolek_detail'],
                $row['umur_tunggakan'],
                $row['flag_restruk'],
                $row['kol_adk1'],
                $row['kolek'],
                $row['periode'],
                $row['next_pmt_date'],
                $row['next_pmt_int_date']
            );
        }

        $actual = DB::table('loan_quality_bucket_samples as sample')
            ->select('sample.id')
            ->selectRaw(LoanQualityBucketMapper::buildSqlExpression('sample').' as bucket')
            ->orderBy('sample.id')
            ->pluck('bucket', 'sample.id')
            ->mapWithKeys(fn ($bucket, $id) => [(int) $id => $bucket])
            ->all();

        $this->assertSame($expected, $actual);
    }
}
