<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\AccountingVoucher;
use App\Models\BankStatement;
use App\Models\CashRegisterMovement;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\User;
use App\Services\AccountingPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PeriodClosingController extends Controller
{
    private const DETAIL_TYPES = ['purchases', 'income', 'expenses', 'bank', 'costs'];

    public function __construct(private readonly AccountingPeriodService $periodService) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'detail' => ['nullable', 'in:'.implode(',', self::DETAIL_TYPES)],
        ]);
        $year = (int) ($validated['year'] ?? now()->year);
        $month = $validated['month'] ?? null;
        $detail = $validated['detail'] ?? null;
        if ($detail && ! $month) {
            abort(422, 'Selecciona un mes para consultar el detalle.');
        }

        $periods = AccountingPeriod::query()
            ->with(['closedBy', 'events.actor'])
            ->where(function ($query) use ($year): void {
                $query->where('period_key', (string) $year)
                    ->orWhere('period_key', 'like', $year.'-%');
            })
            ->get()
            ->keyBy(fn (AccountingPeriod $period): string => $period->period_type.'-'.$period->period_key);

        $months = [];
        for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
            $start = CarbonImmutable::create($year, $monthNumber, 1)->startOfMonth();
            $period = $periods->get('month-'.$start->format('Y-m'));
            $months[] = [
                'number' => $monthNumber,
                'name' => ucfirst($start->locale('es')->monthName),
                'period' => $period,
                'summary' => $this->monthlySummary($start),
                'readiness' => $this->periodService->monthReadiness($year, $monthNumber),
                'can_close' => $start->endOfMonth()->isPast() && ! $period?->isClosed(),
            ];
        }

        $yearPeriod = $periods->get('year-'.$year);
        $allMonthsClosed = collect($months)->every(fn (array $monthData): bool => $monthData['period']?->isClosed() ?? false);
        $yearSummary = [
            'purchases' => ['total' => array_sum(array_map(fn (array $monthData): float => $monthData['summary']['purchases']['total'], $months))],
            'income' => ['total' => array_sum(array_map(fn (array $monthData): float => $monthData['summary']['income']['total'], $months))],
            'expenses' => ['total' => array_sum(array_map(fn (array $monthData): float => $monthData['summary']['expenses']['total'], $months))],
            'bank' => ['total' => array_sum(array_map(fn (array $monthData): float => $monthData['summary']['bank']['total'], $months))],
            'costs' => ['total' => array_sum(array_map(fn (array $monthData): float => $monthData['summary']['costs']['total'], $months))],
        ];
        $detailRecords = $detail && $month
            ? $this->detailRecords($detail, $year, (int) $month)
            : null;
        $paymentRecords = in_array($detail, ['income', 'expenses'], true) && $month
            ? $this->paymentRecords($detail, $year, (int) $month)
            : null;
        $detailLabel = $detail ? $this->detailLabel($detail) : null;
        $isAdministrator = $this->isAdministrator($request->user(), (int) session('company_id'));

        return view('period-closing.index', compact(
            'year',
            'months',
            'yearPeriod',
            'yearSummary',
            'allMonthsClosed',
            'detail',
            'detailRecords',
            'paymentRecords',
            'detailLabel',
            'month',
            'isAdministrator',
        ));
    }

    public function closeMonth(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2200'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $this->periodService->closeMonth((int) $data['year'], (int) $data['month'], (int) $request->user()->id);

        return redirect()->route('period-closing.index', ['year' => $data['year']])
            ->with('success', 'Mes cerrado. Las compras, ingresos, egresos y conciliaciones del periodo quedaron protegidos.');
    }

    public function closeYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2200'],
        ]);

        $this->periodService->closeYear((int) $data['year'], (int) $request->user()->id);

        return redirect()->route('period-closing.index', ['year' => $data['year']])
            ->with('success', 'Año cerrado con informe anual. No se generó un asiento automático de cierre.');
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->periodService->reopen($period, (int) $request->user()->id, $data['reason']);

        $year = substr($period->period_key, 0, 4);

        return redirect()->route('period-closing.index', ['year' => $year])
            ->with('success', 'Periodo reabierto. El motivo quedó guardado en la auditoría.');
    }

    private function monthlySummary(CarbonImmutable $start): array
    {
        $dateRange = [$start->toDateString(), $start->endOfMonth()->toDateString()];
        $incomeVouchers = AccountingVoucher::query()
            ->where('voucher_type', 'recibo_caja')
            ->whereDate('voucher_date', '>=', $dateRange[0])
            ->whereDate('voucher_date', '<=', $dateRange[1]);
        $expenseVouchers = AccountingVoucher::query()
            ->whereIn('voucher_type', ['egreso', 'ajuste_contable'])
            ->whereDate('voucher_date', '>=', $dateRange[0])
            ->whereDate('voucher_date', '<=', $dateRange[1]);
        $incomePayments = $this->periodPayments('income', $dateRange);
        $expensePayments = $this->periodPayments('expenses', $dateRange);

        return [
            'purchases' => [
                'count' => Purchase::query()->whereDate('purchase_date', '>=', $dateRange[0])->whereDate('purchase_date', '<=', $dateRange[1])->count(),
                'total' => (float) Purchase::query()->whereDate('purchase_date', '>=', $dateRange[0])->whereDate('purchase_date', '<=', $dateRange[1])->sum('total_pagar'),
            ],
            'income' => [
                'count' => (clone $incomeVouchers)->count() + $incomePayments->count(),
                'total' => (float) (clone $incomeVouchers)->sum('total_debit') + (float) $incomePayments->sum('amount'),
            ],
            'expenses' => [
                'count' => (clone $expenseVouchers)->count() + $expensePayments->count(),
                'total' => (float) (clone $expenseVouchers)->sum('total_debit') + (float) $expensePayments->sum('amount'),
            ],
            'bank' => [
                'count' => BankStatement::query()->whereDate('transaction_date', '>=', $dateRange[0])->whereDate('transaction_date', '<=', $dateRange[1])->count(),
                'total' => (float) BankStatement::query()
                    ->whereDate('transaction_date', '>=', $dateRange[0])
                    ->whereDate('transaction_date', '<=', $dateRange[1])
                    ->selectRaw("SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END) as net_total")
                    ->value('net_total'),
            ],
            'costs' => [
                'count' => (int) PurchaseDetail::query()
                    ->where('purchase_line_type', 'producto')
                    ->whereHas('purchase', fn ($query) => $query->whereDate('purchase_date', '>=', $dateRange[0])->whereDate('purchase_date', '<=', $dateRange[1]))
                    ->distinct('item_id')
                    ->count('item_id'),
                'total' => (float) PurchaseDetail::query()
                    ->where('purchase_line_type', 'producto')
                    ->whereHas('purchase', fn ($query) => $query->whereDate('purchase_date', '>=', $dateRange[0])->whereDate('purchase_date', '<=', $dateRange[1]))
                    ->selectRaw('SUM(quantity * cost_price) as total_cost')
                    ->value('total_cost'),
            ],
        ];
    }

    private function detailRecords(string $type, int $year, int $month): LengthAwarePaginator
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $dateRange = [$start->toDateString(), $start->endOfMonth()->toDateString()];

        return match ($type) {
            'purchases' => Purchase::query()->with('details.item')->whereDate('purchase_date', '>=', $dateRange[0])->whereDate('purchase_date', '<=', $dateRange[1])->orderBy('purchase_date')->orderBy('id')->paginate(50)->withQueryString(),
            'income' => AccountingVoucher::query()->where('voucher_type', 'recibo_caja')->whereDate('voucher_date', '>=', $dateRange[0])->whereDate('voucher_date', '<=', $dateRange[1])->orderBy('voucher_date')->orderBy('id')->paginate(50)->withQueryString(),
            'expenses' => AccountingVoucher::query()->whereIn('voucher_type', ['egreso', 'ajuste_contable'])->whereDate('voucher_date', '>=', $dateRange[0])->whereDate('voucher_date', '<=', $dateRange[1])->orderBy('voucher_date')->orderBy('id')->paginate(50)->withQueryString(),
            'bank' => BankStatement::query()->with('paymentMethod')->whereDate('transaction_date', '>=', $dateRange[0])->whereDate('transaction_date', '<=', $dateRange[1])->orderBy('transaction_date')->orderBy('id')->paginate(50)->withQueryString(),
            'costs' => PurchaseDetail::query()
                ->with(['item', 'purchase'])
                ->where('purchase_line_type', 'producto')
                ->whereHas('purchase', fn ($query) => $query->whereDate('purchase_date', '>=', $dateRange[0])->whereDate('purchase_date', '<=', $dateRange[1]))
                ->orderBy('item_id')
                ->paginate(50)
                ->withQueryString(),
        };
    }

    private function paymentRecords(string $type, int $year, int $month): LengthAwarePaginator
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $dateRange = [$start->toDateString(), $start->endOfMonth()->toDateString()];
        $payableTypes = $type === 'income'
            ? [(new Sale)->getMorphClass()]
            : [(new Purchase)->getMorphClass(), (new SupportDocument)->getMorphClass()];

        return Payment::query()
            ->with('payable')
            ->whereDate('payment_date', '>=', $dateRange[0])
            ->whereDate('payment_date', '<=', $dateRange[1])
            ->whereIn('payable_type', $payableTypes)
            ->whereNotIn('id', CashRegisterMovement::query()->whereNotNull('payment_id')->select('payment_id'))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();
    }

    private function periodPayments(string $type, array $dateRange): Builder
    {
        $payableTypes = $type === 'income'
            ? [(new Sale)->getMorphClass()]
            : [(new Purchase)->getMorphClass(), (new SupportDocument)->getMorphClass()];

        return Payment::query()
            ->whereDate('payment_date', '>=', $dateRange[0])
            ->whereDate('payment_date', '<=', $dateRange[1])
            ->whereIn('payable_type', $payableTypes)
            ->whereNotIn('id', CashRegisterMovement::query()->whereNotNull('payment_id')->select('payment_id'));
    }

    private function detailLabel(string $type): string
    {
        return [
            'purchases' => 'Facturas de compras',
            'income' => 'Recibos de caja',
            'expenses' => 'Egresos y gastos',
            'bank' => 'Movimientos de conciliación bancaria',
            'costs' => 'Detalle mensual de costos por producto',
        ][$type];
    }

    private function isAdministrator(User $user, int $companyId): bool
    {
        $company = $user->companies()->where('companies.id', $companyId)->first();

        return $company && in_array($company->pivot->role, ['admin', 'owner'], true);
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($this->isAdministrator($request->user(), (int) session('company_id')), 403, 'Solo un administrador puede reabrir periodos contables.');
    }
}
