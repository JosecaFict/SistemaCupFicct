@extends('reportes._layout')

@section('contenido')
    <div class="meta">
        <b>Total postulantes:</b> {{ $total }}
    </div>

    @php $n = 0; @endphp
    @foreach($grupos as $grupo => $filasGrupo)
        <h2>Grupo: {{ $grupo }}
            <span style="font-size:10px; color:#6b7280; font-weight:normal;">({{ count($filasGrupo) }})</span>
        </h2>
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
                @foreach($filasGrupo as $f)
                    @php $n++; @endphp
                    <tr>
                        <td>{{ $n }}</td>
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
    @endforeach
@endsection
