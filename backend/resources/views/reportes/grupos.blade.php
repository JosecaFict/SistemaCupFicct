@extends('reportes._layout')

@section('contenido')
    <div class="kpi-row">
        <div class="kpi-cell">
            <div class="label">Total grupos</div>
            <div class="value">{{ $kpis['total_grupos'] }}</div>
        </div>
        <div class="kpi-cell">
            <div class="label">Activos</div>
            <div class="value">{{ $kpis['activos'] }}</div>
        </div>
        <div class="kpi-cell">
            <div class="label">Total inscritos</div>
            <div class="value">{{ $kpis['total_inscritos'] }}</div>
        </div>
        <div class="kpi-cell">
            <div class="label">Capacidad total</div>
            <div class="value">{{ $kpis['capacidad_total'] }}</div>
        </div>
    </div>

    <h2>Grupos habilitados</h2>
    <table>
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Turno</th>
                <th>Ambiente</th>
                <th class="text-right">Capacidad</th>
                <th class="text-right">Inscritos</th>
                <th class="text-right">% ocupacion</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $f)
                <tr>
                    <td><b>{{ $f['codigo'] }}</b></td>
                    <td>{{ $f['turno'] }}</td>
                    <td>{{ $f['ambiente'] ?? '-' }}</td>
                    <td class="text-right">{{ $f['capacidad'] }}</td>
                    <td class="text-right">{{ $f['inscritos'] }}</td>
                    <td class="text-right">{{ number_format($f['ocupacion'], 1) }}%</td>
                    <td>
                        <span class="badge {{ $f['estado'] === 'ACTIVO' ? 'badge-success' : 'badge-warning' }}">
                            {{ $f['estado'] }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
