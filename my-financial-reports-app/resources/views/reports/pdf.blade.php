<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Financiero</title>
    <link rel="stylesheet" href="{{ asset('assets/reports/reports.css') }}">
</head>
<body>
    <div class="report-container">
        <h1>Reporte Financiero</h1>
        <p>Fecha: {{ date('d/m/Y') }}</p>

        <table>
            <thead>
                <tr>
                    <th>Cuenta</th>
                    <th>Descripción</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach($financialMovements as $movement)
                    <tr>
                        <td>{{ $movement->account->name }}</td>
                        <td>{{ $movement->description }}</td>
                        <td>{{ number_format($movement->amount, 2, ',', '.') }}</td>
                        <td>{{ $movement->date->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <h2>Resumen</h2>
            <p>Total: {{ number_format($totalAmount, 2, ',', '.') }}</p>
        </div>
    </div>
</body>
</html>