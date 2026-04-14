<?php

use Illuminate\Http\Request;

it('returns a successful response', function () {
    $response = app()->handle(Request::create('/', 'GET'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toContain('Dashboard A-Six');
});
