<?php

uses(\Tests\TestCase::class);

it('keeps chart periodik dropdown selectors above chart cards', function () {
    $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/chart-periodik.blade.php'));

    expect($view)->toContain('.loan-dashboard .card.loan-shell {');
    expect($view)->toContain('z-index: 3000;');
    expect($view)->toContain('.loan-dashboard .card.loan-shell .card-body');
    expect($view)->toContain('overflow: visible !important;');
    expect($view)->toContain('.loan-dashboard .loan-dropdown-menu {');
    expect($view)->toContain('z-index: 3050;');
});
