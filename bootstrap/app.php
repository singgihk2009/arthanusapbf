<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Services\AuthHomeRouteService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'restrict_inventory_reports_access' => \App\Http\Middleware\RestrictInventoryReportsAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function ($response, $exception, $request) {
            $statusCode = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : null;

            if ($exception instanceof TokenMismatchException || $statusCode === 419) {
                if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                    return $response;
                }

                $message = 'Sesi halaman sudah kedaluwarsa. Silakan muat ulang halaman lalu coba login kembali.';

                if ($request->is('login')) {
                    return redirect()->route('login')->with('error', $message);
                }

                return back()->with('error', $message);
            }

            if ($request->expectsJson()) {
                return $response;
            }

            if ($statusCode === 403 && $request->user()) {
                return redirect(app(AuthHomeRouteService::class)->resolve($request->user()))
                    ->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
            }

            return $response;
        });
    })->create();
