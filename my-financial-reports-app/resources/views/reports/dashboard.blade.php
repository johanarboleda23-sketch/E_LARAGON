<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Reportes Financieros</title>
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
</head>
<body>
    <div class="container">
        <h1>Dashboard de Reportes Financieros</h1>
        
        <div class="report-filters">
            <form action="{{ route('reports.index') }}" method="GET">
                <label for="start_date">Fecha de Inicio:</label>
                <input type="date" id="start_date" name="start_date" required>

                <label for="end_date">Fecha de Fin:</label>
                <input type="date" id="end_date" name="end_date" required>

                <button type="submit">Filtrar Reportes</button>
            </form>
        </div>

        <div class="report-actions">
            <a href="{{ route('reports.export', ['format' => 'pdf']) }}" class="btn">Exportar a PDF</a>
            <a href="{{ route('reports.export', ['format' => 'excel']) }}" class="btn">Exportar a Excel</a>
        </div>

        <div class="report-list">
            <h2>Lista de Reportes</h2>
            <ul>
                @foreach($reports as $report)
                    <li>
                        <a href="{{ route('reports.show', $report->id) }}">{{ $report->title }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <script src="{{ asset('js/reports/index.js') }}"></script>
</body>
</html>