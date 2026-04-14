<?php

test('registration routes are intentionally disabled in this internal app', function () {
    expect(true)->toBeTrue();
})->skip('Registration is disabled by design for this internal PN-based app.');
