@extends('reportes._layout')

@section('contenido')
    <div class="meta">
        <b>Total aprobados:</b> {{ count($filas) }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Ranking</th>
                <th>Codigo</th>
                <th>Nombre completo</th>
                <th>Carrera asignada</th>
                <th>Opcion</th>
                <th class="text-right">Nota final</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $f)
                <tr>
                    <td>{{ $f['ranking'] ?? '-' }}</td>
                    <td>{{ $f['codigo_publico'] }}</td>
                    <td>{{ $f['nombre_completo'] }}</td>
                    <td>{{ $f['carrera'] }}</td>
                    <td>
                        @if($f['opcion'] === 'PRIMERA') 1ra
                        @elseif($f['opcion'] === 'SEGUNDA') 2da
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float)$f['nota_final'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
