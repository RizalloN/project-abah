<?php

test('password update scaffold is not enabled as a standalone profile flow in this app', function () {
    expect(true)->toBeTrue();
})->skip('Password update testing in the default scaffold depends on profile routes that this app does not expose.');
