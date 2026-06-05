@extends('reportes._layout')

@section('contenido')
    <h2>Promedios generales</h2>
    <div class="kpi-row">
        <div class="kpi-cell">
            <div class="label">Total postulantes</div>
            <div class="value">{{ $kpis['total'] }}</div>
        </div>
        <div class="kpi-cell">
            <div class="label">Promedio general</div>
            <div class="value">{{ number_format($kpis['promedio_general'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="label">Nota mas alta</div>
            <div class="value">{{ number_format($kpis['nota_maxima'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="label">Nota mas baja</div>
            <div class="value">{{ number_format($kpis['nota_minima'], 2) }}</div>
        </div>
    </div>

    <h2>Promedio por carrera (primera opcion)</h2>
    <table>
        <thead>
            <tr>
                <th>Carrera</th>
                <th class="text-right">Postulantes</th>
                <th class="text-right">Promedio</th>
                <th class="text-right">Nota minima</th>
                <th class="text-right">Nota maxima</th>
            </tr>
        </thead>
        <tbody>
            @foreach($porCarrera as $c)
                <tr>
                    <td>{{ $c['codigo'] }}  {{ $c['nombre'] }}</td>
                    <td class="text-right">{{ $c['cantidad'] }}</td>
                    <td class="text-right">{{ number_format($c['promedio'], 2) }}</td>
                    <td class="text-right">{{ number_format($c['min'], 2) }}</td>
                    <td class="text-right">{{ number_format($c['max'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
