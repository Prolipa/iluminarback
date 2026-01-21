<?php
namespace App\Repositories\Evaluaciones;

use App\Models\SalleEvaluaciones;
use App\Models\SallePreguntasEvaluacion;
use App\Repositories\BaseRepository;
use DB;
class  SalleRepository extends BaseRepository
{
    public function __construct(SalleEvaluaciones $salleEvaluaciones)
    {
        parent::__construct($salleEvaluaciones);
    }
    public function updateCalificacionFinal($id_evaluacion)
    {
        DB::beginTransaction(); // Inicia la transacción

        try {
            $calificacionFinal  = 0;
            $totalPuntaje       = 0;
            $calificacionTotal  = 0;
            // Obtener el tipo de calificación
            $getTipoCalificacion = SalleEvaluaciones::where('id_evaluacion', $id_evaluacion)->first();
            if (!$getTipoCalificacion) {
                throw new \Exception("No existe esa evaluación.");
            }

            $tipo_calificacion = $getTipoCalificacion->tipo_calificacion;

            // Calificación tipo 0: versión anterior
            if ($tipo_calificacion == 0) {

            } else {
                // Calificación tipo 1: solo puntaje por pregunta
                $query = SallePreguntasEvaluacion::where('id_evaluacion', $id_evaluacion)->get();

                foreach ($query as $value) {
                    $calificacionFinal += $value->calificacion_final;
                }
            }
            //total puntaje preguntas
            $query2 = DB::SELECT("SELECT DISTINCT * FROM salle_preguntas_evaluacion p
            LEFT JOIN salle_preguntas pp ON pp.id_pregunta = p.id_pregunta
            WHERE p.id_evaluacion = ?
            ",[$id_evaluacion]);
            foreach ($query2 as $key => $value) {
                $totalPuntaje += $value->puntaje_pregunta;
            }
            // Si el puntaje total es 0, evitar división por cero
            if ($totalPuntaje == 0) {
                throw new \Exception("El puntaje total no puede ser cero.");
            }

            // Calcular la calificación total
            $calificacionTotal = ($calificacionFinal / $totalPuntaje) * 100;
            // Actualizar la evaluación con la calificación final
            $salleEvaluaciones = SalleEvaluaciones::find($id_evaluacion);
            if (!$salleEvaluaciones) {
                throw new \Exception("Evaluación no encontrada.");
            }

            $salleEvaluaciones->calificacion_total = $calificacionTotal;
            $salleEvaluaciones->save();

            DB::commit(); // Si todo va bien, confirma la transacción

            return $calificacionTotal;

        } catch (\Exception $e) {
            DB::rollBack(); // Si ocurre un error, revierte la transacción

            // Lanza la excepción con el mensaje de error
            throw new \Exception('Error al actualizar la calificación final: ' . $e->getMessage());
        }
    }
    public function getCalificacionPreguntasXArea($id_evaluacion, $id_area)
    {
        $getTipoCalificacion = SalleEvaluaciones::where('id_evaluacion', $id_evaluacion)->first();
        if (!$getTipoCalificacion) {
            throw new \Exception("No existe esa evaluación.");
        }
        if ($getTipoCalificacion->tipo_calificacion == 0) {
            $query = DB::select(
                "SELECT r.id_pregunta, SUM(r.puntaje) AS puntaje
                FROM salle_respuestas_preguntas r
                LEFT JOIN salle_preguntas p ON r.id_pregunta = p.id_pregunta
                LEFT JOIN salle_asignaturas a ON p.id_asignatura = a.id_asignatura
                WHERE r.id_evaluacion = ? AND a.id_area = ?
                GROUP BY r.id_pregunta",
                [$id_evaluacion, $id_area]
            );
        } else {
            $query = DB::select(
                "SELECT p.id_pregunta, SUM(p.calificacion_final) AS puntaje
                FROM salle_preguntas_evaluacion p
                LEFT JOIN salle_preguntas pp ON pp.id_pregunta = p.id_pregunta
                LEFT JOIN salle_asignaturas a ON pp.id_asignatura = a.id_asignatura
                WHERE p.id_evaluacion = ? AND a.id_area = ?
                GROUP BY p.id_pregunta",
                [$id_evaluacion, $id_area]
            );
        }
        return $query;
    }
    public function puntajePorArea($id_evaluacion, $id_area)
    {
        $query2 = DB::select(
            "SELECT pp.id_pregunta, pp.puntaje_pregunta AS puntaje
            FROM salle_preguntas_evaluacion p
            LEFT JOIN salle_preguntas pp ON pp.id_pregunta = p.id_pregunta
            LEFT JOIN salle_asignaturas a ON pp.id_asignatura = a.id_asignatura
            WHERE p.id_evaluacion = ?
            AND a.id_area = ?
            GROUP BY pp.id_pregunta, pp.puntaje_pregunta",
            [$id_evaluacion, $id_area]
        );
        return $query2;
    }
    public function getIntentosXEvaluacion($id_evaluacion, $id_usuario){
        $query = DB::SELECT("SELECT COUNT(*) AS intentos
        FROM salle_evaluaciones
        WHERE id_evaluacion = ?
        AND id_usuario = ?
        AND estado IN (2)
        ",[$id_evaluacion, $id_usuario]);
        return $query;
    }
    public function getPreguntasXPeriodoTipo($periodo){
        $query = DB::SELECT("SELECT
            o.id_pregunta,
            COUNT(CASE WHEN o.tipo = 1 THEN 1 END) AS total_opciones_correctas,
            p.descripcion,
            a.nombre_asignatura,
            ar.nombre_area,
            tp.nombre_tipo,
            p.id_tipo_pregunta
        FROM salle_opciones_preguntas o
        LEFT JOIN salle_preguntas p
            ON p.id_pregunta = o.id_pregunta
        LEFT JOIN salle_asignaturas a ON a.id_asignatura = p.id_asignatura
        LEFT JOIN salle_areas ar ON ar.id_area = a.id_area
        LEFT JOIN tipos_preguntas tp ON tp.id_tipo_pregunta = p.id_tipo_pregunta
        WHERE p.n_evaluacion = ?
        AND p.estado = '1'
        GROUP BY o.id_pregunta, p.descripcion, a.nombre_asignatura, ar.nombre_area, tp.nombre_tipo, p.id_tipo_pregunta;
;
        ",[$periodo]);
        return $query;
    }
    public function getTiposPreguntas(){
        $query = DB::SELECT("SELECT *
        FROM tipos_preguntas
        ");
        return $query;
    }
    public function getEvaluacionesDocente($id_usuario,$periodo){
        $query = DB::SELECT("SELECT *
        FROM salle_evaluaciones
        WHERE id_usuario = ?
        AND n_evaluacion = ?
        AND estado <> '3'
        ",[$id_usuario,$periodo]);
        return $query;
    }
}
?>
