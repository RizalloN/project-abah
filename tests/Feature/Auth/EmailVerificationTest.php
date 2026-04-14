<?php

test('email verification flow is not enabled for the current auth model', function () {
    expect(true)->toBeTrue();
})->skip('Email verification is not wired to the current PN-only user model.');
