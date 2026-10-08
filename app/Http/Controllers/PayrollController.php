<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\PayrollSocialSecurityError;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use App\Services\FactusService;
use App\Support\ColombianPayrollRates;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PayrollController extends Controller
{
    public function __construct(
        private readonly AccountingEntryService $accountingEntryService,
        private readonly FactusService $factusService,
    ) {}

    public function index()
    {
        $employees = ThirdParty::where('is_employee', true)
            ->where('active', true)
            ->with(['contracts' => fn ($query) => $query->where('active', true)->latest('start_date')])
            ->whereHas('contracts', fn ($query) => $query->where('active', true)->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
            }))
            ->orderBy('name')
            ->get();
        $runs = PayrollRun::with('lines.employee')->latest()->take(12)->get();
        $socialSecurityErrors = PayrollSocialSecurityError::with('employee')
            ->where('resolved', false)
            ->latest()
            ->take(20)
            ->get();

        return view('payroll.index', compact('employees', 'runs', 'socialSecurityErrors'));
    }

    public function calculate(Request $request)
    {
        $data = $request->validate(['period' => 'required|date_format:Y-m', 'payment_date' => 'required|date', 'employees' => 'required|array|min:1', 'employees.*.third_party_id' => 'required|exists:third_parties,id', 'employees.*.salary' => 'required|numeric|min:0']);
        $totals = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'cost' => 0];
        $run = DB::transaction(function () use ($data, &$totals) {
            $run = PayrollRun::create(['period' => $data['period'], 'payment_date' => $data['payment_date'], 'status' => 'calculated', 'created_by' => auth()->id()]);
            foreach ($data['employees'] as $employee) {
                $salary = (float) $employee['salary'];
                $contract = EmployeeContract::where('third_party_id', $employee['third_party_id'])->where('active', true)->latest('start_date')->first();
                $riskClass = $contract?->arl_risk_class ?? 1;
                $healthEmployee = round($salary * 0.04, 2);
                $pensionEmployee = round($salary * 0.04, 2);
                $solidarity = $salary >= 4 * 1300000 ? round($salary * 0.01, 2) : 0;
                $withholding = 0;
                $healthEmployer = round($salary * 0.085, 2);
                $pensionEmployer = round($salary * 0.12, 2);
                $arlEmployer = round($salary * ColombianPayrollRates::arlRate($riskClass), 2);
                $sena = round($salary * ColombianPayrollRates::SENA_RATE, 2);
                $icbf = round($salary * ColombianPayrollRates::ICBF_RATE, 2);
                $compensationFund = round($salary * ColombianPayrollRates::COMPENSATION_FUND_RATE, 2);
                $parafiscals = $sena + $icbf + $compensationFund;
                $severance = round($salary / 12, 2);
                $bonus = round($salary / 12, 2);
                $vacation = round($salary * 0.0417, 2);
                $deductions = $healthEmployee + $pensionEmployee + $solidarity + $withholding;
                $net = $salary - $deductions;
                $employerCost = $salary + $healthEmployer + $pensionEmployer + $arlEmployer + $parafiscals + $severance + $bonus + $vacation;
                PayrollLine::create([
                    'payroll_run_id' => $run->id,
                    'third_party_id' => $employee['third_party_id'],
                    'salary' => $salary,
                    'health_employee' => $healthEmployee,
                    'pension_employee' => $pensionEmployee,
                    'solidarity_employee' => $solidarity,
                    'withholding' => $withholding,
                    'net_pay' => $net,
                    'health_employer' => $healthEmployer,
                    'pension_employer' => $pensionEmployer,
                    'arl_employer' => $arlEmployer,
                    'arl_risk_class' => $riskClass,
                    'sena' => $sena,
                    'icbf' => $icbf,
                    'compensation_fund' => $compensationFund,
                    'eps_name' => $contract?->eps_name,
                    'afp_name' => $contract?->afp_name,
                    'compensation_fund_name' => $contract?->compensation_fund_name,
                    'parafiscals' => $parafiscals,
                    'severance_provision' => $severance,
                    'service_bonus_provision' => $bonus,
                    'vacation_provision' => $vacation,
                    'employer_cost' => $employerCost,
                ]);
                $totals['gross'] += $salary;
                $totals['deductions'] += $deductions;
                $totals['net'] += $net;
                $totals['cost'] += $employerCost;
            }
            $run->update(['total_gross' => $totals['gross'], 'total_deductions' => $totals['deductions'], 'total_net' => $totals['net'], 'total_employer_cost' => $totals['cost']]);

            return $run;
        });

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postPayroll($run, $skipReason);

        $run->loadMissing('lines.employee');
        $factusSent = 0;
        $factusSkipReason = null;
        foreach ($run->lines as $line) {
            if ($this->factusService->sendPayrollInvoice($line, session('company_id'), $factusSkipReason)) {
                $factusSent++;
            }
        }

        $message = $accountingVoucher
            ? 'Nómina calculada y contabilizada automáticamente.'
            : 'Nómina calculada automáticamente. No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.');
        $message .= $factusSent === $run->lines->count()
            ? ' Enviada a la DIAN a través de Factus.'
            : " No se envió a la DIAN ({$factusSent}/{$run->lines->count()} empleados enviados): ".($factusSkipReason ?? 'revisa la configuración de Factus.');

        return back()->with('success', $message);
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
            ."ARL (clase {$line->arl_risk_class}): {$line->arl_employer}\n"
            ."SENA: {$line->sena}\n"
            ."ICBF: {$line->icbf}\n"
            ."Caja de compensación: {$line->compensation_fund}\n"
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

    public function show(PayrollRun $run)
    {
        $run->load('lines.employee', 'accountingVoucher');

        return view('payroll.show', compact('run'));
    }

    public function account(PayrollRun $run)
    {
        if ($run->accounting_voucher_id) {
            return back()->with('success', 'Esta nómina ya estaba contabilizada.');
        }

        $skipReason = null;
        $voucher = $this->accountingEntryService->postPayroll($run, $skipReason);

        return back()->with('success', $voucher
            ? '¡Nómina contabilizada correctamente!'
            : 'No se pudo contabilizar: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    public function socialSecurityFile(PayrollRun $run): Response
    {
        $run->load('lines.employee');
        $rows = [[
            'Tipo documento', 'Documento', 'Empleado', 'Periodo', 'Tipo cotizante',
            'EPS', 'AFP', 'Caja de compensación', 'Clase de riesgo ARL',
            'Salario básico', 'IBC salud', 'IBC pensión', 'IBC ARL', 'Salud empleado',
            'Pensión empleado', 'Salud empleador', 'Pensión empleador', 'ARL',
            'SENA', 'ICBF', 'Caja de compensación (aporte)', 'Observación',
        ]];

        foreach ($run->lines as $line) {
            $missing = array_keys(array_filter([
                'EPS' => ! $line->eps_name,
                'AFP' => ! $line->afp_name,
                'caja de compensación' => ! $line->compensation_fund_name,
            ]));

            $rows[] = [
                $line->employee?->type === 'juridica' ? 'NI' : 'CC',
                $line->employee?->document,
                $line->employee?->name,
                $run->period,
                'Dependiente',
                $line->eps_name,
                $line->afp_name,
                $line->compensation_fund_name,
                $line->arl_risk_class,
                $line->salary,
                $line->salary,
                $line->salary,
                $line->salary,
                $line->health_employee,
                $line->pension_employee,
                $line->health_employer,
                $line->pension_employer,
                $line->arl_employer,
                $line->sena,
                $line->icbf,
                $line->compensation_fund,
                $missing === [] ? 'Completa' : 'Falta registrar: '.implode(', ', $missing).' en el contrato del empleado.',
            ];
        }

        $content = collect($rows)
            ->map(fn (array $row): string => collect($row)->map(fn (mixed $value): string => $this->csvCell($value))->implode(';'))
            ->implode("\r\n");

        return response("\xEF\xBB\xBF".$content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"preparacion-ss-{$run->period}.csv\"",
        ]);
    }

    public function importSocialSecurityErrors(Request $request, PayrollRun $run)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($data['file']->getRealPath(), 'r');
        $delimiter = str_contains((string) fgets($handle), ';') ? ';' : ',';
        rewind($handle);
        $created = 0;
        fgetcsv($handle, escape: '\\');

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (count(array_filter($row)) < 2) {
                continue;
            }
            if (count($row) === 1) {
                $row = str_getcsv($row[0], $delimiter);
            }

            PayrollSocialSecurityError::create([
                'company_id' => (int) session('company_id'),
                'payroll_run_id' => $run->id,
                'line_number' => is_numeric($row[0] ?? null) ? (int) $row[0] : null,
                'field' => trim($row[1] ?? '') ?: null,
                'message' => trim($row[2] ?? $row[1] ?? 'Inconsistencia importada'),
            ]);
            $created++;
        }

        fclose($handle);

        return back()->with('success', "Se importaron {$created} inconsistencias de seguridad social.");
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")
            ? '"'.str_replace('"', '""', $value).'"'
            : $value;
    }
}
