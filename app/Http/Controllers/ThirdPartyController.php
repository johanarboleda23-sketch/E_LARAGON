<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\ThirdParty;
use Illuminate\Http\Request;

class ThirdPartyController extends Controller
{
    public function index()
    {
        $thirdParties = ThirdParty::with('contracts')->latest()->get();
        $employees = ThirdParty::where('is_employee', true)->where('active', true)->orderBy('name')->get();

        return view('third-parties.index', compact('thirdParties', 'employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:natural,juridica',
            'name' => 'required|string|max:255',
            'document' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'is_customer' => 'nullable|boolean',
            'is_supplier' => 'nullable|boolean',
            'is_employee' => 'nullable|boolean',
        ]);
        abort_if(empty($data['is_customer']) && empty($data['is_supplier']) && empty($data['is_employee']), 422, 'Selecciona al menos un perfil.');
        ThirdParty::create([...$data, 'is_customer' => ! empty($data['is_customer']), 'is_supplier' => ! empty($data['is_supplier']), 'is_employee' => ! empty($data['is_employee']), 'active' => true]);

        return back()->with('success', 'Tercero registrado correctamente.');
    }

    public function update(Request $request, ThirdParty $thirdParty)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'document' => 'nullable|string|max:100', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'is_customer' => 'nullable|boolean', 'is_supplier' => 'nullable|boolean', 'is_employee' => 'nullable|boolean', 'active' => 'nullable|boolean']);
        $data['is_customer'] = ! empty($data['is_customer']);
        $data['is_supplier'] = ! empty($data['is_supplier']);
        $data['is_employee'] = ! empty($data['is_employee']);
        $thirdParty->update($data);

        return back()->with('success', 'Tercero actualizado.');
    }

    public function toggleActive(ThirdParty $thirdParty)
    {
        $thirdParty->update(['active' => ! $thirdParty->active]);

        return back()->with('success', 'Estado del tercero actualizado.');
    }

    public function storeContract(Request $request)
    {
        $data = $request->validate([
            'third_party_id' => 'required|exists:third_parties,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'salary' => 'required|numeric|min:0',
            'contract_type' => 'required|in:indefinido,fijo,obra_labor',
        ]);
        $employee = ThirdParty::where('is_employee', true)->findOrFail($data['third_party_id']);
        $initial = mb_strtoupper(mb_substr(trim($employee->name), 0, 1));
        $number = EmployeeContract::count() + 1;
        $data['company_id'] = session('company_id');
        $data['employee_initial'] = $initial;
        $data['consecutive'] = 'CTR-'.$initial.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
        $data['active'] = true;
        EmployeeContract::create($data);

        return back()->with('success', 'Contrato creado: '.$data['consecutive']);
    }

    public function toggleContract(EmployeeContract $contract)
    {
        $contract->update(['active' => ! $contract->active]);

        return back()->with('success', 'Estado del contrato actualizado.');
    }
}
