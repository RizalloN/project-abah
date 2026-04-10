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

        Schema::dropAllTables();

        Schema::create('loan_quality_bucket_samples', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('kolek_detail')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('kol_adk1')->nullable();
            $table->string('kolek')->nullable();
        });
    }

    public function test_sql_expression_matches_php_mapper_for_sample_rows(): void
    {
        $rows = [
            ['id' => 1, 'kolek_detail' => 'L', 'umur_tunggakan' => 2, 'flag_restruk' => null, 'kol_adk1' => '2', 'kolek' => null],
            ['id' => 2, 'kolek_detail' => 'L', 'umur_tunggakan' => 36, 'flag_restruk' => null, 'kol_adk1' => '1', 'kolek' => null],
            ['id' => 3, 'kolek_detail' => 'M', 'umur_tunggakan' => 130, 'flag_restruk' => null, 'kol_adk1' => '5', 'kolek' => null],
            ['id' => 4, 'kolek_detail' => 'KL', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => '1', 'kolek' => null],
            ['id' => 5, 'kolek_detail' => 'DPK2', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => null, 'kolek' => null],
            ['id' => 6, 'kolek_detail' => 'L', 'umur_tunggakan' => 0, 'flag_restruk' => 'Y', 'kol_adk1' => '1', 'kolek' => null],
            ['id' => 7, 'kolek_detail' => '', 'umur_tunggakan' => 0, 'flag_restruk' => 'Y', 'kol_adk1' => '1', 'kolek' => null],
            ['id' => 8, 'kolek_detail' => '0', 'umur_tunggakan' => null, 'flag_restruk' => null, 'kol_adk1' => '2', 'kolek' => '3'],
        ];

        DB::table('loan_quality_bucket_samples')->insert($rows);

        $expected = [];
        foreach ($rows as $row) {
            $expected[$row['id']] = LoanQualityBucketMapper::map(
                $row['kolek_detail'],
                $row['umur_tunggakan'],
                $row['flag_restruk'],
                $row['kol_adk1'],
                $row['kolek']
            );
        }

        $actual = DB::table('loan_quality_bucket_samples as sample')
            ->select('sample.id')
            ->selectRaw(LoanQualityBucketMapper::buildSqlExpression('sample') . ' as bucket')
            ->orderBy('sample.id')
            ->pluck('bucket', 'sample.id')
            ->mapWithKeys(fn ($bucket, $id) => [(int) $id => $bucket])
            ->all();

        $this->assertSame($expected, $actual);
    }
}
