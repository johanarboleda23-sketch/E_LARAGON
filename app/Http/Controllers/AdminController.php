<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        $company = $request->user()->companies()->where('companies.id', session('company_id'))->first();
        abort_unless($company && in_array($company->pivot->role, ['admin', 'owner'], true), 403, 'Solo un administrador puede acceder a este panel.');
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $company = $request->user()->companies()->where('companies.id', session('company_id'))->firstOrFail();
        $companies = $request->user()->companies()->withCount('users')->orderBy('name')->get();
        $members = $company->users()->orderBy('name')->get();

        return view('admin.index', compact('company', 'companies', 'members'));
    }

    public function storeCompany(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => 'required|string|max:255', 'tax_id' => 'nullable|string|max:100', 'email' => 'nullable|email|max:255']);
        $company = Company::create($data + ['active' => true]);
        $company->users()->attach($request->user(), ['role' => 'owner']);

        return back()->with('success', 'Empresa creada y vinculada al administrador.');
    }

    public function addMember(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['email' => 'required|email|exists:users,email', 'role' => 'required|in:admin,contador,auxiliar,vendedor,consulta']);
        $company = $request->user()->companies()->where('companies.id', session('company_id'))->firstOrFail();
        $user = User::where('email', $data['email'])->firstOrFail();
        $company->users()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);

        return back()->with('success', 'Usuario agregado a la empresa.');
    }

    public function updateRole(Request $request, User $user)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['role' => 'required|in:admin,contador,auxiliar,vendedor,consulta']);
        $company = $request->user()->companies()->where('companies.id', session('company_id'))->firstOrFail();
        abort_unless($company->users()->whereKey($user->id)->exists(), 404);
        $company->users()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return back()->with('success', 'Rol actualizado.');
    }
}
