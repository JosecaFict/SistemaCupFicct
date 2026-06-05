@extends('reportes._layout')

@section('contenido')
    <div class="meta">
        <b>Total reprobados:</b> {{ count($filas) }} (no alcanzaron la nota minima)
    </div>
    <table>
        <thead>
            <tr>
                <th>N</th>
                <th>Codigo</th>
                <th>Nombre completo</th>
                <th>CI</th>
                <th>1ra opcion</th>
                <th class="text-right">Nota final</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $i => $f)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $f['codigo'] ?? '-' }}</td>
                    <td>{{ $f['nombre_completo'] }}</td>
                    <td>{{ $f['documento'] }}</td>
                    <td>{{ $f['carrera_primera'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float)$f['nota_final'], 2) }}</td>
                    <td>{{ $f['motivo'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
