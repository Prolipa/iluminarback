<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EncuestaProlipa;
use App\Models\EncuestaPreguntaProlipa;
use App\Models\EncuestaOpcionProlipa;
use App\Models\EncuestaRespuestaProlipa;
use App\Models\EncuestaRespuestaDetalleProlipa;
use App\Models\EncuestaAsignacion;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class EncuestaProlipaController extends Controller
{
    // Generar código secuencial ENC_AAAA-MM-DD_NNNN
    private function generarCodigo()
    {
        $total = EncuestaProlipa::count() + 1;
        return 'ENC_' . date('Y-m-d') . '_' . str_pad($total, 4, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $query = EncuestaProlipa::with([
            'creador:idusuario,nombres,apellidos'
        ])->withCount(['preguntas', 'respuestas']);

        if (request()->filled('created_by')) {
            $query->where('created_by', request('created_by'));
        }

        // Agregar conteo de descargas de certificado
        $encuestas = $query->orderByDesc('id')->get();

        $encuestas->each(function ($e) {
            $e->descargas_count = EncuestaRespuestaProlipa::where('encuesta_id', $e->id)
                ->where('descargo_certificado', 1)
                ->count();
        });

        return $encuestas;
    }

    // Crear encuesta
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|max:255',
        ]);

        // Validar duplicado: mismo título por creador
        $existe = EncuestaProlipa::where('titulo', $request->titulo)
            ->where('created_by', $request->created_by)
            ->exists();

        if ($existe) {
            return response()->json([
                'status'  => 0,
                'message' => 'Ya tienes una encuesta con ese título'
            ]);
        }

        $encuesta = EncuestaProlipa::create([
            'codigo'      => $this->generarCodigo(),
            'titulo'      => $request->titulo,
            'descripcion' => $request->descripcion,
            'created_by'  => $request->created_by,
            'estado'      => 'borrador'
        ]);

        return response()->json($encuesta);
    }

    // Ver encuesta
    public function show($id)
    {
        return EncuestaProlipa::with('preguntas.opciones')->findOrFail($id);
    }

    // Actualizar encuesta
    public function update(Request $request, $id)
    {
        $encuesta = EncuestaProlipa::withCount('preguntas')->findOrFail($id);

        if ($request->estado === 'habilitada' && $encuesta->preguntas_count === 0) {
            return response()->json([
                'status'  => 0,
                'message' => 'No se puede habilitar una encuesta sin preguntas'
            ]);
        }

        $encuesta->update($request->only([
            'titulo', 'descripcion', 'estado'
        ]));
        return response()->json(['status' => 1]);
    }

    // Guardar preguntas + opciones
    public function guardarPregunta(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            foreach ($request->preguntas as $p) {

                $pregunta = isset($p['id'])
                    ? EncuestaPreguntaProlipa::findOrFail($p['id'])
                    : new EncuestaPreguntaProlipa();

                $pregunta->texto      = $p['texto'];
                $pregunta->tipo       = $p['tipo']; // escala | multiple | casillas | abierta
                $pregunta->encuesta_id = $id;
                $pregunta->save();

                // multiple y casillas tienen opciones
                if (in_array($pregunta->tipo, ['multiple', 'casillas']) && isset($p['opciones'])) {
                    // Eliminar opciones que ya no están
                    $idsNuevos = collect($p['opciones'])->pluck('id')->filter()->values()->toArray();
                    EncuestaOpcionProlipa::where('pregunta_id', $pregunta->id)
                        ->whereNotIn('id', $idsNuevos)
                        ->delete();

                    foreach ($p['opciones'] as $o) {
                        $opcion = isset($o['id']) && $o['id']
                            ? EncuestaOpcionProlipa::findOrFail($o['id'])
                            : new EncuestaOpcionProlipa();

                        $opcion->texto       = $o['texto'];
                        $opcion->pregunta_id = $pregunta->id;
                        $opcion->save();
                    }
                } elseif (in_array($pregunta->tipo, ['escala', 'abierta'])) {
                    // Limpiar opciones si cambiaron de tipo
                    EncuestaOpcionProlipa::where('pregunta_id', $pregunta->id)->delete();
                }
            }

            DB::commit();
            return response()->json(['status' => 1]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 0, 'error' => $e->getMessage()]);
        }
    }

    // Responder encuesta
    public function responder(Request $request)
    {
        $request->validate([
            'encuesta_id' => 'required|exists:encuesta_prolipa_encuestas,id',
            'usuario_id'  => 'required|exists:usuario,idusuario',
            'respuestas'  => 'required|array'
        ]);

        $encuesta_id = $request->encuesta_id;
        $encuesta = EncuestaProlipa::findOrFail($encuesta_id);

        $existe = EncuestaRespuestaProlipa::where('encuesta_id', $encuesta_id)
            ->where('usuario_id', $request->usuario_id)
            ->when($request->asignacion_id, fn($q) => $q->where('asignacion_id', $request->asignacion_id))
            ->exists();

        if ($existe) {
            return response()->json(['status' => 0, 'message' => 'Ya respondió esta encuesta']);
        }

        DB::beginTransaction();
        try {
            // Obtener periodo_id desde el seminario si la asignación es de tipo capacitacion
            $periodo_id = null;
            if ($request->asignacion_id) {
                $asignacion = EncuestaAsignacion::find($request->asignacion_id);
                if ($asignacion && $asignacion->entidad_tipo === 'capacitacion') {
                    $seminario = DB::table('seminarios')
                        ->where('id_seminario', $asignacion->entidad_id)
                        ->value('periodo_id');
                    $periodo_id = $seminario ?: null;
                }
            }

            $respuesta = EncuestaRespuestaProlipa::create([
                'encuesta_id'    => $encuesta_id,
                'usuario_id'     => $request->usuario_id,
                'asignacion_id'  => $request->asignacion_id ?? null,
                'periodo_id'     => $periodo_id,
            ]);

            foreach ($request->respuestas as $r) {
                if (!isset($r['tipo'], $r['pregunta_id'])) continue;

                if (in_array($r['tipo'], ['simple', 'escala'])) {
                    if (!isset($r['valor']) || $r['valor'] < 1 || $r['valor'] > 10) continue;
                    EncuestaRespuestaDetalleProlipa::create([
                        'respuesta_id' => $respuesta->id,
                        'pregunta_id'  => $r['pregunta_id'],
                        'valor_escala' => $r['valor']
                    ]);
                }

                if ($r['tipo'] === 'abierta') {
                    if (!isset($r['texto']) || trim($r['texto']) === '') continue;
                    EncuestaRespuestaDetalleProlipa::create([
                        'respuesta_id'  => $respuesta->id,
                        'pregunta_id'   => $r['pregunta_id'],
                        'texto_abierto' => $r['texto']
                    ]);
                }

                if (in_array($r['tipo'], ['multiple', 'casillas'])) {
                    if (!isset($r['opciones']) || !is_array($r['opciones'])) continue;
                    foreach ($r['opciones'] as $opcion_id) {
                        EncuestaRespuestaDetalleProlipa::create([
                            'respuesta_id' => $respuesta->id,
                            'pregunta_id'  => $r['pregunta_id'],
                            'opcion_id'    => $opcion_id
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json(['status' => 1, 'message' => 'Encuesta respondida correctamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'error' => $e->getMessage()]);
        }
    }

    // Endpoint eliminado — institucion_id ya no existe en encuestas

    public function cambiarEstado(Request $request, $id)
    {
        $request->validate(['estado' => 'required|in:borrador,habilitada,cerrada']);
        $encuesta = EncuestaProlipa::withCount('preguntas')->findOrFail($id);

        if ($request->estado === 'habilitada' && $encuesta->preguntas_count === 0) {
            return response()->json([
                'status'  => 0,
                'message' => 'No se puede habilitar una encuesta sin preguntas'
            ]);
        }

        $encuesta->estado = $request->estado;
        $encuesta->save();
        return response()->json(['status' => 1]);
    }

    public function obtenerPreguntas($id)
    {
        $preguntas = EncuestaPreguntaProlipa::with('opciones')
            ->where('encuesta_id', $id)
            ->orderBy('orden')
            ->get();
        return response()->json($preguntas);
    }

    // Estadísticas
    public function estadisticas($encuesta_id)
    {
        // Si viene asignacion_id, filtramos respuestas solo de esa asignación
        $asignacion_id = request('asignacion_id');

        $encuesta = EncuestaProlipa::with('preguntas.opciones')->findOrFail($encuesta_id);
        $data = [];

        foreach ($encuesta->preguntas as $pregunta) {
            // Base query para detalles de esta pregunta
            $baseQuery = DB::table('encuesta_prolipa_respuestas_detalle as det')
                ->join('encuesta_prolipa_respuestas as r', 'r.id', '=', 'det.respuesta_id')
                ->where('det.pregunta_id', $pregunta->id)
                ->where('r.encuesta_id', $encuesta_id);

            if ($asignacion_id) {
                $baseQuery->where('r.asignacion_id', $asignacion_id);
            }

            $total   = (clone $baseQuery)->count();
            $opciones = [];

            if (in_array($pregunta->tipo, ['simple', 'escala'])) {
                $promedio = (clone $baseQuery)->whereNotNull('det.valor_escala')->avg('det.valor_escala');
                $opciones = ['promedio' => $promedio ? round($promedio, 2) : null];
            }

            if (in_array($pregunta->tipo, ['multiple', 'casillas'])) {
                foreach ($pregunta->opciones as $opcion) {
                    $cantidad   = (clone $baseQuery)->where('det.opcion_id', $opcion->id)->count();
                    $porcentaje = $total > 0 ? round(($cantidad * 100) / $total, 2) : 0;
                    $opciones[] = [
                        'opcion'     => $opcion->texto,
                        'cantidad'   => $cantidad,
                        'porcentaje' => $porcentaje
                    ];
                }
            }

            $data[] = [
                'pregunta_id'      => $pregunta->id,
                'pregunta'         => $pregunta->texto,
                'tipo'             => $pregunta->tipo,
                'total_respuestas' => $total,
                'opciones'         => $opciones
            ];
        }

        return response()->json($data);
    }

    public function destroy($id)
    {
        $encuesta = EncuestaProlipa::findOrFail($id);

        $tieneRespuestas = EncuestaRespuestaProlipa::where('encuesta_id', $id)->exists();

        if ($tieneRespuestas) {
            return response()->json([
                'status'  => 0,
                'message' => 'No se puede eliminar, la encuesta ya tiene respuestas registradas'
            ]);
        }

        DB::beginTransaction();
        try {
            // eliminar opciones y preguntas
            foreach ($encuesta->preguntas as $pregunta) {
                EncuestaOpcionProlipa::where('pregunta_id', $pregunta->id)->delete();
            }
            EncuestaPreguntaProlipa::where('encuesta_id', $id)->delete();
            $encuesta->delete();

            DB::commit();
            return response()->json(['status' => 1, 'message' => 'Encuesta eliminada']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => $e->getMessage()]);
        }
    }

    public function guardarOrden(Request $request, $id)
    {
        foreach ($request->orden as $item) {
            EncuestaPreguntaProlipa::where('id', $item['id'])
                ->where('encuesta_id', $id)
                ->update(['orden' => $item['orden']]);
        }
        return response()->json(['status' => 1]);
    }

    public function eliminarPregunta($id)
    {
        $pregunta = EncuestaPreguntaProlipa::findOrFail($id);
        // eliminar opciones primero
        EncuestaOpcionProlipa::where('pregunta_id', $id)->delete();
        $pregunta->delete();
        return response()->json(['status' => 1]);
    }

    public function encuestasConDetalle(Request $request)
    {
        $query = EncuestaProlipa::with(['preguntas'])->withCount('respuestas');

        // Filtrar por asesor (created_by)
        if ($request->filled('asesor_id')) {
            $query->where('created_by', $request->asesor_id);
        }

        $encuestas = $query->orderByDesc('id')->get();

        return $encuestas->map(function ($e) {
            $preguntas = $e->preguntas->map(function ($p) use ($e) {
                $detalle = [];

                // simple y escala: promedio
                if (in_array($p->tipo, ['simple', 'escala'])) {
                    $promedio = DB::table('encuesta_prolipa_respuestas_detalle as d')
                        ->join('encuesta_prolipa_respuestas as r', 'r.id', '=', 'd.respuesta_id')
                        ->where('r.encuesta_id', $e->id)
                        ->where('d.pregunta_id', $p->id)
                        ->whereNotNull('d.valor_escala')
                        ->avg('d.valor_escala');
                    $detalle = ['promedio' => $promedio ? round($promedio, 2) : null];
                }

                // multiple y casillas: conteo por opción
                if (in_array($p->tipo, ['multiple', 'casillas'])) {
                    $opciones = DB::table('encuesta_prolipa_opciones as o')
                        ->leftJoin('encuesta_prolipa_respuestas_detalle as d', 'd.opcion_id', '=', 'o.id')
                        ->where('o.pregunta_id', $p->id)
                        ->selectRaw('o.texto, COUNT(d.id) as cantidad')
                        ->groupBy('o.id', 'o.texto')
                        ->get();
                    $total = $opciones->sum('cantidad');
                    $detalle = $opciones->map(fn($o) => [
                        'opcion'     => $o->texto,
                        'cantidad'   => $o->cantidad,
                        'porcentaje' => $total > 0 ? round(($o->cantidad / $total) * 100, 1) : 0
                    ])->toArray();
                }

                return [
                    'id'      => $p->id,
                    'texto'   => $p->texto,
                    'tipo'    => $p->tipo,
                    'detalle' => $detalle
                ];
            });

            return [
                'id'               => $e->id,
                'codigo'           => $e->codigo,
                'titulo'           => $e->titulo,
                'estado'           => $e->estado,
                'respuestas_count' => $e->respuestas_count,
                'preguntas'        => $preguntas
            ];
        });
    }

    public function estadisticasAsesores()
    {
        $asesores = DB::select("
            SELECT DISTINCT
                u.idusuario,
                u.cedula,
                CONCAT(u.nombres,' ',u.apellidos) AS asesor,
                COUNT(DISTINCT e.id)              AS total_encuestas,
                COUNT(DISTINCT r.id)              AS total_respuestas,
                AVG(d.valor_escala)               AS promedio_global
            FROM encuesta_prolipa_encuestas e
            LEFT JOIN usuario u      ON u.idusuario = e.created_by
            LEFT JOIN encuesta_prolipa_respuestas r ON r.encuesta_id = e.id
            LEFT JOIN encuesta_prolipa_respuestas_detalle d ON d.respuesta_id = r.id
                AND d.valor_escala IS NOT NULL
            WHERE u.idusuario IS NOT NULL
            GROUP BY u.idusuario, u.cedula, u.nombres, u.apellidos
            ORDER BY u.apellidos, u.nombres
        ");

        return response()->json(array_map(function($a) {
            return [
                'idusuario'        => $a->idusuario,
                'cedula'           => $a->cedula,
                'asesor'           => $a->asesor,
                'total_encuestas'  => (int) $a->total_encuestas,
                'total_respuestas' => (int) $a->total_respuestas,
                'promedio_global'  => $a->promedio_global ? round($a->promedio_global, 2) : null,
            ];
        }, $asesores));
    }

    public function estadisticasInstituciones()
    {
        // Reemplazado: encuestas ya no tienen institucion_id.
        // Ahora agrupa por capacitación (entidad_tipo = 'capacitacion') usando asignaciones.
        $capacitaciones = DB::select("
            SELECT
                a.entidad_id                              AS capacitacion_id,
                s.nombre                                  AS nombre_capacitacion,
                CONCAT(u.nombres,' ',u.apellidos)         AS asesor,
                COUNT(DISTINCT a.id)                      AS total_asignaciones,
                COUNT(DISTINCT r.id)                      AS total_respuestas,
                AVG(d.valor_escala)                       AS promedio_global
            FROM encuesta_prolipa_asignaciones a
            LEFT JOIN seminarios s   ON s.id_seminario = a.entidad_id
            LEFT JOIN usuario u      ON u.idusuario = s.asesor_id
            LEFT JOIN encuesta_prolipa_respuestas r ON r.asignacion_id = a.id
            LEFT JOIN encuesta_prolipa_respuestas_detalle d ON d.respuesta_id = r.id
                AND d.valor_escala IS NOT NULL
            WHERE a.entidad_tipo = 'capacitacion'
            GROUP BY a.entidad_id, s.nombre, u.nombres, u.apellidos
            ORDER BY s.nombre
        ");

        return response()->json(array_map(function($c) {
            return [
                'capacitacion_id'      => $c->capacitacion_id,
                'nombre_capacitacion'  => $c->nombre_capacitacion ?? '—',
                'asesor'               => $c->asesor ?? '—',
                'total_asignaciones'   => (int) $c->total_asignaciones,
                'total_respuestas'     => (int) $c->total_respuestas,
                'promedio_global'      => $c->promedio_global ? round($c->promedio_global, 2) : null,
            ];
        }, $capacitaciones));
    }

    public function resumenAsesor($created_by)
        {
            $encuestas = EncuestaProlipa::withCount(['preguntas', 'respuestas'])
                ->where('created_by', $created_by)
                ->orderByDesc('id')
                ->get();

            $resultado = $encuestas->map(function ($e) {
                // Promedio sobre tipos simple y escala
                $promedio = DB::table('encuesta_prolipa_respuestas_detalle as d')
                    ->join('encuesta_prolipa_preguntas as p', 'p.id', '=', 'd.pregunta_id')
                    ->where('p.encuesta_id', $e->id)
                    ->whereIn('p.tipo', ['simple', 'escala'])
                    ->whereNotNull('d.valor_escala')
                    ->avg('d.valor_escala');

                return [
                    'id'               => $e->id,
                    'codigo'           => $e->codigo,
                    'titulo'           => $e->titulo,
                    'estado'           => $e->estado,
                    'preguntas_count'  => $e->preguntas_count,
                    'respuestas_count' => $e->respuestas_count,
                    'promedio'         => $promedio ? round($promedio, 2) : null,
                ];
            });

            return response()->json($resultado);
        }

    public function respuestasDocente($encuesta_id, $usuario_id)
    {
        $asignacion_id = request('asignacion_id');

        $query = EncuestaRespuestaProlipa::where('encuesta_id', $encuesta_id)
            ->where('usuario_id', $usuario_id);
        if ($asignacion_id) $query->where('asignacion_id', $asignacion_id);
        $respuesta = $query->first();

        if (!$respuesta) return response()->json([]);

        $detalle = DB::select("
            SELECT
                p.texto        AS pregunta,
                p.tipo,
                det.valor_escala,
                det.texto_abierto,
                o.texto        AS opcion
            FROM encuesta_prolipa_respuestas_detalle det
            JOIN encuesta_prolipa_preguntas p ON p.id = det.pregunta_id
            LEFT JOIN encuesta_prolipa_opciones o ON o.id = det.opcion_id
            WHERE det.respuesta_id = ?
            ORDER BY p.orden, p.id
        ", [$respuesta->id]);

        // Agrupar por pregunta
        $agrupado = [];
        foreach ($detalle as $row) {
            $key = $row->pregunta;
            if (!isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'pregunta'    => $row->pregunta,
                    'tipo'        => $row->tipo,
                    'valor_escala'=> $row->valor_escala,
                    'texto_abierto'=> $row->texto_abierto,
                    'opciones'    => []
                ];
            }
            if (in_array($row->tipo, ['multiple', 'casillas']) && $row->opcion) {
                $agrupado[$key]['opciones'][] = $row->opcion;
            }
        }

        return response()->json(array_values($agrupado));
    }

    public function participantes($encuesta_id)
    {
        $asignacion_id = request('asignacion_id');

        $sql = "
            SELECT
                u.idusuario,
                u.cedula,
                u.nombres,
                u.apellidos,
                u.id_group,
                r.id           AS respuesta_id,
                r.fecha_respuesta,
                r.descargo_certificado,
                r.fecha_descarga
            FROM encuesta_prolipa_respuestas r
            JOIN usuario u ON u.idusuario = r.usuario_id
            WHERE r.encuesta_id = ?
        ";
        $params = [$encuesta_id];

        if ($asignacion_id) {
            $sql    .= " AND r.asignacion_id = ?";
            $params[] = $asignacion_id;
        }

        $sql .= " ORDER BY u.apellidos, u.nombres";

        $respuestas = DB::select($sql, $params);

        $resultado = array_map(function ($d) {
            $avg = DB::table('encuesta_prolipa_respuestas_detalle as det')
                ->join('encuesta_prolipa_preguntas as p', 'p.id', '=', 'det.pregunta_id')
                ->where('det.respuesta_id', $d->respuesta_id)
                ->whereIn('p.tipo', ['simple', 'escala'])
                ->whereNotNull('det.valor_escala')
                ->avg('det.valor_escala');

            $grupo = DB::table('sys_group_users')->where('id', $d->id_group)->value('deskripsi');

            return [
                'idusuario'             => $d->idusuario,
                'cedula'                => $d->cedula,
                'nombres'               => $d->nombres,
                'apellidos'             => $d->apellidos,
                'rol'                   => $grupo ?: '—',
                'respondio'             => true,
                'respuesta_id'          => $d->respuesta_id,
                'fecha_respuesta'       => $d->fecha_respuesta,
                'promedio'              => $avg ? round($avg, 2) : null,
                'descargo_certificado'  => (bool) $d->descargo_certificado,
                'fecha_descarga'        => $d->fecha_descarga,
            ];
        }, $respuestas);

        $respondieron = count($resultado);

        return response()->json([
            'total'        => $respondieron,
            'respondieron' => $respondieron,
            'pendientes'   => 0,
            'docentes'     => $resultado
        ]);
    }

    public function misEncuestas($usuario_id)
    {
        $periodo_id = request('periodo_id');

        $sql = "
            SELECT
                r.id                    AS respuesta_id,
                r.encuesta_id,
                r.asignacion_id,
                r.periodo_id,
                r.fecha_respuesta,
                r.descargo_certificado,
                r.fecha_descarga,
                e.titulo                AS encuesta_titulo,
                e.codigo                AS encuesta_codigo,
                -- Nombre y firma del capacitador si es capacitacion
                CASE
                    WHEN a.entidad_tipo = 'capacitacion' THEN s.nombre
                    ELSE NULL
                END                     AS nombre_capacitacion,
                CASE
                    WHEN a.entidad_tipo = 'capacitacion' THEN ucap.firma
                    ELSE NULL
                END                     AS firma_capacitador,
                a.entidad_tipo,
                a.codigo_acceso,
                -- Periodo
                p.idperiodoescolar,
                p.periodoescolar,
                p.descripcion           AS periodo_descripcion
            FROM encuesta_prolipa_respuestas r
            JOIN encuesta_prolipa_encuestas e   ON e.id = r.encuesta_id
            LEFT JOIN encuesta_prolipa_asignaciones a ON a.id = r.asignacion_id
            LEFT JOIN seminarios s              ON s.id_seminario = a.entidad_id AND a.entidad_tipo = 'capacitacion'
            LEFT JOIN seminarios_capacitador sc ON sc.seminario_id = s.id_seminario
            LEFT JOIN usuario ucap              ON ucap.idusuario = sc.idusuario
            LEFT JOIN periodoescolar p          ON p.idperiodoescolar = r.periodo_id
            WHERE r.usuario_id = ?
        ";

        $params = [$usuario_id];

        if ($periodo_id) {
            $sql    .= " AND r.periodo_id = ?";
            $params[] = $periodo_id;
        }

        $sql .= " GROUP BY r.id ORDER BY r.fecha_respuesta DESC";

        $respuestas = DB::select($sql, $params);

        // Periodos distintos que tiene este usuario (para el select del filtro)
        $periodos = DB::select("
            SELECT DISTINCT p.idperiodoescolar, p.periodoescolar, p.descripcion
            FROM encuesta_prolipa_respuestas r
            JOIN periodoescolar p ON p.idperiodoescolar = r.periodo_id
            WHERE r.usuario_id = ?
            ORDER BY p.idperiodoescolar DESC
        ", [$usuario_id]);

        return response()->json([
            'encuestas' => $respuestas,
            'periodos'  => $periodos,
        ]);
    }

    public function registrarDescarga(Request $request)
    {
        $query = EncuestaRespuestaProlipa::where('encuesta_id', $request->encuesta_id)
            ->where('usuario_id', $request->usuario_id);

        if ($request->filled('asignacion_id')) {
            $query->where('asignacion_id', $request->asignacion_id);
        }

        $respuesta = $query->first();

        if ($respuesta && !$respuesta->descargo_certificado) {
            $respuesta->descargo_certificado = 1;
            $respuesta->fecha_descarga       = now();
            $respuesta->save();
        }

        return response()->json(['status' => 1]);
    }

    public function yaRespondio($encuesta_id, $usuario_id)
    {
        $asignacion_id = request('asignacion_id');

        $query = EncuestaRespuestaProlipa::where('encuesta_id', $encuesta_id)
            ->where('usuario_id', $usuario_id);

        // Si viene asignacion_id, verificar solo para esa asignación específica
        if ($asignacion_id) {
            $query->where('asignacion_id', $asignacion_id);
        }

        $existe = $query->exists();
        return response()->json(['ya_respondio' => $existe]);
    }

    public function porCodigo($codigo)
    {
        $encuesta = EncuestaProlipa::with('preguntas.opciones')
            ->where('codigo', $codigo)
            ->firstOrFail();

        return response()->json($encuesta);
    }

    /** Acceso público por código de asignación (QR de capacitación) */
    public function porCodigoAcceso($codigo_acceso)
    {
        $asignacion = EncuestaAsignacion::with('encuesta.preguntas.opciones')
            ->where('codigo_acceso', $codigo_acceso)
            ->firstOrFail();

        $encuesta = $asignacion->encuesta;

        // Obtener nombre de la entidad (capacitación/seminario) y firma del capacitador
        $nombreEntidad = null;
        $firmaCapacitador = null;

        if ($asignacion->entidad_tipo === 'capacitacion') {
            $entidad = DB::table('seminarios')->where('id_seminario', $asignacion->entidad_id)->first();
            $nombreEntidad = $entidad ? $entidad->nombre : null;

            // Obtener firma del primer capacitador de esta capacitación
            $capacitador = DB::table('seminarios_capacitador as sc')
                ->leftJoin('usuario as u', 'u.idusuario', '=', 'sc.idusuario')
                ->select('u.firma')
                ->where('sc.seminario_id', $asignacion->entidad_id)
                ->first();

            $firmaCapacitador = $capacitador ? $capacitador->firma : null;
        }

        return response()->json([
            'asignacion_id'       => $asignacion->id,
            'codigo_acceso'       => $asignacion->codigo_acceso,
            'estado'              => $asignacion->estado,
            'grupos_acceso'       => $asignacion->grupos_acceso,
            'tipo_acceso'         => $asignacion->tipo_acceso ?? 'grupos',
            'usuarios_acceso'     => $asignacion->usuarios_acceso,
            'nombre_entidad'      => $nombreEntidad,
            'firma_capacitador'   => $firmaCapacitador,
            'encuesta'            => $encuesta,
        ]);
    }

    public function totalRespuestas($encuesta_id)
    {
        $asignacion_id = request('asignacion_id');
        $query = EncuestaRespuestaProlipa::where('encuesta_id', $encuesta_id);
        if ($asignacion_id) $query->where('asignacion_id', $asignacion_id);
        $total = $query->count();
        return response()->json(['total_respuestas' => $total]);
    }

    public function respuestasAbiertas($pregunta_id)
    {
        $respuestas = EncuestaRespuestaDetalleProlipa::where('pregunta_id', $pregunta_id)
            ->whereNotNull('texto_abierto')
            ->pluck('texto_abierto');
        return response()->json($respuestas);
    }

    // ─── ASIGNACIONES ────────────────────────────────────────────────────────────

    /** Listar asignaciones de una capacitación/seminario */
    public function asignacionesPorEntidad($entidad_tipo, $entidad_id)
    {
        $asignaciones = EncuestaAsignacion::with('encuesta:id,titulo,codigo,estado')
            ->where('entidad_tipo', $entidad_tipo)
            ->where('entidad_id', $entidad_id)
            ->orderByDesc('id')
            ->get();

        // Agregar conteos por asignación
        $asignaciones->each(function ($a) {
            $a->total_respuestas = EncuestaRespuestaProlipa::where('asignacion_id', $a->id)->count();
            $a->total_descargas  = EncuestaRespuestaProlipa::where('asignacion_id', $a->id)
                ->where('descargo_certificado', 1)->count();
        });

        return response()->json($asignaciones);
    }

    /** Crear asignación y generar código de acceso único */
    public function crearAsignacion(Request $request)
    {
        $request->validate([
            'encuesta_id'   => 'required|exists:encuesta_prolipa_encuestas,id',
            'entidad_tipo'  => 'required|string|max:50',
            'entidad_id'    => 'required|integer',
            'grupos_acceso' => 'nullable|string|max:100',
            'created_by'    => 'nullable|integer',
        ]);

        // Evitar duplicado encuesta+entidad
        $existe = EncuestaAsignacion::where('encuesta_id',  $request->encuesta_id)
            ->where('entidad_tipo', $request->entidad_tipo)
            ->where('entidad_id',   $request->entidad_id)
            ->exists();

        if ($existe) {
            return response()->json(['status' => 0, 'message' => 'Esta encuesta ya está asignada a esta capacitación']);
        }

        // Generar código único de 8 caracteres
        do {
            $codigo = strtoupper(Str::random(8));
        } while (EncuestaAsignacion::where('codigo_acceso', $codigo)->exists());

        $asignacion = EncuestaAsignacion::create([
            'encuesta_id'   => $request->encuesta_id,
            'entidad_tipo'  => $request->entidad_tipo,
            'entidad_id'    => $request->entidad_id,
            'grupos_acceso' => $request->grupos_acceso ?? '6,11,13',
            'estado'        => 'habilitada',
            'codigo_acceso' => $codigo,
            'created_by'    => $request->created_by,
        ]);

        $asignacion->load('encuesta:id,titulo,codigo,estado');

        return response()->json(['status' => 1, 'asignacion' => $asignacion]);
    }

    /** Eliminar asignación — solo si no tiene respuestas */
    public function eliminarAsignacion($id)
    {
        $asignacion = EncuestaAsignacion::findOrFail($id);

        $tieneRespuestas = EncuestaRespuestaProlipa::where('asignacion_id', $id)->exists();
        if ($tieneRespuestas) {
            return response()->json([
                'status'  => 0,
                'message' => 'No se puede eliminar: esta asignación ya tiene respuestas registradas.'
            ], 422);
        }

        $asignacion->delete();
        return response()->json(['status' => 1]);
    }

    /** Cambiar estado de asignación (habilitada/cerrada) */
    public function estadoAsignacion(Request $request, $id)
    {
        $request->validate(['estado' => 'required|in:habilitada,cerrada']);
        $asignacion = EncuestaAsignacion::findOrFail($id);
        $asignacion->estado = $request->estado;
        $asignacion->save();
        return response()->json(['status' => 1]);
    }

    /** Actualizar grupos_acceso de una asignación */
    public function actualizarAsignacion(Request $request, $id)
    {
        $request->validate(['grupos_acceso' => 'required|string']);
        $asignacion = EncuestaAsignacion::findOrFail($id);
        $asignacion->grupos_acceso = $request->grupos_acceso;
        $asignacion->save();
        return response()->json(['status' => 1]);
    }

    // ─── USUARIOS INDIVIDUALES EN ASIGNACIÓN ─────────────────────────────────────

    /** Listar usuarios asignados individualmente */
    public function usuariosAsignacion($id)
    {
        $asignacion = EncuestaAsignacion::findOrFail($id);
        $raw = $asignacion->usuarios_acceso;

        // Limpiar y decodificar
        $ids = [];
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $ids = array_map('intval', $decoded);
            }
        }

        if (empty($ids)) return response()->json([]);

        $usuarios = DB::table('usuario as u')
            ->leftJoin('sys_group_users as g', 'g.id', '=', 'u.id_group')
            ->whereIn('u.idusuario', $ids)
            ->select('u.idusuario', 'u.nombres', 'u.apellidos', 'u.cedula', 'u.email', 'g.deskripsi as perfil')
            ->get();

        return response()->json($usuarios);
    }

    /** Agregar un usuario individual (por idusuario) */
    public function agregarUsuarioAsignacion(Request $request, $id)
    {
        $request->validate(['idusuario' => 'required|integer']);
        $asignacion = EncuestaAsignacion::findOrFail($id);

        $raw = $asignacion->usuarios_acceso;
        $ids = [];
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $ids = array_map('intval', $decoded);
        }

        if (in_array((int)$request->idusuario, $ids)) {
            return response()->json(['status' => 0, 'message' => 'El usuario ya está asignado']);
        }

        $ids[] = (int) $request->idusuario;
        $asignacion->usuarios_acceso = json_encode(array_values($ids));
        $asignacion->save();
        return response()->json(['status' => 1]);
    }

    /** Quitar un usuario individual */
    public function quitarUsuarioAsignacion($id, $uid)
    {
        $asignacion = EncuestaAsignacion::findOrFail($id);
        $ids = json_decode($asignacion->usuarios_acceso ?? '[]', true) ?: [];
        $ids = array_values(array_filter($ids, fn($i) => $i != $uid));
        $asignacion->usuarios_acceso = json_encode($ids);
        $asignacion->save();
        return response()->json(['status' => 1]);
    }

    /** Importar usuarios por cédula desde Excel */
    public function importarUsuariosAsignacion(Request $request, $id)
    {
        $request->validate(['cedulas' => 'required|array']);
        $asignacion = EncuestaAsignacion::findOrFail($id);
        $ids = json_decode($asignacion->usuarios_acceso ?? '[]', true) ?: [];

        $cedulas      = array_map('trim', $request->cedulas);
        $noExisten    = [];
        $yaAsignados  = [];
        $agregados    = 0;

        foreach ($cedulas as $cedula) {
            $usuario = DB::table('usuario')->where('cedula', $cedula)->first();
            if (!$usuario) { $noExisten[] = $cedula; continue; }
            if (in_array($usuario->idusuario, $ids)) { $yaAsignados[] = $cedula; continue; }
            $ids[] = $usuario->idusuario;
            $agregados++;
        }

        $asignacion->usuarios_acceso = json_encode(array_values($ids));
        $asignacion->save();

        return response()->json([
            'status'      => 1,
            'agregados'   => $agregados,
            'no_existen'  => $noExisten,
            'ya_asignados'=> $yaAsignados,
        ]);
    }

    /** Cambiar tipo de acceso (grupos / usuarios / ambos) */
    public function cambiarTipoAcceso(Request $request, $id)
    {
        $request->validate(['tipo_acceso' => 'required|in:grupos,usuarios']);
        $asignacion = EncuestaAsignacion::findOrFail($id);
        $asignacion->tipo_acceso = $request->tipo_acceso;
        $asignacion->save();
        return response()->json(['status' => 1]);
    }

    // Top docentes por promedio — filtra por encuesta o asesor
    public function topDocentes(Request $request)
    {
        $limit       = (int) $request->get('limit', 10);
        $encuesta_id = $request->get('encuesta_id');
        $asesor_id   = $request->get('asesor_id');

        $query = DB::table('encuesta_prolipa_respuestas as r')
            ->join('usuario as u', 'u.idusuario', '=', 'r.usuario_id')
            ->join('encuesta_prolipa_encuestas as e', 'e.id', '=', 'r.encuesta_id')
            ->join('encuesta_prolipa_respuestas_detalle as d', 'd.respuesta_id', '=', 'r.id')
            ->join('encuesta_prolipa_preguntas as p', 'p.id', '=', 'd.pregunta_id')
            ->whereIn('p.tipo', ['simple', 'escala'])
            ->whereNotNull('d.valor_escala')
            ->selectRaw("
                u.idusuario,
                CONCAT(u.nombres,' ',u.apellidos) AS docente,
                u.cedula,
                AVG(d.valor_escala) AS promedio,
                (AVG(d.valor_escala) / 10 * 100) AS porcentaje
            ")
            ->groupBy('u.idusuario', 'u.nombres', 'u.apellidos', 'u.cedula')
            ->orderByDesc('promedio')
            ->limit($limit);

        if ($encuesta_id) {
            $query->where('r.encuesta_id', $encuesta_id);
        }

        if ($request->filled('asignacion_id')) {
            $query->where('r.asignacion_id', $request->asignacion_id);
        }

        if ($asesor_id) {
            $query->where('e.created_by', $asesor_id);
        }

        $rows = $query->get();

        return response()->json($rows->map(fn($r) => [
            'idusuario' => $r->idusuario,
            'nombre'    => $r->docente,
            'cedula'    => $r->cedula,
            'promedio'  => round($r->promedio, 2),
            'porcentaje'=> round($r->porcentaje, 2),
        ]));
    }
}
