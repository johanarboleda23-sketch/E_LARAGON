@props([
    'title',
    'number',
    'date' => null,
    'partyLabel' => 'Tercero',
    'party' => null,
    'status' => null,
    'fields' => [],
    'columns' => [],
    'rows' => [],
    'totals' => [],
    'actions' => [],
    'accountingUrl' => null,
    'accountingAction' => null,
    'accountingMessage' => null,
])

@once
    <style>
        .document-preview-panel{background:#fff;border:1px solid #b9ddd3;border-radius:16px;box-shadow:0 8px 22px rgba(25,37,34,.07);color:#192522;margin:0 0 18px;overflow:hidden}
        .document-preview-heading{align-items:flex-start;background:#edf7f4;border-bottom:1px solid #d7dfd8;display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;padding:16px}
        .document-preview-title{font-size:16px;font-weight:900;margin:0}
        .document-preview-meta{color:#52635b;font-size:12px;margin:5px 0 0}
        .document-preview-body{padding:16px}
        .document-preview-fields{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:16px}
        .document-preview-field{background:#f8faf8;border:1px solid #e6ede8;border-radius:10px;padding:10px}
        .document-preview-field dt{color:#71807a;font-size:10px;font-weight:800;text-transform:uppercase}
        .document-preview-field dd{font-size:12px;font-weight:700;margin:4px 0 0;overflow-wrap:anywhere}
        .document-preview-table-wrap{border:1px solid #e6ede8;border-radius:10px;overflow-x:auto}
        .document-preview-table{border-collapse:collapse;min-width:520px;width:100%}
        .document-preview-table th{background:#192522;color:#fff;font-size:10px;padding:9px;text-align:left}
        .document-preview-table td{border-top:1px solid #e6ede8;font-size:12px;padding:9px}
        .document-preview-totals{display:grid;gap:6px;margin:14px 0 0 auto;max-width:320px}
        .document-preview-total{display:flex;font-size:12px;gap:14px;justify-content:space-between}
        .document-preview-total:last-child{border-top:1px solid #d7dfd8;font-size:14px;font-weight:900;padding-top:8px}
        .document-preview-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
        .document-preview-action{background:#fff;border:1px solid #cbd6cf;border-radius:8px;color:#227c70;display:inline-flex;font-size:11px;font-weight:800;padding:8px 11px;text-decoration:none}
        .document-preview-action-primary{background:#227c70;border-color:#227c70;color:#fff}
        .document-preview-note{color:#71807a;font-size:12px;margin:12px 0 0}
        .dark .document-preview-panel{background:#17211e;border-color:#31534a;color:#e8f0ec}
        .dark .document-preview-heading{background:#1b302a;border-color:#31534a}
        .dark .document-preview-meta,.dark .document-preview-note{color:#b9c9c1}
        .dark .document-preview-field{background:#1d2b26;border-color:#31534a}
        .dark .document-preview-field dt{color:#b9c9c1}
        .dark .document-preview-table-wrap{border-color:#31534a}
        .dark .document-preview-table td{border-color:#31534a}
        .dark .document-preview-total:last-child{border-color:#31534a}
        .dark .document-preview-action{background:#1d2b26;border-color:#45665d;color:#d0e5dc}
        .dark .document-preview-action-primary{background:#227c70;border-color:#227c70;color:#fff}
    </style>
@endonce

<section class="document-preview-panel" aria-label="Vista previa del comprobante">
    <div class="document-preview-heading">
        <div>
            <p class="document-preview-title">{{ $title }} · {{ $number }}</p>
            <p class="document-preview-meta">
                @if ($date)
                    {{ $date }}
                @endif
                @if ($party)
                    · {{ $partyLabel }}: {{ $party }}
                @endif
                @if ($status)
                    · {{ $status }}
                @endif
            </p>
        </div>
        <span class="document-preview-action document-preview-action-primary">Guardado</span>
    </div>

    <div class="document-preview-body">
        @if ($fields)
            <dl class="document-preview-fields">
                @foreach ($fields as $field)
                    <div class="document-preview-field">
                        <dt>{{ $field['label'] }}</dt>
                        @php($fieldValue = $field['value'] ?? null)
                        <dd>{{ $fieldValue === null || $fieldValue === '' ? '—' : $fieldValue }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($columns && $rows)
            <div class="document-preview-table-wrap">
                <table class="document-preview-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($columns as $column)
                                    @php($cellValue = data_get($row, $column['key']))
                                    <td>{{ $cellValue === null || $cellValue === '' ? '—' : $cellValue }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($totals)
            <dl class="document-preview-totals">
                @foreach ($totals as $total)
                    <div class="document-preview-total">
                        <dt>{{ $total['label'] }}</dt>
                        <dd>{{ $total['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($actions || $accountingUrl || $accountingAction)
            <div class="document-preview-actions">
                @foreach ($actions as $action)
                    <a href="{{ $action['url'] }}" class="document-preview-action">{{ $action['label'] }}</a>
                @endforeach
                @if ($accountingUrl)
                    <a href="{{ $accountingUrl }}" target="_blank" rel="noopener" class="document-preview-action">Ver contabilización</a>
                @elseif ($accountingAction)
                    <form method="POST" action="{{ $accountingAction }}" onsubmit="return confirm('¿Contabilizar este documento ahora?')">
                        @csrf
                        <button class="document-preview-action" type="submit">Contabilizar ahora</button>
                    </form>
                @endif
            </div>
        @elseif ($accountingMessage)
            <p class="document-preview-note">{{ $accountingMessage }}</p>
        @endif
    </div>
</section>
