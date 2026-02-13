<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SalleEvaluaciones;
use App\Repositories\Evaluaciones\SalleRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Provider\Image;
use Illuminate\Support\Facades\File;


class SalleReportesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    protected $salleRepository;
    public function __construct(SalleRepository $salleRepository)
    {
        $this->salleRepository = $salleRepository;
    }
    public function index()
    {


    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


    // REPORTES

    public function reporte_evaluaciones_institucion($n_evaluacion,$tipo_institucion)
    {
        //si es menor a 4 return reporte anterior
        if($n_evaluacion < 4){
            return $this->reporte_anterior($n_evaluacion,$tipo_institucion);
        }else{
            return $this->reporte_evaluaciones_institucionNueva($n_evaluacion,$tipo_institucion);
        }
    }
    public function reporte_anterior($n_evaluacion,$tipo_institucion){
        $evaluaciones = DB::SELECT("SELECT
        GROUP_CONCAT(CONCAT (se.id_evaluacion) ORDER BY se.id_evaluacion) AS evaluaciones,
        se.created_at AS fecha_evaluacion, i.idInstitucion, i.nombreInstitucion, i.ciudad_id
        FROM salle_evaluaciones se
        LEFT JOIN usuario u ON se.id_usuario = u.idusuario
        LEFT JOIN institucion i ON  u.institucion_idInstitucion = i.idInstitucion
        WHERE se.estado = 2
        AND i.idInstitucion != 1036
        AND se.n_evaluacion = '$n_evaluacion'
        AND i.tipo_institucion = '$tipo_institucion'
        GROUP BY i.idInstitucion
      ");
      // dump($evaluaciones);
      if(!empty($evaluaciones)){
          foreach ($evaluaciones as $key => $value) {
              $vector_evaluaciones = explode(",", $value->evaluaciones);
              $promedio_eval_inst = 0;
              $acum_eval = 0; $acum_doc = 0;
              // dump('*********************************institucion: ' . $value->idInstitucion);
              foreach ($vector_evaluaciones as $keyE => $valueE){
                  // $respuestas = DB::SELECT("SELECT SUM(sp.puntaje_pregunta) AS puntaje
                  // FROM salle_preguntas_evaluacion spe, salle_preguntas sp
                  // WHERE spe.id_evaluacion = id_evaluacion
                  // AND spe.id_pregunta = sp.id_pregunta");
                  $puntaje_respuestas = DB::SELECT("CALL salle_puntaje_respuestas (?);",[$valueE]);
                  // se acumula los puntajes de cada evaluacion por institucion
                  $acum_eval = $acum_eval + $puntaje_respuestas[0]->puntaje;
                  $puntaje_por_pregunta = DB::SELECT("CALL salle_puntaje_pregunta (?);",[$valueE]);
                  foreach ($puntaje_por_pregunta as $keyP => $valueP){
                      //puntaje obtenido por cada docente, cada evaluacion se califica por puntajes diferentes
                      $acum_doc = $acum_doc + $valueP->puntaje;
                  }
              }
              // dump($calificaciones);
              $promedio_eval_inst = ( $acum_doc * 100 ) / $acum_eval;
              $promedio_eval_inst = floatval(number_format($promedio_eval_inst, 2));
              $data['items'][$key] = [
                  'idInstitucion'     => $value->idInstitucion,
                  'nombreInstitucion' => $value->nombreInstitucion,
                  'fecha_evaluacion'  => $value->fecha_evaluacion,
                  'ciudad_id'         => $value->ciudad_id,
                  'puntaje'           => $promedio_eval_inst,
                  'cant_evaluaciones' => count($vector_evaluaciones)
              ];
          }
      }else{
          $data = [];
      }
      return $data;
    }
    public function reporte_evaluaciones_institucionNueva($n_evaluacion,$tipo_institucion)
    {

        $instituciones = DB::SELECT("SELECT
            GROUP_CONCAT(CONCAT(se.id_evaluacion) ORDER BY se.id_evaluacion) AS evaluaciones,
            se.created_at AS fecha_evaluacion, i.idInstitucion, i.nombreInstitucion, i.ciudad_id
            FROM salle_evaluaciones se
            LEFT JOIN usuario u ON se.id_usuario = u.idusuario
            LEFT JOIN institucion i ON  u.institucion_idInstitucion = i.idInstitucion
            WHERE se.estado = 2
            AND i.idInstitucion != 1036
            AND se.n_evaluacion = '$n_evaluacion'
            AND i.tipo_institucion = '$tipo_institucion'
            GROUP BY i.idInstitucion
        ");

        foreach($instituciones as $key => $item){

            // Cantidad de evaluaciones
            $item->cant_evaluaciones = count(explode(',', $item->evaluaciones));

            // Traer los docentes con sus áreas y calificaciones
            $areasDocentes = DB::SELECT("SELECT
                sub.nombreInstitucion,
                sub.nombre_usuario,
                sub.nombre_area,
                ROUND(AVG(sub.porcentaje_asignatura), 2) AS porcentaje_area_promedio
            FROM (
                SELECT
                    i.nombreInstitucion,
                    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_usuario,
                    a.nombre_area,
                    asig.nombre_asignatura,
                    ROUND((SUM(p.calificacion_final) / SUM(pr.puntaje_pregunta)) * 100, 2) AS porcentaje_asignatura
                FROM salle_preguntas_evaluacion p
                LEFT JOIN salle_preguntas pr ON pr.id_pregunta = p.id_pregunta
                LEFT JOIN salle_evaluaciones se ON se.id_evaluacion = p.id_evaluacion
                LEFT JOIN usuario u ON u.idusuario = se.id_usuario
                LEFT JOIN salle_asignaturas asig ON asig.id_asignatura = pr.id_asignatura
                LEFT JOIN salle_areas a ON a.id_area = asig.id_area
                LEFT JOIN institucion i ON i.idInstitucion = u.institucion_idInstitucion
                WHERE se.estado = '2'
                AND se.n_evaluacion = '$n_evaluacion'
                AND u.institucion_idInstitucion = '{$item->idInstitucion}'
                GROUP BY i.nombreInstitucion, u.idusuario, a.nombre_area, asig.nombre_asignatura
            ) AS sub
            GROUP BY sub.nombreInstitucion, sub.nombre_usuario, sub.nombre_area
            ORDER BY sub.nombre_usuario, sub.nombre_area;
            ");

            // Calcular promedio por área
            $promediosPorArea = [];
            foreach($areasDocentes as $docente){
                $area = $docente->nombre_area;
                if(!isset($promediosPorArea[$area])){
                    $promediosPorArea[$area] = ['suma' => 0, 'cantidad' => 0];
                }
                $promediosPorArea[$area]['suma'] += $docente->porcentaje_area_promedio;
                $promediosPorArea[$area]['cantidad']++;
            }

            $totalPromedioAreas = [];
            foreach($promediosPorArea as $area => $data){
                $totalPromedioAreas[] = [
                    'nombre_area' => $area,
                    'promedio_area' => round($data['suma'] / $data['cantidad'], 2)
                ];
            }

            $instituciones[$key]->total_promedio_areas = $totalPromedioAreas;
            $instituciones[$key]->areas = $areasDocentes;
        }

        // Calcular puntaje general por institución
        foreach($instituciones as $key => $item){
            if(!empty($item->total_promedio_areas)){
                $suma = 0;
                $cantidad = count($item->total_promedio_areas);
                foreach($item->total_promedio_areas as $area){
                    $suma += $area['promedio_area'];
                }
                $item->puntaje = round($suma / $cantidad, 2);
            } else {
                $item->puntaje = 0;
            }
        }

        return ['items' => $instituciones];



        // $evaluaciones = DB::SELECT("SELECT
        //   GROUP_CONCAT(CONCAT (se.id_evaluacion) ORDER BY se.id_evaluacion) AS evaluaciones,
        //   se.created_at AS fecha_evaluacion, i.idInstitucion, i.nombreInstitucion, i.ciudad_id
        //   FROM salle_evaluaciones se
        //   LEFT JOIN usuario u ON se.id_usuario = u.idusuario
        //   LEFT JOIN institucion i ON  u.institucion_idInstitucion = i.idInstitucion
        //   WHERE se.estado = 2
        //   AND i.idInstitucion != 1036
        //   AND se.n_evaluacion = '$n_evaluacion'
        //   AND i.tipo_institucion = 2
        //   GROUP BY i.idInstitucion
        // ");
        // // dump($evaluaciones);
        // if(!empty($evaluaciones)){
        //     foreach ($evaluaciones as $key => $value) {
        //         $vector_evaluaciones = explode(",", $value->evaluaciones);
        //         $promedio_eval_inst = 0;
        //         $totalCalificaciones = 0;
        //         // dump('*********************************institucion: ' . $value->idInstitucion);
        //         foreach ($vector_evaluaciones as $keyE => $valueE){
        //            $getEvaluacion = SalleEvaluaciones::where('id_evaluacion', $valueE)->first();
        //            if($getEvaluacion){
        //             //calificacion_total es sobre 100% calificacion
        //                $totalCalificaciones = $totalCalificaciones + $getEvaluacion->calificacion_total;
        //            }
        //         }
        //         // dump($calificaciones);
        //         $promedio_eval_inst = $totalCalificaciones / count($vector_evaluaciones);
        //         $promedio_eval_inst = floatval(number_format($promedio_eval_inst, 2));
        //         $data['items'][$key] = [
        //             'idInstitucion'     => $value->idInstitucion,
        //             'nombreInstitucion' => $value->nombreInstitucion,
        //             'fecha_evaluacion'  => $value->fecha_evaluacion,
        //             'ciudad_id'         => $value->ciudad_id,
        //             'puntaje'           => $promedio_eval_inst,
        //             'cant_evaluaciones' => count($vector_evaluaciones)
        //         ];
        //     }
        // }else{
        //     $data = [];
        // }
        // return $data;
    }

    // public function salle_promedio_areas($n_evaluacion, $institucion){
    //     //estado = 2; resuelta
    //     $evaluaciones = DB::SELECT("SELECT MAX(se.id_evaluacion) AS id_evaluacion,
    //         CONCAT(u.nombres, ' ', u.apellidos) AS nombre_docente, u.cedula, u.email
    //         FROM salle_evaluaciones se, usuario u
    //         WHERE se.estado = 2
    //         AND se.n_evaluacion = '$n_evaluacion'
    //         AND se.id_usuario = u.idusuario
    //         AND u.institucion_idInstitucion = '$institucion'
    //         GROUP BY u.idusuario
    //    ");
    //     $data_evaluaciones = array();
    //     foreach ($evaluaciones as $key => $value) {
    //         $areas = DB::SELECT("CALL salle_areas_evaluacion (?);",[$value->id_evaluacion]);
    //         $data_areas = array(); $promedio_eval_area_acum = 0;
    //         $totalCalificaciones = 0;
    //         $totalPuntajePreguntas = 0;
    //         $data_areas = [];
    //         foreach ($areas as $keyR => $valueR){
    //             $calificacionXArea = 0;
    //             $puntajeXArea = 0;
    //             $promediox_area = 0;
    //             $id_evaluacion = $value->id_evaluacion;
    //             //  // Obtener el tipo de calificación
    //             $query = $this->salleRepository->getCalificacionPreguntasXArea($id_evaluacion,$valueR->id_area);
    //             // // //totla puntaje respuestas
    //             foreach ($query as $keyRespuestas => $valuerRespuestas) {
    //                 $totalCalificaciones += $valuerRespuestas->puntaje;
    //                 $calificacionXArea += $valuerRespuestas->puntaje;
    //             }
    //             // //total puntaje por area
    //             $query2 = $this->salleRepository->puntajePorArea($id_evaluacion,$valueR->id_area);
    //             foreach ($query2 as $keyPuntaje => $valuePuntaje) {
    //                 $totalPuntajePreguntas += $valuePuntaje->puntaje;
    //                 $puntajeXArea += $valuePuntaje->puntaje;
    //             }
    //             $promediox_area = ( $calificacionXArea / $puntajeXArea ) * 100;
    //             $data_areas[$keyR] = [
    //                 'id_area'         => $valueR->id_area,
    //                 'nombre_area'     => $valueR->nombre_area,
    //                 'puntaje'         => round($promediox_area, 2),
    //             ];



    //         }//end foreach areas
    //         $promedio_eval_area = ( $totalCalificaciones / $totalPuntajePreguntas ) * 100;
    //         $data_evaluaciones['items'][$key] = [
    //             'id_evaluacion'         => $value->id_evaluacion,
    //             'puntaje_evaluacion'    => round($promedio_eval_area, 2),
    //             'nombre_docente'        => $value->nombre_docente,
    //             'areas'                 => $data_areas
    //         ];
    //     }
    //     // esta data devuelve los promedios por areas de cada evaluacion, se debe procesar en el front
    //     return $data_evaluaciones;
    // }


    /**
     * Calcula los promedios por áreas para evaluaciones completadas de una institución.
     *
     * @param string $n_evaluacion Número de evaluación.
     * @param string $institucion ID de la institución.
     * @return array Datos con promedios por evaluación y área o mensaje de error.
    */
    public function salle_promedio_areas($n_evaluacion, $institucion)
    {
        // Validar parámetros
        if (empty($n_evaluacion) || empty($institucion)) {
            return ['error' => 'Número de evaluación o institución no proporcionados'];
        }

        // Obtener evaluaciones completadas (estado = 2)
        $evaluaciones = DB::select(
            "SELECT MAX(se.id_evaluacion) AS id_evaluacion,
                    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_docente,
                    u.cedula,
                    u.email
            FROM salle_evaluaciones se
            JOIN usuario u ON se.id_usuario = u.idusuario
            WHERE se.estado = 2
            AND se.n_evaluacion = ?
            AND u.institucion_idInstitucion = ?
            GROUP BY u.idusuario",
            [$n_evaluacion, $institucion]
        );

        // Verificar si hay evaluaciones
        if (empty($evaluaciones)) {
            return ['error' => 'No se encontraron evaluaciones para los parámetros proporcionados'];
        }

        $data_evaluaciones = [];
        foreach ($evaluaciones as $key => $value) {
            //obtener las areas de cada evaluacion
            $areas = DB::select("CALL salle_areas_evaluacion(?);", [$value->id_evaluacion]);
            $data_areas = [];

            // Variables para el cálculo global real
            $total_puntaje_obtenido_evaluacion = 0;
            $total_puntaje_maximo_evaluacion = 0;

            foreach ($areas as $keyR => $valueR) {
                // Fetch subjects within this area
                $asignaturas = DB::select(
                    "SELECT sa.id_asignatura, sa.nombre_asignatura,
                            SUM(sp.puntaje_pregunta) AS puntaje_maximo,
                            SUM(spe.calificacion_final) AS puntaje_obtenido
                    FROM salle_evaluaciones se
                    JOIN salle_preguntas_evaluacion spe ON se.id_evaluacion = spe.id_evaluacion
                    JOIN salle_preguntas sp ON spe.id_pregunta = sp.id_pregunta
                    JOIN salle_asignaturas sa ON sp.id_asignatura = sa.id_asignatura
                    WHERE se.id_evaluacion = ?
                    AND sa.id_area = ?
                    GROUP BY sa.id_asignatura, sa.nombre_asignatura",
                    [$value->id_evaluacion, $valueR->id_area]
                );

                // Variables para el cálculo real por área
                $total_puntaje_obtenido_area = 0;
                $total_puntaje_maximo_area = 0;

                $subject_puntajes = [];
                foreach ($asignaturas as $asig) {
                    $calif_asig_eval = $asig->puntaje_maximo ?: 0;
                    $calif_asig_doc = $asig->puntaje_obtenido ?: 0;

                    // Acumular para cálculo real del área
                    $total_puntaje_obtenido_area += $calif_asig_doc;
                    $total_puntaje_maximo_area += $calif_asig_eval;

                    // Acumular para cálculo real de la evaluación
                    $total_puntaje_obtenido_evaluacion += $calif_asig_doc;
                    $total_puntaje_maximo_evaluacion += $calif_asig_eval;

                    $promedio_asig = ($calif_asig_eval > 0) ? ($calif_asig_doc * 100) / $calif_asig_eval : 0;
                    $promedio_asig = min($promedio_asig, 100);
                    $subject_puntajes[] = round($promedio_asig, 2);
                }

                // Calcular promedio real del área (no promedio de promedios)
                $promediox_area = ($total_puntaje_maximo_area > 0) ?
                    ($total_puntaje_obtenido_area * 100) / $total_puntaje_maximo_area : 0;
                $promediox_area = min($promediox_area, 100);

                $data_areas[] = [
                    'id_area'     => $valueR->id_area,
                    'nombre_area' => $valueR->nombre_area,
                    'puntaje'     => round($promediox_area, 2),
                    'subjects'    => $subject_puntajes,
                ];
            }

            // Calcular promedio real de la evaluación (no promedio de promedios)
            $promedio_eval_area = ($total_puntaje_maximo_evaluacion > 0) ?
                ($total_puntaje_obtenido_evaluacion * 100) / $total_puntaje_maximo_evaluacion : 0;
            $promedio_eval_area = min($promedio_eval_area, 100);

            $data_evaluaciones[] = [
                'id_evaluacion'      => $value->id_evaluacion,
                'puntaje_evaluacion' => round($promedio_eval_area, 2),
                'nombre_docente'     => $value->nombre_docente,
                'areas'              => $data_areas
            ];
        }

        return ['items' => $data_evaluaciones];
    }


    // public function salle_promedio_asignatura($periodo, $institucion, $id_area){
    //     $evaluaciones = DB::SELECT("SELECT MAX(se.id_evaluacion) AS id_evaluacion,
    //     CONCAT(u.nombres, ' ', u.apellidos) AS nombre_docente, u.cedula, u.email
    //     FROM salle_evaluaciones se, usuario u, salle_preguntas_evaluacion spe,
    //     salle_preguntas sp, salle_asignaturas sa
    //     WHERE se.estado = 2
    //     AND se.n_evaluacion = '$periodo'
    //     AND se.id_usuario = u.idusuario
    //     AND u.institucion_idInstitucion = '$institucion'
    //     AND se.id_evaluacion = spe.id_evaluacion
    //     AND spe.id_pregunta = sp.id_pregunta
    //     AND sp.id_asignatura = sa.id_asignatura
    //     AND sa.id_area = id_area
    //     GROUP BY u.idusuario
    //     ");
    //     $data_evaluaciones = array();
    //     foreach ($evaluaciones as $key => $value) {
    //         // asignaturas de cada evaluacion
    //         // $asignaturas = DB::SELECT("SELECT sa.id_asignatura, sa.nombre_asignatura
    //         // FROM salle_evaluaciones se, salle_preguntas_evaluacion sr, salle_preguntas sp, salle_asignaturas sa
    //         // WHERE se.id_evaluacion = '$value->id_evaluacion'
    //         // AND se.id_evaluacion = sr.id_evaluacion
    //         // AND sr.id_pregunta = sp.id_pregunta
    //         // AND sp.id_asignatura =sa.id_asignatura
    //         // AND sa.id_area = '$id_area'
    //         // GROUP BY sa.id_asignatura
    //         // ");
    //         $asignaturas = DB::SELECT("CALL salle_asignaturas_evaluacion (?, ?);",[$value->id_evaluacion, $id_area]);
    //         $data_asignaturas = array(); $promedio_eval_asig_acum = 0;
    //         foreach ($asignaturas as $keyA => $valueA){
    //             $calif_asig_eval = 0; $calif_asig_doc = 0; $promedio_eval_asig = 0; $promedio_eval_asignatura = 0;
    //             // puntaje de la evaluacion por asignatura y cantidad de preguntas por asignatura
    //             $puntaje_asignaturas = DB::SELECT("CALL salle_puntaje_evaluacion_asignaturas (?, ?);",[$value->id_evaluacion, $valueA->id_asignatura]);
    //             $calif_asig_eval = $puntaje_asignaturas[0]->puntaje;
    //             //obtener los puntajes por cada  pregunta
    //             // $puntaje_por_pregunta = DB::SELECT("SELECT sr.id_pregunta, sr.id_usuario,
    //             //     IF(SUM(sr.puntaje)>=0
    //             //     AND SUM(sr.puntaje)<=sp.puntaje_pregunta,SUM(sr.puntaje),
    //             //     (IF(sp.id_tipo_pregunta!=1, sp.puntaje_pregunta, 0))) AS puntaje
    //             //     FROM salle_respuestas_preguntas sr, salle_preguntas sp
    //             //     WHERE sr.id_evaluacion = '$value->id_evaluacion'
    //             //     AND sr.id_pregunta = sp.id_pregunta
    //             //     AND sp.id_asignatura = '$valueA->id_asignatura'
    //             //     GROUP BY sr.id_pregunta
    //             // ");
    //             $puntaje_por_pregunta = DB::SELECT(" SELECT a.id_asignatura, a.nombre_asignatura, SUM(p.calificacion_final) AS puntaje
    //             FROM salle_preguntas_evaluacion p
    //             LEFT JOIN salle_preguntas pp ON pp.id_pregunta = p.id_pregunta
    //             LEFT JOIN salle_asignaturas a ON pp.id_asignatura = a.id_asignatura
    //             WHERE p.id_evaluacion = '$value->id_evaluacion'
    //             AND pp.id_asignatura =  '$valueA->id_asignatura'
    //             ");
    //             // $puntaje_por_pregunta = DB::SELECT("CALL salle_puntaje_pregunta_asig (?, ?);",[$value->id_evaluacion, $valueA->id_asignatura]);
    //             foreach ($puntaje_por_pregunta as $keyP => $valueP){
    //                 //puntaje obtenido de cada docente por asig
    //                 $calif_asig_doc = $calif_asig_doc + $valueP->puntaje;
    //             }
    //             if( $calif_asig_doc <= 0 ){ $promedio_eval_asig = 0; }
    //             else{ $promedio_eval_asig = ( $calif_asig_doc * 100 ) / $calif_asig_eval; }
    //             if( $promedio_eval_asig > 100 ){ $promedio_eval_asig = 100; }
    //             $data_asignaturas[$keyA] = [
    //                 'id_asignatura'         => $puntaje_asignaturas[0]->id_asignatura,
    //                 'nombre_asignatura'     => $puntaje_asignaturas[0]->nombre_asignatura,
    //                 'puntaje'               => floatval(number_format($promedio_eval_asig, 2)),
    //             ];
    //             $promedio_eval_asig_acum    += $promedio_eval_asig;
    //         }
    //         if( count($asignaturas) > 0 ){
    //             $puntaje_evaluacion = $promedio_eval_asig_acum / count($asignaturas);
    //         }else{
    //             $puntaje_evaluacion = 0;
    //         }
    //         $data_evaluaciones['items'][$key] = [
    //             'id_evaluacion'         => $value->id_evaluacion,
    //             'puntaje_evaluacion'    => floatval(number_format($puntaje_evaluacion, 2)),
    //             'nombre_docente'        => $value->nombre_docente,
    //             'asignaturas'           => $data_asignaturas
    //         ];
    //     }
    //     // esta data devuelve los promedios por asignaturas de cada evaluacion, se debe procesar en el front
    //     return $data_evaluaciones;
    // }

public function salle_promedio_asignatura($periodo, $institucion, $id_area) {
    // Consulta inicial para evaluaciones
    $evaluaciones = DB::SELECT("
        SELECT MAX(se.id_evaluacion) AS id_evaluacion,
               CONCAT(u.nombres, ' ', u.apellidos) AS nombre_docente,
               u.cedula, u.email, u.idusuario
        FROM salle_evaluaciones se
        JOIN usuario u ON se.id_usuario = u.idusuario
        JOIN salle_preguntas_evaluacion spe ON se.id_evaluacion = spe.id_evaluacion
        JOIN salle_preguntas sp ON spe.id_pregunta = sp.id_pregunta
        JOIN salle_asignaturas sa ON sp.id_asignatura = sa.id_asignatura
        WHERE se.estado = 2
          AND se.n_evaluacion = ?
          AND u.institucion_idInstitucion = ?
          AND sa.id_area = ?
        GROUP BY u.idusuario, u.nombres, u.apellidos, u.cedula, u.email",
        [$periodo, $institucion, $id_area]
    );

    if (empty($evaluaciones)) {
        return ['items' => [], 'mensaje' => 'No se encontraron evaluaciones'];
    }

    $data_evaluaciones = ['items' => []];

    foreach ($evaluaciones as $key => $value) {
        // Obtener asignaturas
        $asignaturas = DB::SELECT("
            SELECT sa.id_asignatura, sa.nombre_asignatura
            FROM salle_evaluaciones se
            JOIN salle_preguntas_evaluacion sr ON se.id_evaluacion = sr.id_evaluacion
            JOIN salle_preguntas sp ON sr.id_pregunta = sp.id_pregunta
            JOIN salle_asignaturas sa ON sp.id_asignatura = sa.id_asignatura
            WHERE se.id_evaluacion = ?
              AND sa.id_area = ?
            GROUP BY sa.id_asignatura, sa.nombre_asignatura",
            [$value->id_evaluacion, $id_area]
        );

        $data_asignaturas = [];
        $total_obtenido_global = 0;
        $total_maximo_global = 0;

        foreach ($asignaturas as $keyA => $valueA) {
            // Obtener puntaje máximo y obtenido por asignatura
            $puntaje_asignaturas = DB::SELECT("
                SELECT sa.id_asignatura, sa.nombre_asignatura,
                       SUM(sp.puntaje_pregunta) AS puntaje_maximo,
                       SUM(spe.calificacion_final) AS puntaje_obtenido
                FROM salle_preguntas_evaluacion spe
                JOIN salle_preguntas sp ON spe.id_pregunta = sp.id_pregunta
                JOIN salle_asignaturas sa ON sp.id_asignatura = sa.id_asignatura
                WHERE spe.id_evaluacion = ?
                  AND sp.id_asignatura = ?
                GROUP BY sa.id_asignatura, sa.nombre_asignatura",
                [$value->id_evaluacion, $valueA->id_asignatura]
            );

            $calif_asig_eval = !empty($puntaje_asignaturas) ? $puntaje_asignaturas[0]->puntaje_maximo : 0;
            $calif_asig_doc = !empty($puntaje_asignaturas) ? $puntaje_asignaturas[0]->puntaje_obtenido : 0;
            $promedio_asig = ($calif_asig_eval > 0) ? ($calif_asig_doc * 100) / $calif_asig_eval : 0;
            $promedio_asig = min($promedio_asig, 100);

            // Acumular para el cálculo global
            $total_obtenido_global += $calif_asig_doc;
            $total_maximo_global += $calif_asig_eval;

            $data_asignaturas[$keyA] = [
                'id_asignatura'     => $valueA->id_asignatura,
                'nombre_asignatura' => $valueA->nombre_asignatura,
                'puntaje'           => round($promedio_asig, 2),
            ];
        }

        // Calcular puntaje_evaluacion como promedio global
        $promedios_asignaturas = array_column($data_asignaturas, 'puntaje');
        $puntaje_evaluacion = !empty($promedios_asignaturas) ? array_sum($promedios_asignaturas) / count($promedios_asignaturas) : 0;
        $puntaje_evaluacion = min(round($puntaje_evaluacion, 2), 100);

        $data_evaluaciones['items'][$key] = [
            'id_evaluacion'      => $value->id_evaluacion,
            'puntaje_evaluacion' => $puntaje_evaluacion,
            'nombre_docente'     => $value->nombre_docente,
            'idusuario'          => $value->idusuario,
            'asignaturas'        => $data_asignaturas,
        ];
    }

    return $data_evaluaciones;
}


    public function salle_promedios_tipos_pregunta($periodo, $institucion, $id_asignatura){

    }


}
