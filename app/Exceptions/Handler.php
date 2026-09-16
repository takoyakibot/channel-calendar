<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // A stale CSRF token usually means a tab was left open past the session
        // lifetime. Users stay signed in via the remember cookie, so the bare
        // 419 page is confusing: honour a logout anyway, and send other form
        // posts back to where they came from with an explanation.
        // By the time renderables run, the framework has already wrapped the
        // TokenMismatchException in a 419 HttpException, so match on that.
        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }
            if ($request->expectsJson()) {
                return null;
            }

            if ($request->is('logout')) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/')->with('success', 'ログアウトしました。');
            }

            // Use this request's referer explicitly; redirect()->back() would consult the
            // container-bound request, which is not necessarily the one that failed.
            $backTo = $request->headers->get('referer') ?: url('/');

            return redirect($backTo)->with('error', 'ページの有効期限が切れました。お手数ですが、もう一度操作してください。');
        });
    }
}
