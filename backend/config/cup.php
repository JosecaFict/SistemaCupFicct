<?php

/*
| Configuracion del Sistema CUP FICCT
| --------------------------------------------------------------------------
| Constantes de negocio que NO deben estar hardcodeadas en el codigo.
| Acceso: config('cup.KEY') o Config::get('cup.KEY').
*/

return [

    /*
    | Docente - carga de asignaciones por gestion
    | ----------------------------------------------------------------------
    | Un docente debe tener al menos MIN_ASIGNACIONES en una gestion
    | (verificado por reporte del coordinador, no por bloqueo tecnico).
    | No puede superar MAX_ASIGNACIONES (bloqueo estricto en el controller).
    |
    | La regla se cuenta como filas activas en 'asignaciones_docente' para
    | esa gestion. Ejemplo: Mat-M1 + Mat-M2 + Fis-N1 + Fis-N2 = 4.
    */
    'MIN_ASIGNACIONES_DOCENTE' => 1,
    'MAX_ASIGNACIONES_DOCENTE' => 4,

    /*
    | Habilitacion docente <-> materia
    | ----------------------------------------------------------------------
    | Al asignar un docente a un grupo, se valida que este HABILITADO en la
    | materia (tabla docente_materias). Si BLOQUEO_ESTRICTO_HABILITACION es
    | true, se rechaza con 422; si false, solo se registra warning.
    |
    | Por decision del negocio en Ciclo 3: bloqueo estricto.
    */
    'BLOQUEO_ESTRICTO_HABILITACION' => true,

    /*
    | Descripcion profesional del docente (formulario estructurado - B1)
    | ----------------------------------------------------------------------
    | Reglas minimas al crear/editar el perfil del docente.
    */
    'DOCENTE_DESCRIPCION_MIN_EXPERIENCIAS' => 2,
    'DOCENTE_PROFESION_MIN_LENGTH'         => 5,
    'DOCENTE_EXPERIENCIA_MIN_LENGTH'       => 15,

];
