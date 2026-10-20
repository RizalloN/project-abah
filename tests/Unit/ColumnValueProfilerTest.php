<?php

use App\Support\Import\ColumnValueProfiler;

beforeEach(function () {
    $this->profiler = new ColumnValueProfiler();
});

it('proves a month-first period column and marks a midnight clock as ignorable', function () {
    $profile = $this->profiler->profile(['10/18/2026 12:00:00 AM', '10/18/2026 12:00:00 AM', ''], 'PERIODE');

    expect($profile['kind'])->toBe(ColumnValueProfiler::KIND_DATE)
        ->and($profile['date']['order'])->toBe('mdy')
        ->and($profile['date']['order_basis'])->toBe(ColumnValueProfiler::ORDER_EVIDENCE)
        ->and($profile['date']['has_time'])->toBeTrue()
        ->and($profile['date']['meaningful_time'])->toBeFalse()
        ->and($profile['date']['pattern'])->toBe('MM/DD/YYYY hh:mm:ss AM/PM')
        ->and($profile['blank'])->toBe(1);
});

it('proves a day-first column from any value with a day above twelve', function () {
    $profile = $this->profiler->profile(['04/07/2026', '16/08/2022', '02/10/2024'], 'TGL_PH');

    expect($profile['date']['order'])->toBe('dmy')
        ->and($profile['date']['order_basis'])->toBe(ColumnValueProfiler::ORDER_EVIDENCE)
        ->and($profile['signature'])->toBe('date|numeric|dmy|notime');
});

it('falls back to conventions and flags conflicting orders', function () {
    $ambiguousClock = $this->profiler->profile(['04/07/2026 12:00:00 AM'], 'PERIODE');
    $ambiguousPlain = $this->profiler->profile(['04/07/2026'], 'TGL');
    $mixed = $this->profiler->profile(['13/01/2026', '01/13/2026'], 'TANGGAL');

    expect($ambiguousClock['date']['order'])->toBe('mdy')
        ->and($ambiguousClock['date']['order_basis'])->toBe(ColumnValueProfiler::ORDER_CONVENTION)
        ->and($ambiguousPlain['date']['order'])->toBe('dmy')
        ->and($mixed['date']['order_basis'])->toBe(ColumnValueProfiler::ORDER_CONFLICT);
});

it('detects a datetime when the clock carries information', function () {
    $profile = $this->profiler->profile(['2026-10-16 08:30:00', '2026-10-16 13:05:00'], 'WAKTU_UPDATE');

    expect($profile['kind'])->toBe(ColumnValueProfiler::KIND_DATETIME)
        ->and($profile['date']['order'])->toBe('ymd');
});

it('keeps identifiers as text and recognises thousand separated decimals', function () {
    $account = $this->profiler->profile(['0814601000000005', '0814601000000006'], 'NO_REKENING_BARU');
    $amount = $this->profiler->profile(['15,764,428.00', '-485,817.19', '0.00'], 'POKOK');
    $idAmount = $this->profiler->profile(['1.234.567,89', '12,5'], 'SALDO');
    $count = $this->profiler->profile(['1', '0', '3'], 'JUMLAH_PN');

    expect($account['kind'])->toBe(ColumnValueProfiler::KIND_IDENTIFIER)
        ->and($amount['kind'])->toBe(ColumnValueProfiler::KIND_DECIMAL)
        ->and($amount['number']['style'])->toBe('us')
        ->and($amount['number']['has_negative'])->toBeTrue()
        ->and($idAmount['number']['style'])->toBe('id')
        ->and($count['kind'])->toBe(ColumnValueProfiler::KIND_INTEGER);
});

it('does not mistake plain numbers for dates outside date-like headers', function () {
    expect($this->profiler->profile(['20261016', '20261017'], 'NILAI_POIN')['kind'])->toBe(ColumnValueProfiler::KIND_INTEGER)
        ->and($this->profiler->profile(['20261016', '20261017'], 'TGL_POSISI')['kind'])->toBe(ColumnValueProfiler::KIND_DATE)
        ->and($this->profiler->profile(['', '-', null], 'APA_SAJA')['kind'])->toBe(ColumnValueProfiler::KIND_EMPTY);
});

it('accepts decimals without a leading zero and shows a representative example', function () {
    $profile = $this->profiler->profile(['.00', '.00', '687.00', '1,250.50', '0'], 'RATAS_SALDO');

    expect($profile['kind'])->toBe(ColumnValueProfiler::KIND_DECIMAL)
        ->and($profile['nonconforming'])->toBe(0)
        ->and($profile['example'])->toBe('1,250.50');
});

it('keeps amount headers that mention a date word numeric', function () {
    expect($this->profiler->profile(['15032019', '20261016', '12345678'], 'SALDO_POSISI')['kind'])->toBe(ColumnValueProfiler::KIND_INTEGER)
        ->and($this->profiler->profile(['20261016', '20261017'], 'TANGGAL_POSISI')['kind'])->toBe(ColumnValueProfiler::KIND_DATE);
});
