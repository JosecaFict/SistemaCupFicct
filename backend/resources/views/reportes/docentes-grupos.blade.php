@extends('reportes._layout')

@section('contenido')
    <h2>Docentes por grupos</h2>
    <p style="font-size: 10px; color: #6b7280;">
        Asignaciones docente-grupo-materia de la gestion.
    </p>
    <table>
        <thead>
            <tr>
                <th>Docente</th>
                <th>Email</th>
                <th>Materias / Grupos</th>
                <th class="text-right">Total grupos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $f)
                <tr>
                    <td><b>{{ $f['docente_nombre'] }}</b></td>
                    <td style="font-size: 9px;">{{ $f['docente_email'] }}</td>
                    <td style="font-size: 10px;">
                        @foreach($f['asignaciones'] as $a)
                            <div>
                                <b>{{ $a['materia'] }}</b>  {{ $a['grupo'] }}
                                {{ $a['dias_semana'] }} {{ substr($a['hora_inicio'],0,5) }}-{{ substr($a['hora_fin'],0,5) }}
                                @if($a['ambiente'])  Aula {{ $a['ambiente'] }} @endif
                            </div>
                        @endforeach
                    </td>
                    <td class="text-right">{{ count($f['asignaciones']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
