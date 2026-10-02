<?php

namespace App\Support;

use App\Models\ChartOfAccount;

class DefaultChartOfAccounts
{
    /**
     * Catálogo PUC estándar usado para iniciar la contabilidad de una empresa nueva.
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: string, 4: bool}>
     */
    public const ACCOUNTS = [
        ['1000', 'ACTIVO', 1, 'debit', false],
        ['1105', 'Caja', 1, 'debit', true],
        ['1110', 'Bancos', 1, 'debit', true],
        ['1305', 'Clientes', 1, 'debit', true],
        ['1355', 'Anticipos de impuestos y contribuciones', 1, 'debit', true],
        ['1435', 'Mercancías no fabricadas por la empresa', 1, 'debit', true],
        ['1504', 'Terrenos', 1, 'debit', true],
        ['1520', 'Maquinaria y equipo', 1, 'debit', true],
        ['1705', 'Gastos pagados por anticipado', 1, 'debit', true],
        ['2000', 'PASIVO', 2, 'credit', false],
        ['2105', 'Bancos nacionales', 2, 'credit', true],
        ['2205', 'Nacionales - Proveedores', 2, 'credit', true],
        ['2335', 'Costos y gastos por pagar', 2, 'credit', true],
        ['2365', 'Retención en la fuente', 2, 'credit', true],
        ['2367', 'Impuesto a las ventas retenido', 2, 'credit', true],
        ['2408', 'Impuesto sobre las ventas por pagar', 2, 'credit', true],
        ['2505', 'Salarios por pagar', 2, 'credit', true],
        ['2510', 'Cesantías consolidadas', 2, 'credit', true],
        ['2515', 'Intereses sobre cesantías', 2, 'credit', true],
        ['2520', 'Prima de servicios', 2, 'credit', true],
        ['2525', 'Vacaciones consolidadas', 2, 'credit', true],
        ['2605', 'Para obligaciones laborales', 2, 'credit', true],
        ['3000', 'PATRIMONIO', 3, 'credit', false],
        ['3105', 'Capital suscrito y pagado', 3, 'credit', true],
        ['3305', 'Reservas obligatorias', 3, 'credit', true],
        ['3605', 'Utilidad del ejercicio', 3, 'credit', true],
        ['4000', 'INGRESOS', 4, 'credit', false],
        ['4135', 'Comercio al por mayor y al por menor', 4, 'credit', true],
        ['4205', 'Otras ventas', 4, 'credit', true],
        ['5000', 'GASTOS', 5, 'debit', false],
        ['5105', 'Gastos de personal', 5, 'debit', true],
        ['5110', 'Honorarios', 5, 'debit', true],
        ['5115', 'Impuestos', 5, 'debit', true],
        ['5120', 'Arrendamientos', 5, 'debit', true],
        ['5135', 'Servicios', 5, 'debit', true],
        ['5140', 'Gastos legales', 5, 'debit', true],
        ['5150', 'Adecuación e instalación', 5, 'debit', true],
        ['5160', 'Depreciaciones', 5, 'debit', true],
        ['5195', 'Diversos', 5, 'debit', true],
        ['6000', 'COSTOS DE VENTAS', 6, 'debit', false],
        ['6135', 'Comercio al por mayor y al por menor', 6, 'debit', true],
        ['7000', 'COSTOS DE PRODUCCIÓN', 7, 'debit', false],
        ['7205', 'Mano de obra directa', 7, 'debit', true],
        ['7305', 'Costos indirectos', 7, 'debit', true],
        ['8000', 'CUENTAS DE ORDEN DEUDORAS', 8, 'debit', false],
        ['8105', 'Bienes y valores entregados en custodia', 8, 'debit', true],
        ['9000', 'CUENTAS DE ORDEN ACREEDORAS', 9, 'credit', false],
        ['9105', 'Bienes y valores recibidos en custodia', 9, 'credit', true],
    ];

    /**
     * Crea las cuentas estándar que todavía no existan para la empresa indicada. Es seguro
     * llamarlo varias veces: nunca duplica ni modifica cuentas ya creadas por el usuario.
     */
    public static function seedFor(int $companyId): void
    {
        foreach (self::ACCOUNTS as [$code, $name, $class, $nature, $allowsPosting]) {
            ChartOfAccount::withoutGlobalScope('company')->firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'name' => $name,
                    'class' => $class,
                    'nature' => $nature,
                    'allows_posting' => $allowsPosting,
                    'active' => true,
                ],
            );
        }
    }
}
