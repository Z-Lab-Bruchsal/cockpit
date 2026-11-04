<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/client', fn (Request $request): array => ['ip' => $request->ip(), 'secure' => $request->isSecure()]);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    protected function trustProxies(?string $proxies): void
    {
        config(['app.trusted_proxies' => $proxies]);
        $this->app->getProvider(AppServiceProvider::class)->boot();
    }

    protected function requestVia(string $remoteAddress): array
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.5', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/_test/client')
            ->json();
    }

    public function test_forwarded_headers_are_ignored_without_trusted_proxies(): void
    {
        $this->trustProxies(null);

        $this->assertSame(['ip' => '172.18.0.1', 'secure' => false], $this->requestVia('172.18.0.1'));
    }

    public function test_client_ip_and_https_come_from_a_trusted_proxy(): void
    {
        $this->trustProxies('10.0.0.1, 172.16.0.0/12');

        $this->assertSame(['ip' => '203.0.113.5', 'secure' => true], $this->requestVia('172.18.0.1'));
    }

    public function test_forwarded_headers_from_other_peers_are_ignored(): void
    {
        $this->trustProxies('10.0.0.1');

        $this->assertSame(['ip' => '192.0.2.7', 'secure' => false], $this->requestVia('192.0.2.7'));
    }
}
