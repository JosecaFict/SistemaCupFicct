<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Reporte' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 0; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0 0 4px 0; color: #1e40af; font-size: 18px; }
        .header .subtitle { color: #4b5563; font-size: 10px; }
        .meta { margin: 8px 0 14px 0; font-size: 9px; color: #6b7280; }
        .meta b { color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead { background: #1e40af; color: #fff; }
        thead th { padding: 6px 8px; text-align: left; font-weight: bold; font-size: 10px; }
        tbody td { padding: 4px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .total-row td { background: #dbeafe; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { position: fixed; bottom: 10px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        h2 { color: #1e40af; font-size: 14px; margin: 18px 0 6px 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .kpi-row { display: table; width: 100%; margin: 6px 0 12px 0; }
        .kpi-cell { display: table-cell; border: 1px solid #e5e7eb; padding: 8px; text-align: center; width: 25%; }
        .kpi-cell .label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .kpi-cell .value { font-size: 18px; font-weight: bold; color: #1e40af; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistema CUP FICCT  {{ $titulo }}</h1>
        <div class="subtitle">Facultad de Ingenieria en Ciencias de la Computacion y Telecomunicaciones</div>
    </div>
    <div class="meta">
        @if(isset($gestion))
            <b>Gestion:</b> {{ $gestion->codigo }}  {{ $gestion->nombre }} ({{ $gestion->estado }})<br>
        @endif
        <b>Fecha de generacion:</b> {{ now()->format('d/m/Y H:i') }}
    </div>

    @yield('contenido')

    <div class="footer">
        Sistema CUP FICCT  Reporte generado automaticamente
    </div>
</body>
</html>
