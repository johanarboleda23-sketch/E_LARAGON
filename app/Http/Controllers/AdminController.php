<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FactusCredential;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\DefaultChartOfAccounts;
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
        $paymentMethods = PaymentMethod::query()->with('account')->orderBy('name')->get();
        if ($paymentMethods->isEmpty()) {
            foreach (['Efectivo', 'Bancos', 'Anticipo', 'Crédito'] as $name) {
                PaymentMethod::create(['name' => $name, 'is_editable' => true]);
            }
            $paymentMethods = PaymentMethod::query()->with('account')->orderBy('name')->get();
        }
        $postingAccounts = ChartOfAccount::query()->where('active', true)->where('allows_posting', true)->orderBy('code')->get(['id', 'code', 'name']);
        $factusCredential = $company->factusCredential;

        return view('admin.index', compact('company', 'companies', 'members', 'paymentMethods', 'postingAccounts', 'factusCredential'));
    }

    public function storeCompany(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => 'required|string|max:255', 'tax_id' => 'nullable|string|max:100', 'email' => 'nullable|email|max:255']);
        $company = Company::create($data + ['active' => true]);
        $company->users()->attach($request->user(), ['role' => 'owner']);
        DefaultChartOfAccounts::seedFor($company->id);

        return back()->with('success', 'Empresa creada, vinculada al administrador y con su catálogo PUC inicial.');
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

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'chart_of_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $paymentMethod->update($data);

        return back()->with('success', 'Cuenta PUC de la forma de pago actualizada.');
    }

    public function seedChartOfAccounts(Request $request)
    {
        $this->authorizeAdmin($request);
        DefaultChartOfAccounts::seedFor((int) session('company_id'));

        return back()->with('success', 'Catálogo PUC estándar restaurado: se crearon las cuentas que faltaban (sin duplicar ni modificar las existentes).');
    }

    public function updateFactusCredential(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'environment' => ['required', 'in:sandbox,production'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'invoice_numbering_range_id' => ['nullable', 'integer'],
            'payroll_numbering_range_id' => ['nullable', 'integer'],
        ]);
        $company = $request->user()->companies()->where('companies.id', session('company_id'))->firstOrFail();
        FactusCredential::updateOrCreate(['company_id' => $company->id], $data + ['active' => true]);

        return back()->with('success', 'Credenciales de Factus guardadas.');
    }
}
