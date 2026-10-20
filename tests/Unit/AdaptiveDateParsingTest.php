<?php

use App\Support\StrictDateParser;

it('normalizes modern export variants without losing the date', function (string $value, string $expected) {
    expect(StrictDateParser::normalize($value))->toBe($expected);
})->with([
    'core banking month first with clock' => ['10/18/2026 12:00:00 AM', '2026-10-18'],
    'iso 8601 with T' => ['2026-10-16T08:30:00', '2026-10-16'],
    'iso with milliseconds and zone' => ['2026-10-16T08:30:00.000Z', '2026-10-16'],
    'fractional seconds' => ['2026-10-16 08:30:00.123', '2026-10-16'],
    'utc offset' => ['2026-10-16 08:30:00+07:00', '2026-10-16'],
    'day first with 24h clock' => ['16/10/2026 08:30:00', '2026-10-16'],
    'single digit day and month' => ['4/7/2026', '2026-07-04'],
    'dotted european date' => ['16.10.2026', '2026-10-16'],
    'dash with meridiem' => ['10-18-2026 12:00:00 AM', '2026-10-18'],
    'textual with meridiem' => ['20 Oct 2026 08:39:16 AM', '2026-10-20'],
    'indonesian short month' => ['16-Okt-2026', '2026-10-16'],
    'indonesian august short' => ['5 Agt 2026', '2026-08-05'],
    'a.m. punctuation' => ['10/18/2026 1:05:09 p.m.', '2026-10-18'],
]);

it('uses the learned column order only for genuinely ambiguous values', function () {
    expect(StrictDateParser::normalizeWithOrder('04/07/2026', 'mdy'))->toBe('2026-04-07')
        ->and(StrictDateParser::normalizeWithOrder('04/07/2026', 'dmy'))->toBe('2026-07-04')
        ->and(StrictDateParser::normalizeWithOrder('27/08/2026', 'mdy'))->toBe('2026-08-27')
        ->and(StrictDateParser::normalizeWithOrder('10/18/2026 12:00:00 AM', 'dmy'))->toBe('2026-10-18')
        ->and(StrictDateParser::normalizeWithOrder('2026-10-16', 'mdy'))->toBe('2026-10-16')
        ->and(StrictDateParser::normalizeWithOrder('04/07/2026', null))->toBe('2026-07-04');
});

it('rejects impossible calendar dates in every variant', function (string $value) {
    expect(StrictDateParser::normalize($value))->toBeNull()
        ->and(StrictDateParser::normalizeWithOrder($value, 'mdy'))->toBeNull();
})->with(['31/02/2026', '2/31/2026', '30.02.2026', '2026-02-30T00:00:00']);

it('adds iso datetime, generic day-month and textual clauses to the mysql expression', function () {
    $sql = StrictDateParser::buildMySqlCaseExpression('@v', 'mdy');

    expect($sql)->toStartWith('DATE_ADD(CASE ')
        ->and($sql)->toEndWith('ELSE NULL END, INTERVAL 0 DAY)')
        ->and($sql)->toContain("[T ]+[0-9]{1,2}:[0-9]{2}")
        ->and($sql)->toContain("'%m/%d/%Y'")
        ->and($sql)->toContain("'%Y-%c-%e'")
        ->and($sql)->toContain("'okt', 'oct'");
});
