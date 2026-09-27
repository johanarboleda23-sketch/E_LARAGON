<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
    <title>Reportes Financieros</title>
</head>
<body>
    <div class="container">
        <h1>Reportes Financieros</h1>
        
        <form action="{{ route('reports.filter') }}" method="GET" id="reportFilterForm">
            @csrf
            <div class="form-group">
                <label for="start_date">Fecha de Inicio:</label>
                <input type="date" name="start_date" id="start_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="end_date">Fecha de Fin:</label>
                <input type="date" name="end_date" id="end_date" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Filtrar Reportes</button>
        </form>

        <div class="report-actions">
            <a href="{{ route('reports.export.pdf') }}" class="btn btn-secondary">Exportar a PDF</a>
            <a href="{{ route('reports.export.excel') }}" class="btn btn-secondary">Exportar a Excel</a>
        </div>

        <div class="report-results">
            @if(isset($reports) && count($reports) > 0)
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $report)
                            <tr>
                                <td>{{ $report->id }}</td>
                                <td>{{ $report->description }}</td>
                                <td>{{ $report->amount }}</td>
                                <td>{{ $report->date }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p>No se encontraron reportes para los criterios seleccionados.</p>
            @endif
        </div>
    </div>

    <script src="{{ asset('js/reports/index.js') }}"></script>
</body>
</html>