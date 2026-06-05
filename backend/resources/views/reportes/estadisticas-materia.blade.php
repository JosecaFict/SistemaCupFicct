@extends('reportes._layout')

@section('contenido')
    <h2>Estadisticas por materia</h2>
    <p style="font-size: 10px; color: #6b7280;">
        Promedio de notas validadas por materia y examen, en toda la gestion.
    </p>
    <table>
        <thead>
            <tr>
                <th>Materia</th>
                <th>Examen</th>
                <th class="text-right">Cant. notas</th>
                <th class="text-right">Promedio</th>
                <th class="text-right">Nota minima</th>
                <th class="text-right">Nota maxima</th>
                <th class="text-right">Descalifican</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $f)
                <tr>
                    <td><b>{{ $f['materia_codigo'] }}</b>  {{ $f['materia_nombre'] }}</td>
                    <td class="text-center">Examen {{ $f['numero_examen'] }}</td>
                    <td class="text-right">{{ $f['cantidad'] }}</td>
                    <td class="text-right">{{ number_format($f['promedio'], 2) }}</td>
                    <td class="text-right">{{ number_format($f['min'], 2) }}</td>
                    <td class="text-right">{{ number_format($f['max'], 2) }}</td>
                    <td class="text-right">{{ $f['descalifican'] }} ({{ number_format($f['pct_descalifican'], 1) }}%)</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
