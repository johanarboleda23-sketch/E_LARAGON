<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 403, 'Debes iniciar sesión para acceder a los módulos de la empresa.');

        $companyId = (int) session('company_id');
        $company = $user->companies()->where('companies.id', $companyId)->where('companies.active', true)->first();
        if (! $company) {
            $company = $user->companies()->where('companies.active', true)->orderBy('companies.id')->first();
        }

        abort_unless($company, 403, 'Tu usuario no tiene una empresa activa.');
        session(['company_id' => $company->id]);
        view()->share('activeCompany', $company);

        return $next($request);
    }
}
