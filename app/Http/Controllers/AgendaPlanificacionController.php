<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agenda;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AgendaPlanificacionController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'startDate' => 'required|date',
            'hora_inicio' => 'required',
            'usuario_creador' => 'required|integer',
            'opciones' => 'required|string',
            'url' => 'nullable|string'
        ], [
            'title.required' => 'El título es obligatorio',
            'startDate.required' => 'La fecha de inicio es obligatoria',
            'hora_inicio.required' => 'La hora de inicio es obligatoria',
            'usuario_creador.required' => 'El usuario creador es requerido',
            'opciones.required' => 'Debe seleccionar al menos una opción'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $opciones = json_decode($request->opciones, true);

        $tieneOpcion = false;
        if (is_array($opciones)) {
            foreach ($opciones as $key => $value) {
                if ($value === true) {
                    $tieneOpcion = true;
                    break;
                }
            }
        }

        if (!$tieneOpcion) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar al menos una opción'
            ], 422);
        }

        // Validar que no exista una planificación duplicada
        $query = Agenda::where('title', $request->title)
            ->where('startDate', $request->startDate)
            ->whereIn('estado', [0, 1]); // Solo validar estados generada (0) o finalizada (1)

        // Validar según tipo de institución
        if ($request->estado_institucion_temporal == 1) {
            // Si es institución temporal, validar por institucion_id_temporal
            $query->where('institucion_id_temporal', $request->institucion_id_temporal);
        } else {
            // Si es institución normal, validar por institucion_id
            $query->where('institucion_id', $request->institucion_id);
        }

        $existeDuplicado = $query->exists();

        if ($existeDuplicado) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una planificación con el mismo título y fecha para esta institución'
            ], 422);
        }

        try {
            $planificacion = new Agenda();
            $planificacion->title = $request->title;
            $planificacion->startDate = $request->startDate;
            $planificacion->hora_inicio = $request->hora_inicio;
            $planificacion->hora_fin = $request->hora_fin;
            $planificacion->usuario_creador = $request->usuario_creador;
            $planificacion->id_usuario = $request->usuario_creador;
            $planificacion->opciones = $request->opciones;
            $planificacion->url = $request->url;
            $planificacion->estado = 0;
            $planificacion->endDate = $request->endDate;
            // $planificacion->fecha_que_visita = $request->fecha_que_visita;
            $planificacion->label = $request->label ?? 'default';
            $planificacion->classes = $request->classes ?? '';
            // Manejar institución normal vs temporal (misma lógica que save_agenda_docente)
            if ($request->estado_institucion_temporal == 1) {
                $planificacion->periodo_id = $request->periodo_id;
                $planificacion->institucion_id_temporal = $request->institucion_id_temporal;
                $planificacion->nombre_institucion_temporal = $request->nombre_institucion_temporal;
                $planificacion->institucion_id = null;
            } else {
                $planificacion->institucion_id = $request->institucion_id;
                $planificacion->institucion_id_temporal = null;
                $planificacion->nombre_institucion_temporal = null;
                // Buscar el periodo automáticamente para instituciones normales
                $periodoInstitucion = DB::select("SELECT idperiodoescolar AS periodo FROM periodoescolar WHERE idperiodoescolar = (
                    SELECT pir.periodoescolar_idperiodoescolar as id_periodo
                    FROM institucion i, periodoescolar_has_institucion pir
                    WHERE i.idInstitucion = pir.institucion_idInstitucion
                    AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
                    WHERE phi.institucion_idInstitucion = i.idInstitucion
                    AND i.idInstitucion = ?))", [$request->institucion_id]);
                if (count($periodoInstitucion) > 0) {
                    $planificacion->periodo_id = $periodoInstitucion[0]->periodo;
                } else {
                    $planificacion->periodo_id = $request->periodo_id;
                }
            }
            $planificacion->estado_institucion_temporal = $request->estado_institucion_temporal ?? 0;

            // Manejar ubicación de inicio
            if ($request->latitud && $request->longitud) {
                $planificacion->latitud = $request->latitud;
                $planificacion->longitud = $request->longitud;
                // Si no viene fecha_captura_longitud, usar la fecha/hora actual del servidor
                $planificacion->fecha_captura_longitud = $request->fecha_captura_longitud ?? now()->format('Y-m-d H:i:s');
            }

            // Manejar ubicación final
            if ($request->latitud_fin && $request->longitud_fin) {
                $planificacion->latitud_fin = $request->latitud_fin;
                $planificacion->longitud_fin = $request->longitud_fin;
                // Si no viene fecha_captura_longitud_fin, usar la fecha/hora actual del servidor
                $planificacion->fecha_captura_longitud_fin = $request->fecha_captura_longitud_fin ?? now()->format('Y-m-d H:i:s');
            }

            $planificacion->otraEditorial = $request->nombre_editorial;

            $planificacion->save();

            return response()->json([
                'success' => true,
                'message' => 'Planificación creada exitosamente',
                'data' => $planificacion,
                'id' => $planificacion->id
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la planificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $usuario_id = $request->query('usuario_id');

            $query = Agenda::select('agenda_usuario.*')
                ->selectRaw('
                    CASE
                        WHEN agenda_usuario.estado_institucion_temporal = 0
                            THEN institucion.nombreInstitucion
                        WHEN agenda_usuario.estado_institucion_temporal = 1
                            THEN seguimiento_institucion_temporal.nombre_institucion
                        ELSE NULL
                    END as nombre_institucion
                ')
                ->leftJoin('institucion', 'agenda_usuario.institucion_id', '=', 'institucion.idInstitucion')
                ->leftJoin('seguimiento_institucion_temporal', 'agenda_usuario.institucion_id_temporal', '=', 'seguimiento_institucion_temporal.institucion_temporal_id');
            if ($usuario_id) {
                $query->where('agenda_usuario.id_usuario', $usuario_id);
            }

            $planificaciones = $query->where('agenda_usuario.estado', '!=', 2)
            //order by por startData y id
            ->orderBy('agenda_usuario.startDate', 'desc')
            ->orderBy('agenda_usuario.id', 'desc')
                ->get();

            // Formatear las fechas para evitar problemas de timezone
            $planificaciones = $planificaciones->map(function ($item) {
                if ($item->startDate) {
                    $item->startDate = date('Y-m-d', strtotime($item->startDate));
                }
                if ($item->endDate) {
                    $item->endDate = date('Y-m-d', strtotime($item->endDate));
                }
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $planificaciones
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'startDate' => 'required|date',
            'hora_inicio' => 'required',
            'opciones' => 'required|string'
        ], [
            'title.required' => 'El título es obligatorio',
            'startDate.required' => 'La fecha de inicio es obligatoria',
            'hora_inicio.required' => 'La hora de inicio es obligatoria',
            'opciones.required' => 'Debe seleccionar al menos una opción'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $opciones = json_decode($request->opciones, true);

        $tieneOpcion = false;
        if (is_array($opciones)) {
            foreach ($opciones as $key => $value) {
                if ($value === true) {
                    $tieneOpcion = true;
                    break;
                }
            }
        }

        if (!$tieneOpcion) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar al menos una opción'
            ], 422);
        }

        try {
            $planificacion = Agenda::findOrFail($id);
            //estado 2 es anulado no se puede actualizar
            if ($planificacion->estado == 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede actualizar una planificación anulada'
                ], 400);
            }
            // si es estado 1 es finalizada no se puede actualizar
            if ($planificacion->estado == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede actualizar una planificación finalizada'
                ], 400);
            }
            // si es estado 3 fue marcada como planificacion no realizada no se puede actualizar
            if ($planificacion->estado == 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede actualizar una planificación marcada como no realizada'
                ], 400);
            }

            // Validar que no exista una planificación duplicada (excluyendo la actual)
            $query = Agenda::where('id', '!=', $id)
                ->where('title', $request->title)
                ->where('startDate', $request->startDate)
                ->whereIn('estado', [0, 1]); // Solo validar estados generada (0) o finalizada (1)

            // Validar según tipo de institución
            if ($request->estado_institucion_temporal == 1) {
                // Si es institución temporal, validar por institucion_id_temporal
                $query->where('institucion_id_temporal', $request->institucion_id_temporal);
            } else {
                // Si es institución normal, validar por institucion_id
                $query->where('institucion_id', $request->institucion_id);
            }

            $existeDuplicado = $query->exists();

            if ($existeDuplicado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una planificación con el mismo título y fecha para esta institución'
                ], 422);
            }

            $planificacion->title = $request->title;
            $planificacion->startDate = $request->startDate;
            $planificacion->hora_inicio = $request->hora_inicio;
            $planificacion->hora_fin = $request->hora_fin;
            $planificacion->opciones = $request->opciones;
            $planificacion->url = $request->url;
            $planificacion->usuario_editor = $request->usuario_editor;
            $planificacion->endDate = $request->endDate;
            // $planificacion->fecha_que_visita = $request->fecha_que_visita;
            $planificacion->label = $request->label ?? $planificacion->label;
            $planificacion->classes = $request->classes ?? $planificacion->classes;
            // Manejar institución normal vs temporal (misma lógica que save_agenda_docente)
            if ($request->estado_institucion_temporal == 1) {
                $planificacion->periodo_id = $request->periodo_id;
                $planificacion->institucion_id_temporal = $request->institucion_id_temporal;
                $planificacion->nombre_institucion_temporal = $request->nombre_institucion_temporal;
                $planificacion->institucion_id = null;
            } else {
                $planificacion->institucion_id = $request->institucion_id;
                $planificacion->institucion_id_temporal = null;
                $planificacion->nombre_institucion_temporal = null;
                // Buscar el periodo automáticamente para instituciones normales
                if ($request->institucion_id) {
                    $periodoInstitucion = DB::select("SELECT idperiodoescolar AS periodo FROM periodoescolar WHERE idperiodoescolar = (
                        SELECT pir.periodoescolar_idperiodoescolar as id_periodo
                        FROM institucion i, periodoescolar_has_institucion pir
                        WHERE i.idInstitucion = pir.institucion_idInstitucion
                        AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
                        WHERE phi.institucion_idInstitucion = i.idInstitucion
                        AND i.idInstitucion = ?))", [$request->institucion_id]);
                    if (count($periodoInstitucion) > 0) {
                        $planificacion->periodo_id = $periodoInstitucion[0]->periodo;
                    } else {
                        $planificacion->periodo_id = $request->periodo_id;
                    }
                } else {
                    $planificacion->periodo_id = $request->periodo_id;
                }
            }
            $planificacion->estado_institucion_temporal = $request->estado_institucion_temporal ?? $planificacion->estado_institucion_temporal;

            // Manejar ubicación de inicio
            if ($request->latitud && $request->longitud) {
                $planificacion->latitud = $request->latitud;
                $planificacion->longitud = $request->longitud;
                // Si no viene fecha_captura_longitud o es null, usar la fecha/hora actual del servidor
                if (!$request->fecha_captura_longitud || $request->fecha_captura_longitud === 'null') {
                    $planificacion->fecha_captura_longitud = now()->format('Y-m-d H:i:s');
                } else {
                    $planificacion->fecha_captura_longitud = $request->fecha_captura_longitud;
                }
            }

            // Manejar ubicación final
            if ($request->latitud_fin && $request->longitud_fin) {
                $planificacion->latitud_fin = $request->latitud_fin;
                $planificacion->longitud_fin = $request->longitud_fin;
                // Si no viene fecha_captura_longitud_fin o es null, usar la fecha/hora actual del servidor
                if (!$request->fecha_captura_longitud_fin || $request->fecha_captura_longitud_fin === 'null') {
                    $planificacion->fecha_captura_longitud_fin = now()->format('Y-m-d H:i:s');
                } else {
                    $planificacion->fecha_captura_longitud_fin = $request->fecha_captura_longitud_fin;
                }
            }

            $planificacion->otraEditorial = $request->nombre_editorial;

            if ($request->has('estado')) {
                $planificacion->estado = $request->estado;
            }

            $planificacion->save();

            return response()->json([
                'success' => true,
                'message' => 'Planificación actualizada exitosamente',
                'data' => $planificacion
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la planificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $planificacion = Agenda::findOrFail($id);
            $planificacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Planificación eliminada exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la planificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $planificacion = Agenda::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $planificacion
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Planificación no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function cambiarEstado(Request $request, $id)
    {
        try {
            $planificacion = Agenda::findOrFail($id);
            $planificacion->estado = $request->estado;
            $planificacion->save();

            $mensajes = [
                0 => 'Planificación marcada como generada',
                1 => 'Planificación registrada exitosamente',
                2 => 'Planificación anulada'
            ];

            return response()->json([
                'success' => true,
                'message' => $mensajes[$request->estado] ?? 'Estado actualizado',
                'data' => $planificacion
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function calendario(Request $request)
    {
        try {
            $usuario_id = $request->query('usuario_id');
            $year = $request->query('year');

            // Construir el rango de fechas para el año completo
            $fechaInicio = sprintf('%04d-01-01', $year);
            $fechaFin = sprintf('%04d-12-31', $year);

            // Consultar planificaciones del usuario en el año
            $query = Agenda::selectRaw('DATE(startDate) as fecha, COUNT(*) as cantidad')
                ->where('estado', '!=', 2) // Excluir anuladas
                ->whereBetween('startDate', [$fechaInicio, $fechaFin])
                ->groupBy('fecha');

            if ($usuario_id) {
                $query->where('id_usuario', $usuario_id);
            }

            $resultados = $query->get();

            // Formatear como objeto { "2026-03-04": 2, "2026-02-27": 1 }
            $calendarioDatos = [];
            foreach ($resultados as $item) {
                $calendarioDatos[$item->fecha] = (int)$item->cantidad;
            }

            return response()->json([
                'success' => true,
                'data' => $calendarioDatos
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar calendario',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
