<?php

namespace App\Http\Controllers;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        $rates = CurrencyRate::latestPerCurrency();
        $history = CurrencyRate::with('creator')->orderByDesc('rate_date')->orderByDesc('id')->take(30)->get();

        return view('currency.index', [
            'rates' => $rates,
            'history' => $history,
            'suggestedCurrencies' => CurrencyRate::SUGGESTED_CURRENCIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'currency_code' => ['required', 'string', 'max:10'],
            'currency_name' => ['nullable', 'string', 'max:255'],
            'rate_to_cop' => ['required', 'numeric', 'min:0.0001'],
            'rate_date' => ['required', 'date'],
        ]);

        $data['currency_code'] = strtoupper($data['currency_code']);
        $data['currency_name'] = ($data['currency_name'] ?? null) ?: (CurrencyRate::SUGGESTED_CURRENCIES[$data['currency_code']] ?? $data['currency_code']);
        $data['created_by'] = auth()->id();

        $existing = CurrencyRate::query()
            ->where('currency_code', $data['currency_code'])
            ->whereDate('rate_date', $data['rate_date'])
            ->first();

        if ($existing) {
            $existing->update($data);
        } else {
            CurrencyRate::create($data);
        }

        return back()->with('success', "Tasa de {$data['currency_code']} del {$data['rate_date']} guardada correctamente.");
    }
}
