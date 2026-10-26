<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust only Fly.io's private network and standard private ranges.
        // See App\Http\Middleware\TrustFlyProxies for details.
        $middleware->trustProxies(
            at: [
                '127.0.0.1',
                '::1',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                'fdaa::/16', // Fly.io private machine network (6PN)
            ]
        );
        $middleware->prepend(\App\Http\Middleware\SetClientIpFromFly::class);
        $middleware->web(append: \App\Http\Middleware\SetFrameHeaders::class);
        $middleware->statefulApi();
        $middleware->api(prepend: \App\Http\Middleware\ServerTiming::class);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A 413 means a request (in practice a photo upload) hit PHP's
        // post_max_size, which is a server limit rather than user error, so
        // send it to the error log (and Slack). It's logged while rendering
        // because PostTooLargeException is an HttpException, which Laravel
        // never reports (stopIgnoring() only matches the exact class name).
        // Returning nothing keeps the default 413 response.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::error('Request rejected: body larger than post_max_size', [
                'method' => $request->method(),
                'path' => $request->path(),
                'content_length' => (int) $request->server('CONTENT_LENGTH'),
                'post_max_size' => ini_get('post_max_size'),
                'user_id' => rescue(fn () => $request->user()?->id, report: false),
            ]);
        });

        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        // Implicit route-model binding failures surface (after Laravel's
        // prepareException) as a NotFoundHttpException wrapping a
        // ModelNotFoundException, whose message leaks internals to the client —
        // e.g. "No query results for model [App\Models\Page] duty-officer-guide".
        // Replace ONLY those with a clean, generic 404 for API requests; explicit
        // abort(404, '...') calls have no ModelNotFoundException previous and are
        // left untouched so their custom messages survive.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') && $e->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['message' => 'The requested resource was not found.'], 404);
            }
        });
    })->create();
