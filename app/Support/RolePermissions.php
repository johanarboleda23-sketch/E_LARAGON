<?php

namespace App\Support;

class RolePermissions
{
    /**
     * Roles (besides admin/owner, who always have full access) allowed to create,
     * update or delete records in each module.
     *
     * @var array<string, array<int, string>>
     */
    public const WRITE_ROLES = [
        'commercial-documents' => ['vendedor', 'auxiliar'],
        'purchases' => ['auxiliar'],
        'sales' => ['vendedor', 'auxiliar'],
        'pos' => ['vendedor', 'auxiliar'],
        'items' => ['auxiliar'],
        'accounting-vouchers' => ['contador'],
        'puc' => ['contador'],
        'support-documents' => ['auxiliar', 'contador'],
        'third-parties' => ['auxiliar', 'vendedor', 'contador'],
        'payroll' => ['contador'],
        'bulk-operations' => ['auxiliar', 'vendedor', 'contador'],
        'bank-reconciliation' => ['contador'],
        'logistics' => ['auxiliar'],
        'numbering-resolutions' => ['contador'],
        'currency' => ['contador', 'auxiliar'],
        'cash-register' => ['contador', 'auxiliar', 'vendedor'],
    ];

    public static function canWrite(?string $role, string $module): bool
    {
        if (in_array($role, ['admin', 'owner'], true)) {
            return true;
        }

        return in_array($role, self::WRITE_ROLES[$module] ?? [], true);
    }
}
