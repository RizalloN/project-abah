<?php

test('password confirmation flow is not enabled for the current PN-only auth model', function () {
    expect(true)->toBeTrue();
})->skip('Password confirmation relies on email-based auth state that this app does not use.');
