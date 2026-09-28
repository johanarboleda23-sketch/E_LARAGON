<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Manifiesto {{ $route->code }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:0 auto;max-width:900px;padding:24px;font-size:14px}
        h1{font-size:20px;margin:0 0 4px;color:#115e59}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f3f4f6;font-size:11px;text-transform:uppercase}
        .actions{margin-bottom:20px}
        .actions button{border:0;background:#0f766e;color:white;padding:9px 14px;border-radius:5px;cursor:pointer}
        @media print{.actions{display:none}}
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Imprimir manifiesto</button></div>
    <h1>Manifiesto de entrega — Ruta {{ $route->code }}</h1>
    <p>Vehículo: {{ $route->vehicle_plate }} — Conductor: {{ $route->driver_name }} — Fecha: {{ $route->route_date->format('d/m/Y') }}</p>

    <table>
        <thead><tr><th>#</th><th>Cliente</th><th>Dirección</th><th>Ciudad</th><th>Dificultad</th><th>Firma / sello</th></tr></thead>
        <tbody>
            @foreach ($route->stops as $stop)
                <tr>
                    <td>{{ $stop->sequence }}</td>
                    <td>{{ $stop->customer_name }}</td>
                    <td>{{ $stop->address }}</td>
                    <td>{{ $stop->city }}</td>
                    <td>{{ $stop->difficulty_score }}</td>
                    <td style="height:40px"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
