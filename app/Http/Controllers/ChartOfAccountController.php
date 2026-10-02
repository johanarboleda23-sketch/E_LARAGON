<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        $accounts = ChartOfAccount::with('parent')->orderBy('code')->get();
        $parents = ChartOfAccount::whereIn('account_type', ['clase', 'mayor'])->orderBy('code')->get();

        return view('accounting.puc.index', compact('accounts', 'parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('chart_of_accounts', 'code')->where('company_id', session('company_id'))],
            'name' => 'required|string|max:255',
            'class' => 'required|integer|between:1,9',
            'nature' => 'required|in:debit,credit',
            'account_type' => 'required|in:clase,mayor,auxiliar',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'has_due_date' => 'boolean',
        ]);
        $data['allows_posting'] = $data['account_type'] === 'auxiliar';
        $data['active'] = true;
        ChartOfAccount::create($data);

        return back()->with('success', 'Cuenta PUC creada correctamente.');
    }

    public function update(Request $request, ChartOfAccount $account)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nature' => 'required|in:debit,credit',
            'account_type' => 'required|in:clase,mayor,auxiliar',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'has_due_date' => 'boolean',
            'active' => 'boolean',
        ]);
        $data['allows_posting'] = $data['account_type'] === 'auxiliar';
        $account->update($data);

        return back()->with('success', 'Cuenta PUC actualizada.');
    }
}
