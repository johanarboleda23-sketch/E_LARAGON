<?php

namespace App\Http\Controllers;

use App\Mail\RetentionCertificateMail;
use App\Models\AccountingVoucherLine;
use App\Models\RetentionCertificate;
use App\Models\ThirdParty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetentionCertificateController extends Controller
{
    private const ACCOUNTS = [
        RetentionCertificate::CUSTOMER => ['code' => '2365', 'label' => 'Retenciones practicadas a clientes', 'debit' => false, 'party' => 'is_customer'],
        RetentionCertificate::SUPPLIER => ['code' => '1355', 'label' => 'Retenciones sufridas de proveedores', 'debit' => true, 'party' => 'is_supplier'],
    ];

    public function recordsForYear(int $year)
    {
        return RetentionCertificate::query()
            ->where('taxable_year', $year)
            ->with('thirdParty')
            ->orderBy('kind')
            ->orderBy('third_party_name')
            ->get();
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'taxable_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'kind' => ['required', 'in:customer,supplier'],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['required', 'integer', 'distinct', Rule::exists('chart_of_accounts', 'id')->where('company_id', (int) session('company_id'))],
        ]);
        $configuration = self::ACCOUNTS[$data['kind']];
        $movements = $this->retentionMovements((int) $data['taxable_year'], $data['kind'], $data['account_ids']);
        $created = 0;
        $unmatched = 0;
        $missingEmail = 0;

        DB::transaction(function () use ($data, $configuration, $movements, &$created, &$unmatched, &$missingEmail): void {
            foreach ($movements as $movement) {
                $matches = ThirdParty::query()
                    ->where($configuration['party'], true)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($movement['name'])])
                    ->limit(2)
                    ->get();

                if ($matches->count() !== 1) {
                    $unmatched++;

                    continue;
                }
                $thirdParty = $matches->first();
                $missingEmail += blank($thirdParty->email) ? 1 : 0;

                $certificate = RetentionCertificate::query()->firstOrCreate(
                    [
                        'company_id' => (int) session('company_id'),
                        'taxable_year' => $data['taxable_year'],
                        'kind' => $data['kind'],
                        'third_party_id' => $thirdParty->id,
                    ],
                    [
                        'third_party_name' => $thirdParty->name,
                        'third_party_document' => $thirdParty->document,
                        'recipient_email' => $thirdParty->email,
                        'total_amount' => $movement['total'],
                        'account_breakdown' => $movement['accounts'],
                        'source_voucher_ids' => $movement['voucher_ids'],
                        'subject' => $this->defaultSubject($data['kind'], $data['taxable_year']),
                        'body' => $this->defaultBody($data['kind'], $data['taxable_year'], $thirdParty->name, $movement['total']),
                        'status' => 'draft',
                        'sent_at' => null,
                        'created_by' => auth()->id(),
                    ],
                );
                $created += $certificate->wasRecentlyCreated ? 1 : 0;
            }
        });

        return redirect()->route('reports.index', ['retention_year' => $data['taxable_year']])
            ->with('success', "Se crearon {$created} documento(s) de {$configuration['label']}. Los existentes se conservaron."
                .($unmatched ? " {$unmatched} tercero(s) no se pudieron identificar de forma única y fueron omitidos." : '')
                .($missingEmail ? " {$missingEmail} tercero(s) no tienen correo registrado." : ''));
    }

    public function edit(RetentionCertificate $certificate): View
    {
        return view('retention-certificates.edit', compact('certificate'));
    }

    public function update(Request $request, RetentionCertificate $certificate): RedirectResponse
    {
        $data = $request->validate([
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        $certificate->update([...$data, 'status' => 'draft', 'sent_at' => null]);

        return redirect()->route('reports.index', ['retention_year' => $certificate->taxable_year])->with('success', 'Certificado actualizado.');
    }

    public function send(RetentionCertificate $certificate): RedirectResponse
    {
        abort_if(blank($certificate->recipient_email), 422, 'El tercero no tiene un correo registrado.');
        if (! $this->hasDeliveryMailer()) {
            return back()->with('error', 'El correo no está configurado para realizar envíos reales.');
        }
        try {
            Mail::to($certificate->recipient_email)->send(new RetentionCertificateMail($certificate));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo enviar el correo. El documento sigue pendiente.');
        }
        $certificate->update(['status' => 'sent', 'sent_at' => now()]);

        return back()->with('success', 'Correo entregado al servicio de envío para '.$certificate->recipient_email.'.');
    }

    public function sendAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'taxable_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'kind' => ['required', 'in:customer,supplier'],
        ]);
        if (! $this->hasDeliveryMailer()) {
            return back()->with('error', 'El correo no está configurado para realizar envíos reales.');
        }
        $certificates = RetentionCertificate::query()
            ->where('taxable_year', $data['taxable_year'])
            ->where('kind', $data['kind'])
            ->where('status', 'draft')
            ->whereNotNull('recipient_email')
            ->where('recipient_email', '<>', '')
            ->get();
        $sent = 0;
        $failed = 0;

        foreach ($certificates as $certificate) {
            try {
                Mail::to($certificate->recipient_email)->send(new RetentionCertificateMail($certificate));
                $certificate->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return back()->with('success', "Se entregaron {$sent} documento(s) al servicio de correo. Los ya enviados y los que no tienen correo fueron omitidos."
            .($failed ? " Fallaron {$failed} envío(s); permanecen en borrador." : ''));
    }

    private function hasDeliveryMailer(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if (in_array($transport, ['log', 'array', null], true)) {
            return false;
        }
        if ($transport === 'failover') {
            foreach (config('mail.mailers.'.$mailer.'.mailers', []) as $fallback) {
                if (in_array(config('mail.mailers.'.$fallback.'.transport'), ['log', 'array', null], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function download(RetentionCertificate $certificate): View
    {
        return view('retention-certificates.document', compact('certificate'));
    }

    public function destroy(RetentionCertificate $certificate): RedirectResponse
    {
        $year = $certificate->taxable_year;
        $certificate->delete();

        return redirect()->route('reports.index', ['retention_year' => $year])->with('success', 'Registro eliminado.');
    }

    private function retentionMovements(int $year, string $kind, array $accountIds)
    {
        $configuration = self::ACCOUNTS[$kind];
        $lines = AccountingVoucherLine::query()
            ->whereHas('account', fn ($query) => $query->whereIn('id', $accountIds))
            ->whereHas('voucher', fn ($query) => $query->whereBetween('voucher_date', [$year.'-01-01', $year.'-12-31']))
            ->with(['account', 'voucher'])
            ->get();

        return $lines
            ->map(function (AccountingVoucherLine $line) use ($configuration): ?array {
                $name = trim((string) $line->voucher?->third_party);
                $amount = $configuration['debit'] ? (float) $line->debit - (float) $line->credit : (float) $line->credit - (float) $line->debit;
                if ($name === '' || $amount === 0.0) {
                    return null;
                }

                return [
                    'name' => $name,
                    'total' => $amount,
                    'accounts' => [$line->account->code => ['name' => $line->account->name, 'amount' => $amount]],
                    'voucher_ids' => [$line->voucher->id],
                ];
            })
            ->filter()
            ->groupBy(fn (array $movement): string => mb_strtolower($movement['name']))
            ->map(function ($items): array {
                $first = $items->first();
                $accounts = [];
                $voucherIds = [];
                foreach ($items as $item) {
                    foreach ($item['accounts'] as $code => $account) {
                        $accounts[$code]['name'] = $account['name'];
                        $accounts[$code]['amount'] = ($accounts[$code]['amount'] ?? 0) + $account['amount'];
                    }
                    $voucherIds = array_merge($voucherIds, $item['voucher_ids']);
                }

                return [
                    'name' => $first['name'],
                    'total' => round($items->sum('total'), 2),
                    'accounts' => $accounts,
                    'voucher_ids' => array_values(array_unique($voucherIds)),
                ];
            })
            ->filter(fn (array $movement): bool => $movement['total'] > 0)
            ->values();
    }

    private function defaultSubject(string $kind, int $year): string
    {
        return $kind === RetentionCertificate::CUSTOMER
            ? 'Certificado de retenciones practicadas - '.$year
            : 'Solicitud de certificado de retenciones - '.$year;
    }

    private function defaultBody(string $kind, int $year, string $name, float $total): string
    {
        if ($kind === RetentionCertificate::CUSTOMER) {
            return "Apreciado(a) {$name},\n\nCertificamos que durante el año gravable {$year} practicamos retenciones en la fuente a su nombre por valor total de $".number_format($total, 2, ',', '.').".\n\nEste certificado se expide con base en nuestros comprobantes contables y podrá ser utilizado para los fines tributarios correspondientes.";
        }

        return "Apreciado(a) {$name},\n\nSolicitamos amablemente el certificado de retenciones en la fuente que nos practicaron durante el año gravable {$year}. En nuestros registros contables figura un valor de referencia de $".number_format($total, 2, ',', '.')." asociado a sus operaciones.\n\nAgradecemos enviarlo a este mismo correo para completar nuestros soportes tributarios.";
    }
}
