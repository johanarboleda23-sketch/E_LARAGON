<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureReportAccess
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar si el usuario está autenticado
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión para acceder a los reportes.');
        }

        // Verificar si el usuario tiene permisos para acceder a los reportes
        if (! Auth::user()->can('view-reports')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para acceder a los reportes.');
        }

        return $next($request);
    }
}
