<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['company_id' => 'required|integer']);
        abort_unless($request->user()->companies()->where('companies.id', $data['company_id'])->exists(), 403, 'No tienes acceso a esa empresa.');
        session(['company_id' => (int) $data['company_id']]);

        return back()->with('success', 'Empresa activa cambiada correctamente.');
    }
}
