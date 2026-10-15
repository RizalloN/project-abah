<?php

use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('retains security headers and hides details on exception responses', function (int $status): void {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/security-exception-probe', function () use ($status): void {
        if ($status === 500) {
            throw new RuntimeException('private-exception-marker');
        }
        abort($status, 'private-exception-marker');
    });

    $response = $this->getJson('/security-exception-probe');

    $response->assertStatus($status)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    if ($status === 500) {
        $response->assertDontSee('private-exception-marker');
    }
})->with([403, 404, 419, 500]);

it('sets headers without looking up a session user during database failures', function (): void {
    $request = Request::create('/report/dashboard-pinjaman', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put(auth()->guard()->getName(), 12345);
    DB::shouldReceive('connection')->never();

    $response = app(SecurityHeadersMiddleware::class)
        ->withSecurityHeaders(response('unavailable', 503), $request);

    expect($response->getContent())->toBe('unavailable');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});
