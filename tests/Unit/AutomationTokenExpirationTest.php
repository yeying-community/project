<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Services\AutomationTokenService;
use Carbon\Carbon;
use Tests\TestCase;

class AutomationTokenExpirationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expiration_uses_server_time_for_ninety_days(): void
    {
        config(['dootask.automation_token_max_days' => 90]);
        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00', 'Asia/Shanghai'));

        $expiresAt = AutomationTokenService::expirationFromDays(90);

        $this->assertSame('2027-01-09 12:00:00', $expiresAt->format('Y-m-d H:i:s'));
    }

    public function test_expiration_rejects_days_outside_configured_limit(): void
    {
        config(['dootask.automation_token_max_days' => 90]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('令牌有效期必须在90天以内');

        AutomationTokenService::expirationFromDays(91);
    }

    public function test_expiration_rejects_non_integer_days(): void
    {
        config(['dootask.automation_token_max_days' => 90]);

        $this->expectException(ApiException::class);

        AutomationTokenService::expirationFromDays('90.0');
    }

    public function test_legacy_missing_days_uses_default_without_client_timestamp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00', 'Asia/Shanghai'));

        $expiresAt = AutomationTokenService::expirationFromDays();

        $this->assertSame('2026-11-10 12:00:00', $expiresAt->format('Y-m-d H:i:s'));
    }
}
