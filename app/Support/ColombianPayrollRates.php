<?php

namespace App\Support;

class ColombianPayrollRates
{
    /**
     * Tarifas de ARL por clase de riesgo (Decreto 1772 de 1994), a cargo del empleador.
     *
     * @var array<int, float>
     */
    public const ARL_RATES = [
        1 => 0.00522,
        2 => 0.01044,
        3 => 0.02436,
        4 => 0.0435,
        5 => 0.0696,
    ];

    public const SENA_RATE = 0.02;

    public const ICBF_RATE = 0.03;

    public const COMPENSATION_FUND_RATE = 0.04;

    public static function arlRate(int $riskClass): float
    {
        return self::ARL_RATES[$riskClass] ?? self::ARL_RATES[1];
    }
}
