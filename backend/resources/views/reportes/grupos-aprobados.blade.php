@extends('reportes._layout')

@section('contenido')
    <h2>Ranking de grupos por cantidad de aprobados</h2>
    <p style="font-size: 10px; color: #6b7280;">
        Cuantos postulantes de cada grupo terminaron ACEPTADOS en alguna carrera.
    </p>
    <table>
        <thead>
            <tr>
                <th>Posicion</th>
                <th>Grupo</th>
                <th>Turno</th>
                <th class="text-right">Total inscritos</th>
                <th class="text-right">Aceptados</th>
                <th class="text-right">Reprobados</th>
                <th class="text-right">Sin cupo</th>
                <th class="text-right">% aprobacion</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $i => $f)
                <tr>
                    <td><b>#{{ $i + 1 }}</b></td>
                    <td><b>{{ $f['grupo_codigo'] }}</b></td>
                    <td>{{ $f['turno_codigo'] }}</td>
                    <td class="text-right">{{ $f['total'] }}</td>
                    <td class="text-right" style="color: #065f46;"><b>{{ $f['aceptados'] }}</b></td>
                    <td class="text-right" style="color: #991b1b;">{{ $f['reprobados'] }}</td>
                    <td class="text-right" style="color: #92400e;">{{ $f['sin_cupo'] }}</td>
                    <td class="text-right"><b>{{ number_format($f['pct_aprobacion'], 1) }}%</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
