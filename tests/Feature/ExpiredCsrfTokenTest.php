<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExpiredCsrfTokenTest extends TestCase
{
    use RefreshDatabase;

    private function requestWithSession(string $uri, string $method, array $headers = []): Request
    {
        $request = Request::create($uri, $method, [], [], [], $headers);
        $session = $this->app['session.store'];
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    public function test_expired_token_on_logout_still_logs_the_user_out_and_goes_home(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $request = $this->requestWithSession('/logout', 'POST');

        $response = app(Handler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(url('/'), $response->headers->get('Location'));
        $this->assertFalse(Auth::check());
        $this->assertTrue($request->session()->has('success'));
    }

    public function test_expired_token_elsewhere_returns_to_the_previous_page_with_a_message(): void
    {
        $request = $this->requestWithSession('/admin/settings', 'PUT', ['HTTP_REFERER' => url('/admin/settings')]);

        $response = app(Handler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(url('/admin/settings'), $response->headers->get('Location'));
        $this->assertStringContainsString('有効期限', $request->session()->get('error'));
    }

    public function test_expired_token_on_json_request_is_still_a_419(): void
    {
        $request = $this->requestWithSession('/api/manual-schedules', 'POST', ['HTTP_ACCEPT' => 'application/json']);

        $response = app(Handler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(419, $response->getStatusCode());
    }
}
