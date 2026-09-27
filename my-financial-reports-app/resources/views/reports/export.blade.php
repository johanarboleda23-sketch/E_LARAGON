<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Reportes Financieros</title>
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
</head>
<body>
    <div class="container">
        <h1>Exportar Reportes Financieros</h1>
        <form action="{{ route('reports.export') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="report_type">Tipo de Reporte:</label>
                <select name="report_type" id="report_type" required>
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                </select>
            </div>
            <div class="form-group">
                <label for="date_range">Rango de Fechas:</label>
                <input type="text" name="date_range" id="date_range" placeholder="YYYY-MM-DD a YYYY-MM-DD" required>
            </div>
            <button type="submit" class="btn btn-primary">Exportar</button>
        </form>
    </div>
    <script src="{{ asset('js/reports/index.js') }}"></script>
</body>
</html>