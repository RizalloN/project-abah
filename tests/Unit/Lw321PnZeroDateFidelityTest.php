<?php

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\SchemaDrift\ColumnFormatMemory;
use App\Services\Import\SchemaDrift\SchemaDriftDetector;
use App\Services\Import\SchemaIntrospectionService;
use App\Support\Import\ColumnValueProfiler;
use App\Support\StrictDateParser;

it('recognises every core banking zero date token', function (string $value, bool $zero) {
    expect(StrictDateParser::isZeroDateToken($value))->toBe($zero);
})->with([
    ['0', true], ['00', true], ['00/00/0000', true], ['0000-00-00', true], ['0 00:00:00', true],
    ['05/03/2002', false], ['0.00', false], ['10', false], ['', false], ['0 12:00:00 AM', false],
]);

it('keeps the source zero for lw321pn only and never turns it into Excel serial zero', function () {
    $zeroPreserving = StrictDateParser::buildMySqlZeroPreservingExpression('`c5`', 'PARSED');
    $violation = StrictDateParser::buildMySqlFidelityViolation('`c5`', $zeroPreserving, true);

    expect(StrictDateParser::preservesZeroDates('lw321pn'))->toBeTrue()
        ->and(StrictDateParser::preservesZeroDates('daily_loan_dinamis'))->toBeFalse()
        ->and($zeroPreserving)->toContain("THEN '0000-00-00' ELSE PARSED END")
        // A string literal, so the result does not depend on NO_ZERO_DATE.
        ->and($zeroPreserving)->not->toContain("DATE('0000-00-00')")
        ->and(StrictDateParser::buildMySqlCaseExpression('`c5`'))->toContain('BETWEEN 20000 AND 80000')
        ->and($violation)->toContain('NOT BETWEEN 1900 AND 2100');
});

it('builds lw321pn date rules that preserve zero in both the SQL and the PHP path', function () {
    $controller = app(ImportExcelController::class);
    $buildSql = new ReflectionMethod($controller, 'buildDirectLoadSqlExpression');
    $normalize = new ReflectionMethod($controller, 'normalizeExcelValueByRule');
    $rule = ['header_name' => 'TGL_MENUNGGAK', 'is_date' => true, 'date_order' => 'dmy', 'preserve_zero_date' => true];
    $otherRule = ['preserve_zero_date' => false] + $rule;

    expect($buildSql->invoke($controller, $rule, '`c15`', 'tgl_menunggak', []))->toContain("THEN '0000-00-00'")
        ->and($buildSql->invoke($controller, $otherRule, '`c15`', 'tgl_menunggak', []))->not->toContain("'0000-00-00'")
        ->and($normalize->invoke($controller, $rule, '0'))->toBe('0000-00-00')
        ->and($normalize->invoke($controller, $rule, '05/03/2002'))->toBe('2002-03-05')
        ->and($normalize->invoke($controller, $otherRule, '0'))->toBeNull();
});

it('tells the admin how a source zero date is stored', function () {
    $schema = new class extends SchemaIntrospectionService {
        public function getColumnMetadata(string $tableName): array
        {
            return ['tgl_menunggak' => $this->parseMysqlColumnTypeMetadata('date') + ['name' => 'tgl_menunggak']];
        }
    };
    $detector = new SchemaDriftDetector(new ColumnValueProfiler(), app(ColumnFormatMemory::class), $schema);
    $rows = array_merge(array_fill(0, 30, ['0']), [['05/03/2002'], ['28/02/2002']]);

    $kept = collect($detector->detect('lw321pn', ['TGL_MENUNGGAK'], $rows)['adaptations'])->firstWhere('code', 'zero_date_kept');
    $empty = collect($detector->detect('demo_other', ['TGL_MENUNGGAK'], $rows)['adaptations'])->firstWhere('code', 'zero_date_empty');

    expect($kept['severity'])->toBe('info')
        ->and($kept['detail'])->toContain('0000-00-00')
        ->and($empty['severity'])->toBe('info')
        ->and(collect($detector->detect('lw321pn', ['TGL_MENUNGGAK'], $rows)['adaptations'])->pluck('code')->all())->not->toContain('unparsed_dates');
});
