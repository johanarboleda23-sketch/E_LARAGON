<?php

namespace App\Http\Controllers;

use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\ThirdParty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PayrollController extends Controller
{
    public function index()
    {
        $employees = ThirdParty::where('is_employee', true)
            ->where('active', true)
            ->whereHas('contracts', fn ($query) => $query->where('active', true)->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
            }))
            ->orderBy('name')
            ->get();
        $runs = PayrollRun::with('lines.employee')->latest()->take(12)->get();

        return view('payroll.main', compact('employees', 'runs'));
    }

    public function calculate(Request $request)
    {
        $data = $request->validate(['period' => 'required|date_format:Y-m', 'payment_date' => 'required|date', 'employees' => 'required|array|min:1', 'employees.*.third_party_id' => 'required|exists:third_parties,id', 'employees.*.salary' => 'required|numeric|min:0']);
        $totals = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'cost' => 0];
        DB::transaction(function () use ($data, &$totals) {
            $run = PayrollRun::create(['period' => $data['period'], 'payment_date' => $data['payment_date'], 'status' => 'calculated', 'created_by' => auth()->id()]);
            foreach ($data['employees'] as $employee) {
                $salary = (float) $employee['salary'];
                $healthEmployee = round($salary * 0.04, 2);
                $pensionEmployee = round($salary * 0.04, 2);
                $solidarity = $salary >= 4 * 1300000 ? round($salary * 0.01, 2) : 0;
                $withholding = 0;
                $healthEmployer = round($salary * 0.085, 2);
                $pensionEmployer = round($salary * 0.12, 2);
                $parafiscals = round($salary * 0.09, 2);
                $severance = round($salary / 12, 2);
                $bonus = round($salary / 12, 2);
                $vacation = round($salary * 0.0417, 2);
                $deductions = $healthEmployee + $pensionEmployee + $solidarity + $withholding;
                $net = $salary - $deductions;
                $employerCost = $salary + $healthEmployer + $pensionEmployer + $parafiscals + $severance + $bonus + $vacation;
                PayrollLine::create(['payroll_run_id' => $run->id, 'third_party_id' => $employee['third_party_id'], 'salary' => $salary, 'health_employee' => $healthEmployee, 'pension_employee' => $pensionEmployee, 'solidarity_employee' => $solidarity, 'withholding' => $withholding, 'net_pay' => $net, 'health_employer' => $healthEmployer, 'pension_employer' => $pensionEmployer, 'parafiscals' => $parafiscals, 'severance_provision' => $severance, 'service_bonus_provision' => $bonus, 'vacation_provision' => $vacation, 'employer_cost' => $employerCost]);
                $totals['gross'] += $salary;
                $totals['deductions'] += $deductions;
                $totals['net'] += $net;
                $totals['cost'] += $employerCost;
            }
            $run->update(['total_gross' => $totals['gross'], 'total_deductions' => $totals['deductions'], 'total_net' => $totals['net'], 'total_employer_cost' => $totals['cost']]);
        });

        return back()->with('success', 'Nómina calculada automáticamente. Revisa los valores antes de contabilizar.');
    }

    public function email(Request $request, PayrollLine $line)
    {
        $line->load(['employee', 'payrollRun']);
        abort_unless($line->employee?->email, 422, 'El empleado no tiene un correo registrado.');

        $run = $line->payrollRun;
        $body = "Desprendible de nómina\n\n"
            ."Empleado: {$line->employee->name}\n"
            ."Período: {$run->period}\n"
            ."Fecha de pago: {$run->payment_date->format('Y-m-d')}\n\n"
            ."Salario: {$line->salary}\n"
            ."Salud empleado: {$line->health_employee}\n"
            ."Pensión empleado: {$line->pension_employee}\n"
            ."Fondo de solidaridad: {$line->solidarity_employee}\n"
            ."Retención: {$line->withholding}\n"
            ."Neto a pagar: {$line->net_pay}\n\n"
            ."Aportes empleador\n"
            ."Salud: {$line->health_employer}\n"
            ."Pensión: {$line->pension_employer}\n"
            ."Parafiscales: {$line->parafiscals}\n"
            ."Provisión cesantías: {$line->severance_provision}\n"
            ."Provisión prima: {$line->service_bonus_provision}\n"
            ."Provisión vacaciones: {$line->vacation_provision}\n"
            ."Costo total empresa: {$line->employer_cost}";

        Mail::raw($body, function ($message) use ($line, $run) {
            $message->to($line->employee->email)
                ->subject('Desprendible de nómina '.$run->period);
        });

        return back()->with('success', 'Nómina enviada a '.$line->employee->email.'.');
    }
}
