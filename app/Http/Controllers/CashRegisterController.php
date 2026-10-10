<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function index(Request $request)
    {
        $fundType = $request->query('fund_type', 'general');

        $openSession = CashRegisterSession::where('fund_type', $fundType)->where('status', 'open')->first();
        if ($openSession) {
            $openSession->recalculateExpectedCash();
        }

        $recentSessions = CashRegisterSession::where('fund_type', $fundType)
            ->where('status', 'closed')
            ->latest('closed_at')
            ->take(15)
            ->get();

        return view('cash-register.index', [
            'fundType' => $fundType,
            'fundTypes' => CashRegisterSession::FUND_TYPES,
            'openSession' => $openSession,
            'recentSessions' => $recentSessions,
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'fund_type' => ['required', 'string', 'in:'.implode(',', array_keys(CashRegisterSession::FUND_TYPES))],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $alreadyOpen = CashRegisterSession::where('fund_type', $data['fund_type'])->where('status', 'open')->exists();
        abort_if($alreadyOpen, 422, 'Ya hay una sesión de esta caja abierta. Ciérrala antes de abrir una nueva.');

        CashRegisterSession::create([
            'fund_type' => $data['fund_type'],
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => $data['opening_balance'],
            'opened_by' => auth()->id(),
        ]);

        return redirect()->route('cash-register.index', ['fund_type' => $data['fund_type']])->with('success', 'Caja abierta correctamente.');
    }

    public function close(Request $request, CashRegisterSession $cashRegisterSession)
    {
        abort_unless($cashRegisterSession->isOpen(), 422, 'Esta sesión ya está cerrada.');

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cashRegisterSession->closed_at = now();
        $cashRegisterSession->recalculateExpectedCash();
        $cashRegisterSession->counted_cash = $data['counted_cash'];
        $cashRegisterSession->difference = round($data['counted_cash'] - $cashRegisterSession->expected_cash, 2);
        $cashRegisterSession->notes = $data['notes'] ?? null;
        $cashRegisterSession->status = 'closed';
        $cashRegisterSession->closed_by = auth()->id();
        $cashRegisterSession->save();

        $message = abs($cashRegisterSession->difference) < 0.01
            ? '¡Caja cuadrada perfectamente!'
            : ($cashRegisterSession->difference > 0
                ? 'Caja cerrada con sobrante de $'.number_format($cashRegisterSession->difference, 2).'.'
                : 'Caja cerrada con faltante de $'.number_format(abs($cashRegisterSession->difference), 2).'.');

        return redirect()->route('cash-register.index', ['fund_type' => $cashRegisterSession->fund_type])->with('success', $message);
    }
}
