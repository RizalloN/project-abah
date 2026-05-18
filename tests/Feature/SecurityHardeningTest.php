<?php

use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('adds browser security headers to web responses', function () {
    $response = app()->handle(Request::create('/login', 'GET'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('X-Download-Options'))->toBe('noopen');
    expect($response->headers->get('X-DNS-Prefetch-Control'))->toBe('off');
    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('rejects legacy tracing methods before they reach controllers', function () {
    $middleware = new SecurityHeadersMiddleware;
    $request = Request::create('/login', 'TRACE');

    $response = $middleware->handle($request, fn () => response('should not run'));

    expect($response->getStatusCode())->toBe(405);
    expect($response->getContent())->toBe('Method Not Allowed');
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('rate limits sensitive admin control actions without throttling the import page', function () {
    $importPageRoute = Route::getRoutes()->getByName('import.index');
    $deleteRoute = Route::getRoutes()->getByName('import.report-management.delete');
    $userDeleteRoute = Route::getRoutes()->getByName('user-management.destroy');

    expect($importPageRoute?->gatherMiddleware())->not->toContain('throttle:admin-sensitive');
    expect($deleteRoute?->gatherMiddleware())->toContain('throttle:admin-sensitive');
    expect($userDeleteRoute?->gatherMiddleware())->toContain('throttle:admin-sensitive');
});

it('rate limits sensitive authentication actions', function () {
    $loginRoute = collect(Route::getRoutes())->first(
        fn ($route) => $route->uri() === 'login' && in_array('POST', $route->methods(), true)
    );
    $passwordRoute = Route::getRoutes()->getByName('password.update');

    expect($loginRoute?->gatherMiddleware())->toContain('throttle:auth-sensitive');
    expect($passwordRoute?->gatherMiddleware())->toContain('throttle:auth-sensitive');
});
