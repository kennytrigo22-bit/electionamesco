<?php

namespace App\Http\Middleware;

use App\Models\Election;
use App\Models\Form;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasFunction
{
    /**
     * Gate les routes admin par fonction ("cei" ou "be"). super_admin passe toujours.
     */
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();

        $allowed = match ($area) {
            'cei' => $user?->can('manage', Election::class),
            'be' => $user?->can('manage', Form::class),
            default => false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
