<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        // Se não vier nenhum papel, apenas segue (ou você pode abortar 403 se preferir)
        if (empty($roles)) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            abort(401); // precisa estar autenticado
        }

        // Compara papel do usuário com a lista recebida
        if (!in_array($user->role, $roles, true)) {
            abort(403); // sem permissão
        }

        return $next($request);
    }
}
