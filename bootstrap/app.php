<?php

use App\Http\Middleware\EnsureUserHasFunction;
use App\Http\Middleware\PreventDynamicPageCaching;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'function' => EnsureUserHasFunction::class,
        ]);

        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            PreventDynamicPageCaching::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Laravel convertit TokenMismatchException en HttpException(419) avant de
        // consulter les callbacks render() (voir Handler::prepareException()) : un
        // callback type-hinte sur TokenMismatchException ne serait donc jamais
        // appele. On intercepte HttpException(419) et on verifie l'exception
        // d'origine via getPrevious().
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
                return response()->json(['message' => 'Session expirée, merci de recharger la page.'], 419);
            }

            // Ne jamais rediriger vers une URL interne de Livewire/API : un 302 sur
            // ce type d'URL ferait rejouer la requete suivante en GET cote
            // navigateur et provoquerait un 405 au lieu d'un rechargement propre.
            $target = $request->is('livewire/*', 'api/*') ? '/' : ($request->fullUrlWithoutQuery([]) ?: '/');

            return redirect($target)
                ->with('error', "Votre session a expiré. Merci de réessayer, l'opération n'a pas été enregistrée.");
        });
    })->create();
