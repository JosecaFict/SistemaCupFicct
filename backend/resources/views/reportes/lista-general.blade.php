@extends('reportes._layout')

@section('contenido')
    <div class="meta">
        <b>Total postulantes:</b> {{ count($filas) }}
    </div>
    <table>
        <thead>
            <tr>
                <th>N</th>
                <th>Codigo</th>
                <th>Nombre completo</th>
                <th>CI</th>
                <th>1ra opcion</th>
                <th>2da opcion</th>
                <th>Grupo</th>
                <th>Estado</th>
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
                    <td>{{ $f['carrera_segunda'] ?? '-' }}</td>
                    <td>{{ $f['grupo'] ?? '-' }}</td>
                    <td>{{ $f['estado'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
