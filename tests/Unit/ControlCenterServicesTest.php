<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ControlCenter\PhpMyAdminGateway;
use App\Services\ControlCenter\ReportDataFreshnessService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ControlCenterServicesTest extends TestCase
{
    public function test_freshness_is_judged_against_each_reports_own_rhythm(): void
    {
        $service = app(ReportDataFreshnessService::class);

        $this->assertSame('ok', $service->judge(1, 1, null)[0]);
        $this->assertSame('warn', $service->judge(5, 1, null)[0]);
        $this->assertSame('fail', $service->judge(24, 1, null)[0]);
        // A monthly report 40 days after its last period is still on rhythm.
        $this->assertSame('ok', $service->judge(40, 30, null)[0]);
        $this->assertSame('warn', $service->judge(0, 1, ['status' => 'failed_partial'])[0]);
    }

    public function test_cadence_is_the_median_gap_so_weekends_do_not_skew_it(): void
    {
        $service = app(ReportDataFreshnessService::class);
        $periods = array_map(static fn (string $date): CarbonImmutable => CarbonImmutable::parse($date), [
            '2026-10-20', '2026-10-19', '2026-10-16', '2026-10-15', '2026-10-14',
        ]);

        $this->assertSame(1, $service->cadenceDays($periods));
        $this->assertNull($service->cadenceDays([CarbonImmutable::parse('2026-10-20')]));
    }

    public function test_reports_without_imports_for_ninety_days_are_dormant(): void
    {
        $service = app(ReportDataFreshnessService::class);

        $this->assertTrue($service->isDormant(null));
        $this->assertTrue($service->isDormant(['finished_at' => now()->subDays(120)->toIso8601String()]));
        $this->assertFalse($service->isDormant(['finished_at' => now()->subDays(3)->toIso8601String()]));
    }

    public function test_phpmyadmin_gate_requires_the_account_password_and_is_bound_to_user_and_ip(): void
    {
        $gateway = app(PhpMyAdminGateway::class);
        $user = new User(['pn' => '90179583', 'role' => 'superadmin']);
        $user->id = 1;
        $user->password = Hash::make('benar-sekali');

        $request = $this->gatewayRequest($user, '10.0.0.5');
        $this->assertFalse($gateway->unlock($request, 'salah'));
        $this->assertFalse($gateway->state($request)['unlocked']);

        $this->assertTrue($gateway->unlock($request, 'benar-sekali'));
        $this->assertTrue($gateway->authorizeAndTouch($request));

        // Same session from another address is rejected and the gate is closed.
        $moved = $this->gatewayRequest($user, '10.0.0.9', $request->session());
        $this->assertFalse($gateway->authorizeAndTouch($moved));
        $this->assertFalse($gateway->state($request)['unlocked']);
    }

    public function test_phpmyadmin_gate_expires_after_idle_time(): void
    {
        $gateway = app(PhpMyAdminGateway::class);
        $user = new User(['pn' => '90179583', 'role' => 'superadmin']);
        $user->id = 1;
        $user->password = Hash::make('benar-sekali');
        $request = $this->gatewayRequest($user, '10.0.0.5');
        $this->assertTrue($gateway->unlock($request, 'benar-sekali'));

        $gate = $request->session()->get(PhpMyAdminGateway::SESSION_KEY);
        $gate['last_seen'] = time() - 3600;
        $request->session()->put(PhpMyAdminGateway::SESSION_KEY, $gate);

        $this->assertFalse($gateway->authorizeAndTouch($request));
    }

    public function test_phpmyadmin_paths_are_validated_before_any_upstream_call(): void
    {
        $gateway = app(PhpMyAdminGateway::class);
        $request = Request::create('/job-management/database/pma/x');

        $this->assertSame(400, $gateway->forward($request, '../../etc/passwd')->getStatusCode());
        $this->assertSame(400, $gateway->forward($request, 'index.php%00')->getStatusCode());
        $this->assertSame(400, $gateway->forward($request, 'a\\b')->getStatusCode());
    }

    public function test_phpmyadmin_redirects_and_cookies_stay_inside_the_gateway_path(): void
    {
        $gateway = app(PhpMyAdminGateway::class);
        config(['services.phpmyadmin.internal_url' => 'http://127.0.0.1/admin-db-secret-a1b2c3/']);

        $this->assertSame('/job-management/database/pma/index.php?route=/', $gateway->rewriteLocation('http://127.0.0.1/admin-db-secret-a1b2c3/index.php?route=/'));
        $this->assertSame('/job-management/database/pma/index.php', $gateway->rewriteLocation('http://localhost:80/admin-db-secret-a1b2c3/index.php'));
        $this->assertSame('/job-management/database/pma/x', $gateway->rewriteLocation('/admin-db-secret-a1b2c3/x'));
        // PmaAbsoluteUri may still name an old public domain.
        $this->assertSame('/job-management/database/pma/index.php?route=/', $gateway->rewriteLocation('http://asixdashboard.duckdns.org/admin-db-secret-a1b2c3/index.php?route=/'));
        $this->assertSame('https://example.com/', $gateway->rewriteLocation('https://example.com/'));

        $cookie = $gateway->rewriteCookie('phpMyAdmin=abc; path=/admin-db-secret-a1b2c3/; HttpOnly', true);
        $this->assertStringContainsString('path=/job-management/database/pma/', $cookie);
        $this->assertStringContainsString('Secure', $cookie);
    }

    public function test_application_session_cookies_are_never_forwarded_to_phpmyadmin(): void
    {
        $gateway = app(PhpMyAdminGateway::class);
        $session = (string) config('session.cookie');

        $forwarded = $gateway->forwardableCookies($session . '=secret; XSRF-TOKEN=t; remember_web_abc=r; phpMyAdmin=pma; pmaUser-1=u');

        $this->assertSame('phpMyAdmin=pma; pmaUser-1=u', $forwarded);
    }

    private function gatewayRequest(User $user, string $ip, $session = null): Request
    {
        $request = Request::create('/job-management/control/database/unlock', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]);
        $request->setLaravelSession($session ?? tap(app('session')->driver('array'))->start());
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
