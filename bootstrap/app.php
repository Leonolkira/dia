<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );

    // Erreurs HTTP de l'API en français (les erreurs de validation passent par lang/fr)
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
        if (! $request->is('api/*')) {
            return null;
        }

        $status = $e->getStatusCode();

        $message = match (true) {
            $e->getMessage() === 'Your email address is not verified.' => 'Confirmez votre adresse e-mail pour continuer.',
            $status === 403 => "Vous n'avez pas le droit d'effectuer cette action.",
            $status === 404 => 'Ressource introuvable.',
            $status === 405 => 'Méthode non autorisée.',
            $status === 429 => 'Trop de tentatives. Réessayez dans une minute.',
            default => null,
        };

        return $message === null
            ? null
            : response()->json(['message' => $message], $status, $e->getHeaders());
    });

    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
        return $request->is('api/*')
            ? response()->json(['message' => 'Authentification requise.'], 401)
            : null;
    });
})
->create();
