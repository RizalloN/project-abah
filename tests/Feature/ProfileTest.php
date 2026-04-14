<?php

test('profile management scaffold is not enabled for this app', function () {
    expect(true)->toBeTrue();
})->skip('Profile routes and email-based profile fields are not part of the current PN-only app.');
