<?php

namespace App\Http\Middleware;

use App\Support\RolePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleWriteAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $user = $request->user();
        $company = $user?->companies()->where('companies.id', (int) session('company_id'))->first();
        $role = $company?->pivot->role;

        abort_unless(RolePermissions::canWrite($role, $module), 403, 'Tu rol no tiene permiso para modificar este módulo.');

        return $next($request);
    }
}
