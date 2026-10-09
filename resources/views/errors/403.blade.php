<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso no permitido</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f3f5f1;color:#192522;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px;margin:0}
        .box{max-width:480px;background:white;border-radius:16px;padding:32px;box-shadow:0 14px 30px rgba(25,37,34,.08);border:1px solid #d7dfd8;text-align:center}
        h1{font-size:20px;margin:0 0 12px}
        p{color:#71807a;font-size:14px;line-height:1.5;margin:0 0 20px}
        a{display:inline-block;background:#227c70;color:white;padding:10px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:bold}
    </style>
</head>
<body>
    <div class="box">
        <h1>🔒 Acceso no permitido</h1>
        <p>{{ $exception->getMessage() ?: 'No tienes permiso para realizar esta acción.' }}</p>
        <a href="{{ route('dashboard') }}">⌂ Ir al tablero</a>
    </div>
</body>
</html>
