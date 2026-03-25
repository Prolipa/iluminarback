<?php

namespace App\Http\Controllers\Perseo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use Exception;

class VentaListaPerseoController extends Controller
{
    /**
     * Obtener pedidos de Listas sin agrupar (disponibles para agrupar)
     * GET /api/perseo/ventas-lista/pedidos-sin-agrupar
     */
    public function getPedidosSinAgrupar(Request $request)
    {
        try {
            $idPeriodo = $request->input('id_periodo');
            
            if (!$idPeriodo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El periodo es requerido'
                ], 400);
            }

            $pedidos = DB::SELECT("
                SELECT 
                    pv.id_pedido,
                    pv.id_periodo,
                    pv.tipo_venta,
                    pv.contrato_generado,
                    pv.descuento,
                    pv.total_venta,
                    pv.estado_institucion,
                    i.nombreInstitucion,
                    i.telefonoInstitucion,
                    i.direccionInstitucion,
                    c.nombre AS ciudad,
                    u.nombres AS asesor_nombres,
                    u.apellidos AS asesor_apellidos,
                    pe.periodoescolar AS periodo,
                    pe.codigo_contrato
                FROM pedidos pv
                INNER JOIN institucion i ON pv.id_institucion = i.idInstitucion
                LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
                INNER JOIN usuario u ON pv.id_asesor = u.idusuario
                INNER JOIN periodoescolar pe ON pv.id_periodo = pe.idperiodoescolar
                WHERE pv.id_periodo = ?
                    AND pv.tipo_venta = 1
                    AND pv.estado = 1
                    AND (pv.ca_codigo_agrupado IS NULL OR pv.ca_codigo_agrupado = '')
                ORDER BY pv.id_pedido DESC
            ", [$idPeriodo]);

            return response()->json([
                'status' => 'success',
                'data' => $pedidos
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener pedidos sin agrupar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los contratos agrupados (para V-Select)
     * GET /api/perseo/ventas-lista/contratos-agrupados
     */
    public function getContratosAgrupados(Request $request)
    {
        try {
            $idPeriodo = $request->input('id_periodo');
            $busqueda = $request->input('busqueda', '');
            
            if (!$idPeriodo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El periodo es requerido'
                ], 400);
            }

            $query = "
                SELECT 
                    ca.ca_id as id,
                    ca.ca_codigo_agrupado,
                    ca.ca_descripcion,
                    ca.created_at as ca_fecha_creacion,
                    ca.ca_estado,
                    ca.id_periodo,
                    COUNT(DISTINCT pv.id_pedido) AS total_contratos,
                    GROUP_CONCAT(DISTINCT pv.contrato_generado ORDER BY pv.contrato_generado SEPARATOR ', ') AS contratos,
                    GROUP_CONCAT(DISTINCT i.nombreInstitucion ORDER BY i.nombreInstitucion SEPARATOR ', ') AS instituciones,
                    GROUP_CONCAT(DISTINCT CONCAT(u.nombres, ' ', u.apellidos) SEPARATOR ', ') AS asesores
                FROM f_contratos_agrupados ca
                LEFT JOIN pedidos pv ON ca.ca_codigo_agrupado = pv.ca_codigo_agrupado AND pv.estado = 1
                LEFT JOIN institucion i ON pv.id_institucion = i.idInstitucion
                LEFT JOIN usuario u ON pv.id_asesor = u.idusuario
                WHERE ca.id_periodo = ?
                    AND ca.ca_estado = 1
            ";

            $params = [$idPeriodo];

            if (!empty($busqueda)) {
                $query .= " AND (
                    ca.ca_codigo_agrupado LIKE ? OR
                    ca.ca_descripcion LIKE ? OR
                    pv.contrato_generado LIKE ? OR
                    i.nombreInstitucion LIKE ? OR
                    CONCAT(u.nombres, ' ', u.apellidos) LIKE ?
                )";
                $busquedaParam = '%' . $busqueda . '%';
                $params[] = $busquedaParam;
                $params[] = $busquedaParam;
                $params[] = $busquedaParam;
                $params[] = $busquedaParam;
                $params[] = $busquedaParam;
            }

            $query .= " GROUP BY ca.ca_id, ca.ca_codigo_agrupado, ca.ca_descripcion, ca.created_at, ca.ca_estado, ca.id_periodo 
                        ORDER BY ca.created_at DESC";

            $agrupados = DB::SELECT($query, $params);

            return response()->json([
                'status' => 'success',
                'data' => $agrupados
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener contratos agrupados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener TODOS los pedidos de Lista (agrupados y sin agrupar)
     * GET /api/perseo/ventas-lista/pedidos-lista-completos
     */
    public function getPedidosListaCompletos(Request $request)
    {
        try {
            $idPeriodo = $request->input('id_periodo');

            if (!$idPeriodo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El parámetro id_periodo es requerido'
                ], 400);
            }

            // Query para obtener TODOS los pedidos de Lista con información de agrupación
            $pedidos = DB::SELECT("
                SELECT 
                    pv.id_pedido,
                    pv.contrato_generado,
                    pv.ca_codigo_agrupado,
                    pv.tipo_venta,
                    pv.estado as estado_pedido,
                    pv.total_venta,
                    pv.id_asesor,
                    i.nombreInstitucion,
                    c.nombre as nombre_ciudad,
                    CONCAT(u.nombres, ' ', u.apellidos) as asesor,
                    ca.ca_codigo_agrupado as codigo_agrupacion,
                    ca.ca_descripcion as descripcion_agrupacion,
                    ca.ca_estado as estado_agrupacion
                FROM pedidos pv
                LEFT JOIN institucion i ON pv.id_institucion = i.idInstitucion
                LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
                LEFT JOIN usuario u ON pv.id_asesor = u.idusuario
                LEFT JOIN f_contratos_agrupados ca ON pv.ca_codigo_agrupado = ca.ca_codigo_agrupado AND ca.ca_estado = 1
                WHERE pv.tipo_venta != 1
                    AND pv.id_periodo = ?
                    AND pv.estado = 1
                    AND pv.tipo = 0
                    AND pv.facturacion_vee = 1
                    AND pv.contrato_generado IS NOT NULL
                ORDER BY 
                    CASE WHEN pv.ca_codigo_agrupado IS NULL THEN 0 ELSE 1 END,
                    pv.id_pedido DESC
            ", [$idPeriodo]);

            return response()->json([
                'status' => 'success',
                'data' => $pedidos
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener pedidos de lista: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva agrupación de contratos
     * POST /api/perseo/ventas-lista/crear-agrupacion
     */
    public function crearAgrupacion(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'id_periodo' => 'required|integer',
                'descripcion' => 'required|string|max:255',
                'pedidos' => 'required|array|min:1',
                'pedidos.*' => 'integer'
            ]);

            $idPeriodo = $request->input('id_periodo');
            $descripcion = $request->input('descripcion');
            $pedidos = $request->input('pedidos');
            $idUsuario = $request->input('id_usuario', 1);

            // 1. Verificar que la descripción no esté duplicada en el mismo periodo
            $descripcionExiste = DB::table('f_contratos_agrupados')
                ->where('id_periodo', $idPeriodo)
                ->where('ca_descripcion', $descripcion)
                ->where('ca_estado', 1)
                ->exists();

            if ($descripcionExiste) {
                throw new Exception('Ya existe una agrupación con esta descripción en el período actual');
            }

            // 2. Verificar que todos los pedidos existen y están libres
            $pedidosValidos = DB::SELECT("
                SELECT id_pedido, ca_codigo_agrupado
                FROM pedidos
                WHERE id_pedido IN (" . implode(',', $pedidos) . ")
                    AND id_periodo = ?
                    AND tipo_venta = 2
                    AND estado = 1
            ", [$idPeriodo]);

            if (count($pedidosValidos) !== count($pedidos)) {
                throw new Exception('Algunos pedidos no son válidos o no existen');
            }

            foreach ($pedidosValidos as $pedido) {
                if (!empty($pedido->ca_codigo_agrupado)) {
                    throw new Exception('El pedido ' . $pedido->id_pedido . ' ya está agrupado');
                }
            }

            // 2. Obtener código de contrato del periodo
            $periodo = DB::table('periodoescolar')
                ->select('codigo_contrato')
                ->where('idperiodoescolar', $idPeriodo)
                ->first();

            if (!$periodo || empty($periodo->codigo_contrato)) {
                throw new Exception('No se encontró el código de contrato para el período');
            }

            // 3. Generar código TVL-{codigo_contrato}-XXXXX
            $tipoDoc = DB::table('f_tipo_documento')
                ->where('tdo_id', 21)
                ->lockForUpdate()
                ->first();

            if (!$tipoDoc) {
                throw new Exception('No se encontró el tipo de documento para Totalizado');
            }

            $secuenciaActual = intval($tipoDoc->tdo_secuencial_calmed2026);
            $nuevoSecuencial = $secuenciaActual + 1;

            $codigoAgrupado = 'TVL-' . $periodo->codigo_contrato . '-' . str_pad($nuevoSecuencial, 5, '0', STR_PAD_LEFT);

            // 4. Actualizar secuencial
            DB::table('f_tipo_documento')
                ->where('tdo_id', 21)
                ->update(['tdo_secuencial_calmed2026' => $nuevoSecuencial]);

            // 5. Insertar en f_contratos_agrupados
            $idAgrupado = DB::table('f_contratos_agrupados')->insertGetId([
                'ca_codigo_agrupado' => $codigoAgrupado,
                'ca_descripcion' => $descripcion,
                'ca_tipo_pedido' => 2,
                'ca_cantidad' => count($pedidos),
                'ca_estado' => 1,
                'id_periodo' => $idPeriodo,
                'user_created' => $idUsuario,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 6. Actualizar pedidos con el código agrupado
            DB::table('pedidos')
                ->whereIn('id_pedido', $pedidos)
                ->update([
                    'ca_codigo_agrupado' => $codigoAgrupado,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Agrupación creada exitosamente',
                'data' => [
                    'id' => $idAgrupado,
                    'codigo' => $codigoAgrupado,
                    'descripcion' => $descripcion,
                    'total_contratos' => count($pedidos)
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear agrupación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Editar una agrupación existente
     * PUT /api/perseo/ventas-lista/editar-agrupacion/{id}
     */
    public function editarAgrupacion(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'descripcion' => 'required|string|max:255',
                'pedidos' => 'required|array|min:1',
                'pedidos.*' => 'integer'
            ]);

            $descripcion = $request->input('descripcion');
            $nuevosPedidos = $request->input('pedidos');

            // 1. Verificar que la agrupación existe
            $agrupacion = DB::table('f_contratos_agrupados')
                ->where('ca_id', $id)
                ->where('ca_estado', 1)
                ->first();

            if (!$agrupacion) {
                throw new Exception('La agrupación no existe o está anulada');
            }

            // 2. Verificar que no existan ventas registradas con este totalizado
            $ventasExistentes = DB::table('f_venta')
                ->where('ven_totalizado', $agrupacion->ca_codigo_agrupado)
                ->where('est_ven_codigo', '!=', 3)
                ->count();

            if ($ventasExistentes > 0) {
                throw new Exception('No se puede editar la agrupación porque ya tiene ventas registradas');
            }

            // 3. Obtener pedidos actuales
            $pedidosActuales = DB::table('pedidos')
                ->where('ca_codigo_agrupado', $agrupacion->ca_codigo_agrupado)
                ->pluck('id_pedido')
                ->toArray();

            // 4. Determinar pedidos a liberar y pedidos a agregar
            $pedidosLiberar = array_diff($pedidosActuales, $nuevosPedidos);
            $pedidosAgregar = array_diff($nuevosPedidos, $pedidosActuales);

            // 5. Liberar pedidos que ya no están en el grupo
            if (!empty($pedidosLiberar)) {
                DB::table('pedidos')
                    ->whereIn('id_pedido', $pedidosLiberar)
                    ->update([
                        'ca_codigo_agrupado' => null,
                        'updated_at' => now()
                    ]);
            }

            // 6. Agregar nuevos pedidos al grupo
            if (!empty($pedidosAgregar)) {
                // Verificar que los nuevos pedidos estén libres
                $pedidosNuevosValidos = DB::SELECT("
                    SELECT id_pedido, ca_codigo_agrupado
                    FROM pedidos
                    WHERE id_pedido IN (" . implode(',', $pedidosAgregar) . ")
                        AND tipo_venta = 1
                        AND estado = 1
                ");

                foreach ($pedidosNuevosValidos as $pedido) {
                    if (!empty($pedido->ca_codigo_agrupado)) {
                        throw new Exception('El pedido ' . $pedido->id_pedido . ' ya está agrupado');
                    }
                }

                DB::table('pedidos')
                    ->whereIn('id_pedido', $pedidosAgregar)
                    ->update([
                        'ca_codigo_agrupado' => $agrupacion->ca_codigo_agrupado,
                        'updated_at' => now()
                    ]);
            }

            // 7. Actualizar descripción de la agrupación
            DB::table('f_contratos_agrupados')
                ->where('ca_id', $id)
                ->update([
                    'ca_descripcion' => $descripcion,
                    'ca_tipo_pedido' => 2,
                    'ca_cantidad' => count($nuevosPedidos),
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Agrupación actualizada exitosamente',
                'data' => [
                    'id' => $id,
                    'codigo' => $agrupacion->ca_codigo_agrupado,
                    'descripcion' => $descripcion,
                    'total_contratos' => count($nuevosPedidos)
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al editar agrupación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anular una agrupación
     * POST /api/perseo/ventas-lista/anular-agrupacion/{id}
     */
    public function anularAgrupacion(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $motivoAnulacion = $request->input('motivo', '');

            // 1. Verificar que la agrupación existe
            $agrupacion = DB::table('f_contratos_agrupados')
                ->where('ca_id', $id)
                ->first();

            if (!$agrupacion) {
                throw new Exception('La agrupación no existe');
            }

            if ($agrupacion->ca_estado == 0) {
                throw new Exception('La agrupación ya está anulada');
            }

            // 2. Anular la agrupación
            DB::table('f_contratos_agrupados')
                ->where('ca_id', $id)
                ->update([
                    'ca_estado' => 0,
                    'ca_motivo_anulacion' => $motivoAnulacion,
                    'ca_fecha_anulacion' => now(),
                    'updated_at' => now()
                ]);

            // 3. Liberar los pedidos asociados
            DB::table('pedidos')
                ->where('ca_codigo_agrupado', $agrupacion->ca_codigo_agrupado)
                ->update([
                    'ca_codigo_agrupado' => null,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Agrupación anulada exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al anular agrupación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si una agrupación tiene ventas registradas
     * GET /api/perseo/ventas-lista/verificar-ventas/{codigo}
     */
    public function verificarVentasAgrupacion($codigo)
    {
        try {
            $ventasActivas = DB::table('f_venta')
                ->where('ven_totalizado', $codigo)
                ->where('est_ven_codigo', '!=', 3)
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'tiene_ventas' => $ventasActivas > 0,
                    'total_ventas' => $ventasActivas
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al verificar ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consultar stock por producto desde sistema local
     * GET /api/perseo/ventas-lista/stock-producto
     */
    public function consultarStockProductoSistema(Request $request)
    {
        try {
            $proCodigo = $request->input('pro_codigo');
            $empresaId = intval($request->input('empresa_id'));
            $tipoDocumento = intval($request->input('tipo_documento'));

            if (!$proCodigo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El parámetro pro_codigo es requerido'
                ], 400);
            }

            $producto = DB::selectOne("SELECT 
                pro_codigo,
                pro_nombre,
                pro_reservar,
                pro_stock,
                pro_deposito,
                pro_stockCalmed,
                pro_depositoCalmed
                FROM `1_4_cal_producto`
                WHERE pro_codigo = ?
                LIMIT 1", [$proCodigo]);

            if (!$producto) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró el producto solicitado'
                ], 404);
            }

            $stockMostrado = intval($producto->pro_stockCalmed);
            $fuenteStock = 'pro_stockCalmed';

            return response()->json([
                'status' => 'success',
                'data' => [
                    'pro_codigo' => $producto->pro_codigo,
                    'pro_nombre' => $producto->pro_nombre,
                    'pro_reservar' => intval($producto->pro_reservar),
                    'pro_stock' => intval($producto->pro_stock),
                    'pro_deposito' => intval($producto->pro_deposito),
                    'pro_stockCalmed' => intval($producto->pro_stockCalmed),
                    'pro_depositoCalmed' => intval($producto->pro_depositoCalmed),
                    'stock_mostrado' => $stockMostrado,
                    'stock_unico' => $stockMostrado,
                    'existenciastotales' => $stockMostrado,
                    'fuente_stock' => $fuenteStock,
                    'empresa_id' => $empresaId,
                    'tipo_documento' => $tipoDocumento
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar stock del producto: ' . $e->getMessage()
            ], 500);
        }
    }
}
