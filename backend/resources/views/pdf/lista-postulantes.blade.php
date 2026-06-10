<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lista de postulantes - {{ $grupo->codigo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 0; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 0 0 2px 0; color: #1e40af; font-size: 16px; }
        .header .subtitle { color: #4b5563; font-size: 10px; }
        .meta { margin: 6px 0 12px 0; font-size: 10px; color: #374151; }
        .meta .row { margin-bottom: 3px; }
        .meta b { color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        thead { background: #1e40af; color: #fff; }
        thead th { padding: 5px 8px; text-align: left; font-weight: bold; font-size: 11px; }
        tbody td { padding: 6px 8px; border-bottom: 1px solid #d1d5db; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .num { width: 36px; text-align: right; color: #6b7280; font-family: monospace; }
        .cod { width: 110px; font-family: monospace; font-size: 10px; color: #1f2937; }
        .footer { position: fixed; bottom: 10px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
        .vacio { text-align: center; padding: 16px; color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CUP FICCT - Lista de postulantes</h1>
        <div class="subtitle">Facultad de Ingenieria en Ciencias de la Computacion y Telecomunicaciones</div>
    </div>

    <div class="meta">
        <div class="row">
            <b>Gestion:</b> {{ $gestion->codigo }}
            &nbsp;&nbsp;&nbsp;<b>Grupo:</b> {{ $grupo->codigo }}
            &nbsp;&nbsp;&nbsp;<b>Materia:</b> {{ $materia->codigo }} - {{ $materia->nombre }}
        </div>
        <div class="row">
            <b>Horario:</b> {{ $horario }}
            &nbsp;&nbsp;&nbsp;<b>Ambiente:</b> {{ $ambienteUbicacion ?: '-' }}
            &nbsp;&nbsp;&nbsp;<b>Aula:</b> {{ $ambienteNombre ?: '-' }}
        </div>
        <div class="row">
            <b>Docente:</b> {{ $docente }}
            &nbsp;&nbsp;&nbsp;<b>Total:</b> {{ count($postulantes) }}
            &nbsp;&nbsp;&nbsp;<b>Fecha de impresion:</b> {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="num">#</th>
                <th class="cod" style="color:#fff;">Codigo postulante</th>
                <th>Apellido y Nombre</th>
            </tr>
        </thead>
        <tbody>
            @forelse($postulantes as $i => $p)
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td class="cod">{{ $p['codigo'] }}</td>
                    <td>{{ $p['nombre'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">No hay postulantes asignados a este grupo.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Sistema CUP FICCT - Lista de postulantes - Generada automaticamente
    </div>
</body>
</html>
