<?php

namespace App\Http\Controllers\Perseo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use App\Models\VentasF;
use App\Models\DetalleVentasF;
use App\Models\Ventas;
use App\Models\DetalleVentas;
use App\Repositories\perseo\PerseoConsultasRepository;
use App\Traits\Pedidos\TraitPedidosGeneral;
use Exception;

class VentaPerseoController extends Controller
{
    use TraitPedidosGeneral;
    
    public $perseoConsultaReposiory;
    
    public function __construct(PerseoConsultasRepository $perseoConsultasRepository)
    {
        $this->perseoConsultaReposiory = $perseoConsultasRepository;
    }
    
    /**
     * Generar código de venta (Prefactura) para Perseo
     * Formato: PFAC-P-2026-0001-CR (ejemplo)
     */
    private function generarCodigoVenta($empresa, $tipoDocumento, $contrato, $idusuario)
    {
        try {
            // 1. Obtener información completa del tipo de documento CON BLOQUEO DE FILA
            // Usar query builder con lockForUpdate para participar correctamente en la transacción
            $tipoDoc = DB::table('f_tipo_documento')
                ->where('tdo_id', $tipoDocumento)
                ->lockForUpdate()
                ->first();

            if (!$tipoDoc) {
                throw new Exception('Tipo de documento no encontrado');
            }
            
            $letraTipoDoc = $tipoDoc->tdo_letra;
            
            // 2. Obtener letra de la empresa
            $letraEmpresa = '';
            $secuenciaActual = 0;
            $campoSecuencial = '';
            
            $empresaData = DB::table('empresas')->where('id', $empresa)->first();

            if (!$empresaData) {
                throw new Exception('Empresa no encontrada');
            }

            if ($empresaData->id == 1) {
                $letraEmpresa = $empresaData->letra_empresa;
                $secuenciaActual = $tipoDoc->tdo_secuencial_Prolipa;
                $campoSecuencial = 'tdo_secuencial_Prolipa';
            } else if ($empresaData->id == 3) {
                $letraEmpresa = $empresaData->letra_empresa;
                $secuenciaActual = $tipoDoc->tdo_secuencial_calmed;
                $campoSecuencial = 'tdo_secuencial_calmed';
            } else if ($empresaData->id == 5) {
                $letraEmpresa = $empresaData->letra_empresa;
                $secuenciaActual = $tipoDoc->tdo_secuencial_Prolipa2026;
                $campoSecuencial = 'tdo_secuencial_Prolipa2026';
            } else if ($empresaData->id == 4) {
                $letraEmpresa = $empresaData->letra_empresa;
                $secuenciaActual = $tipoDoc->tdo_secuencial_calmed2026;
                $campoSecuencial = 'tdo_secuencial_calmed2026';
            } else {
                $letraEmpresa = 'SE';
                $secuenciaActual = $tipoDoc->tdo_secuencial_SinEmpresa;
                $campoSecuencial = 'tdo_secuencial_SinEmpresa';
            }
            
            // 3. Obtener código contrato del período escolar
            $pedido = DB::SELECT("SELECT pv.*, pe.codigo_contrato 
                FROM pedidos pv
                LEFT JOIN periodoescolar pe ON pv.id_periodo = pe.idperiodoescolar
                WHERE pv.contrato_generado = ?", [$contrato]);
            
            if (empty($pedido)) {
                throw new Exception('Contrato no encontrado en pedidos');
            }
            
            $codigoContrato = $pedido[0]->codigo_contrato ?? '';
            
            if (empty($codigoContrato)) {
                throw new Exception('No se pudo obtener el código de contrato del período escolar');
            }
            
            // 4. Obtener iniciales del usuario
            $usuario = DB::SELECT("SELECT iniciales FROM usuario WHERE idusuario = ?", [$idusuario]);
            
            if (empty($usuario) || empty($usuario[0]->iniciales)) {
                throw new Exception('No se pudieron obtener las iniciales del usuario');
            }
            
            $iniciales = $usuario[0]->iniciales;
            
            // 5. Incrementar secuencial
            $nuevoSecuencial = $secuenciaActual + 1;
            
            // 6. Actualizar el secuencial usando el campo correcto para esta empresa
            DB::table('f_tipo_documento')
                ->where('tdo_id', $tipoDocumento)
                ->update([$campoSecuencial => $nuevoSecuencial]);
            
            // 7. Generar código final
            $secuencialFormateado = str_pad($nuevoSecuencial, 4, '0', STR_PAD_LEFT);
            $codigoFinal = "{$letraTipoDoc}-{$letraEmpresa}-{$codigoContrato}-{$iniciales}-{$secuencialFormateado}";
            
            return $codigoFinal;
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Crear venta con Perseo (transacción directa sin proforma)
     * POST /api/perseo/ventas/crear
     */
    public function crearVentaPerseo(Request $request)
    {
        try {
            DB::beginTransaction();

            // ==================================
            // 1. VALIDACIONES INICIALES
            // ==================================
            $request->validate([
                'empresa' => 'required|integer',
                'descuento' => 'required|numeric|min:0',
                'observacion' => 'required|string|max:500',
                'concepto_perseo' => 'required|string|max:500',
                'lugar_despacho_id' => 'required|integer',
                'cliente_perseo_id' => 'required|integer',
                'clientesidPerseo' => 'required|integer',
                'ruc_cliente' => 'required|string',
                'contrato' => 'required|string',
                'tipo_venta' => 'required|string|in:L,V',
                'periodo_id' => 'required|integer',
                'idusuario' => 'required|integer',
                'detalles' => 'required|array|min:1',
                'detalles.*.pro_codigo' => 'required|string',
                'detalles.*.cantidad' => 'required|integer|min:1',
                'detalles.*.precio' => 'required|numeric|min:0',
                'detalles.*.descuento' => 'required|numeric|min:0|max:100',
            ]);

            // ==================================
            // 2. GENERAR CÓDIGO DE VENTA
            // ==================================
            // Tipo de documento 1 = Prefactura
            $tipoDocumento = 1;
            
            $codigoVenta = $this->generarCodigoVenta(
                $request->empresa,
                $tipoDocumento,
                $request->contrato,
                $request->idusuario
            );

            if (!$codigoVenta) {
                throw new Exception('No se pudo generar el código de venta');
            }

            // \Log::info('✅ Código de venta generado: ' . $codigoVenta);

            // ==================================
            // 3. CALCULAR TOTALES
            // ==================================
            $subtotal = 0;
            $detalles = $request->detalles;

            foreach ($detalles as $detalle) {
                $subtotal += (float)$detalle['cantidad'] * (float)$detalle['precio'];
            }

            $porcentajeDescuento = (float)$request->descuento;
            $descuentoMonto = $subtotal * $porcentajeDescuento / 100;
            $total = $subtotal - $descuentoMonto;

            // \Log::info('💰 Totales calculados:', [
            //     'subtotal' => $subtotal,
            //     'descuento_porcentaje' => $porcentajeDescuento,
            //     'descuento_monto' => $descuentoMonto,
            //     'total' => $total
            // ]);

            // ==================================
            // 4. OBTENER TIPO DE VENTA (tip_ven_codigo)
            // ==================================
            // L = Venta por Lista (tipo_venta = 2)
            // V = Venta Directa (tipo_venta = 1)
            $tipoVentaCodigo = $request->tipo_venta == 'L' ? 2 : 1;

            // ==================================
            // 5. CREAR REGISTRO EN f_venta
            // ==================================
            DB::table('f_venta')->insert([
                'ven_codigo' => $codigoVenta,
                'contrato' => $request->contrato,
                'tip_ven_codigo' => $tipoVentaCodigo,
                'est_ven_codigo' => 2, // Estado: 2 (Generado/Activo)
                'ven_idproforma' => null, // No hay proforma, es venta directa
                'ven_tipo_inst' => $request->tipo_venta, // L o V
                'ven_comision' => null,
                'ven_com_porcentaje' => null,
                'ven_valor' => $total,
                'ven_subtotal' => $subtotal,
                'ven_pagado' => null,
                'ven_desc_por' => $porcentajeDescuento,
                'ven_descuento' => $descuentoMonto,
                'ven_iva_por' => null,
                'ven_iva' => null,
                'ven_fecha' => now(),
                'ven_transporte' => null,
                'ven_devolucion' => null,
                'ven_remision' => null,
                'ven_fech_remision' => null,
                'institucion_id' => $request->lugar_despacho_id,
                'periodo_id' => $request->periodo_id,
                'updated_at' => now(),
                'user_created' => $request->idusuario,
                'id_empresa' => $request->empresa,
                'id_ins_depacho' => null,
                'id_sucursal' => null,
                'impresion' => 0,
                'ven_observacion' => $request->observacion,
                'ven_concepto_perseo' => $request->concepto_perseo,
                'idtipodoc' => $tipoDocumento, // 1 = Prefactura
                'ven_p_libros_obsequios' => null, // Pendiente de implementar
                'ven_cliente' => $request->cliente_perseo_id,
                'estadoPerseo' => 0, // 0 = No enviado a Perseo aún
                'idPerseo' => 0,
                'proformas_codigo' => null,
                'fecha_envio_perseo' => null,
                'clientesidPerseo' => $request->clientesidPerseo,
                'id_factura' => null,
                'ruc_cliente' => $request->ruc_cliente,
                'anuladoEnPerseo' => 0,
                'doc_intercambio' => null,
                'user_intercambio' => 0,
                'fecha_intercambio' => null,
                'observacion_intercambio' => null,
                'user_anulado' => null,
                'fecha_anulacion' => null,
                'observacionAnulacion' => null,
                'id_pedido' => null,
                'fecha_notaCredito' => null,
                'fecha_sri' => null,
                'documento_sri' => null,
                'observacionRegresarAPendiente' => null,
                'fecha_update_sri' => null,
                'usuario_update_sri' => null,
                'user_despacha' => null,
                'fecha_proceso_despacho' => null,
                'destino' => 0
            ]);

            // \Log::info('✅ Venta creada en f_venta: ' . $codigoVenta);

            // ==================================
            // 6. CREAR DETALLES EN f_detalle_venta
            // ==================================
            foreach ($detalles as $detalle) {
                DB::table('f_detalle_venta')->insert([
                    'ven_codigo' => $codigoVenta,
                    'id_empresa' => $request->empresa,
                    'pro_codigo' => $detalle['pro_codigo'],
                    'det_ven_cantidad' => $detalle['cantidad'],
                    'contrato' => $request->contrato,
                    'det_ven_valor_u' => $detalle['precio'],
                    'det_ven_cantidad_despacho' => 0, // Pendiente
                    'idProforma' => null, // No hay proforma
                    'det_ven_dev' => 0,
                    'det_cant_intercambio' => 0,
                    'doc_intercambio' => null,
                    'detalle_notaCreditInterna' => null,
                    'descuento' => $detalle['descuento']
                ]);
            }

            // \Log::info('✅ ' . count($detalles) . ' detalles creados en f_detalle_venta');

            // ==================================
            // 7. COMMIT Y RESPUESTA
            // ==================================
            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Venta creada exitosamente',
                'data' => [
                    'ven_codigo' => $codigoVenta,
                    'total' => $total,
                    'subtotal' => $subtotal,
                    'descuento' => $descuentoMonto,
                    'cantidad_detalles' => count($detalles)
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            // \Log::error('❌ Error de validación en crearVentaPerseo: ' . json_encode($e->errors()));
            
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();
            // \Log::error('❌ Error al crear venta Perseo: ' . $e->getMessage());
            // \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 500,
                'message' => 'Error al crear la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar venta Perseo existente
     * PUT /api/perseo/ventas/actualizar
     */
    public function actualizarVentaPerseo(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validar que la venta exista
            $venta = DB::table('f_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->empresa)
                ->first();

            if (!$venta) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            // Validar que esté en estado editable (estado 2)
            if ($venta->est_ven_codigo != 2) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Solo se pueden editar ventas en estado activo (estado 2)'
                ], 400);
            }

            // Calcular nuevos totales
            $subtotal = 0;
            $detalles = $request->detalles;

            foreach ($detalles as $detalle) {
                $subtotal += (float)$detalle['cantidad'] * (float)$detalle['precio'];
            }

            $porcentajeDescuento = (float)$request->descuento;
            $descuentoMonto = $subtotal * $porcentajeDescuento / 100;
            $total = $subtotal - $descuentoMonto;

            // Actualizar venta
            DB::table('f_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->empresa)
                ->update([
                    'ven_valor' => $total,
                    'ven_subtotal' => $subtotal,
                    'ven_desc_por' => $porcentajeDescuento,
                    'ven_descuento' => $descuentoMonto,
                    'ven_observacion' => $request->observacion,
                    'ven_concepto_perseo' => $request->concepto_perseo,
                    'updated_at' => now()
                ]);

            // Eliminar detalles antiguos
            DB::table('f_detalle_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->empresa)
                ->delete();

            // Crear nuevos detalles
            foreach ($detalles as $detalle) {
                DB::table('f_detalle_venta')->insert([
                    'ven_codigo' => $request->ven_codigo,
                    'id_empresa' => $request->empresa,
                    'pro_codigo' => $detalle['pro_codigo'],
                    'det_ven_cantidad' => $detalle['cantidad'],
                    'contrato' => $venta->contrato,
                    'det_ven_valor_u' => $detalle['precio'],
                    'det_ven_cantidad_despacho' => 0,
                    'idProforma' => null,
                    'det_ven_dev' => 0,
                    'det_cant_intercambio' => 0,
                    'doc_intercambio' => null,
                    'detalle_notaCreditInterna' => null,
                    'descuento' => $detalle['descuento']
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Venta actualizada exitosamente',
                'data' => [
                    'ven_codigo' => $request->ven_codigo,
                    'total' => $total,
                    'subtotal' => $subtotal,
                    'descuento' => $descuentoMonto
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            // \Log::error('Error al actualizar venta Perseo: ' . $e->getMessage());
            
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de una venta para edición en Perseo
     * GET: get_venta_para_editar_perseo/{ven_codigo}
     */
    public function obtenerVentaParaEditar($ven_codigo)
    {
        try {
            // 1. Obtener datos básicos de la venta con tipo_venta del pedido
            $venta = DB::table('f_venta as fv')
                ->leftJoin('pedidos as p', 'fv.contrato', '=', 'p.contrato_generado')
                ->select('fv.*', 'p.tipo_venta')
                ->where('fv.ven_codigo', $ven_codigo)
                ->first();

            if (!$venta) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            // 1.1 Determinar si es primera venta del contrato
            // Obtenemos la primera venta del contrato ordenada por fecha
            $primeraVenta = DB::table('f_venta')
                ->where('contrato', $venta->contrato)
                ->where('est_ven_codigo', 2) // Solo ventas pendientes
                ->orderBy('ven_fecha', 'ASC')
                ->first();

            // Es primera venta si el código coincide con la primera encontrada
            $esPrimeraVenta = ($primeraVenta && $primeraVenta->ven_codigo === $ven_codigo);

            // 1.2 Determinar si es venta de obsequios o de pedido
            $esVentaObsequios = !empty($venta->ven_p_libros_obsequios);

            // 2. Obtener lugar de despacho (institución)
            $lugarDespacho = DB::table('institucion')
                ->where('idInstitucion', $venta->institucion_id)
                ->first();

            // 2.1 Si es primera venta de pedido, obtener cupo
            $datosInstitucion = [];
            if ($esPrimeraVenta && !$esVentaObsequios && $venta->periodo_id) {
                $cupo = DB::table('f_formulario_proforma')
                    ->where('idInstitucion', $venta->institucion_id)
                    ->where('idperiodoescolar', $venta->periodo_id)
                    ->first();

                if ($cupo) {
                    $datosInstitucion = [$cupo];
                }
            }

            // 3. Obtener información del cliente Perseo
            $cliente = DB::table('usuario')
                ->select('idusuario', 'nombres', 'apellidos', 'cedula', 'email')
                ->where('idusuario', $venta->ven_cliente)
                ->first();

            // Formatear nombre completo del cliente
            $nombreCliente = ($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '');

            // 4. Obtener detalles de la venta (productos vendidos)
            // Incluye cantidades vendidas en otras ventas para control de stock
            $detalles = DB::table('f_detalle_venta as dv')
                ->join('1_4_cal_producto as l', 'dv.pro_codigo', '=', 'l.pro_codigo')
                // Join con pedido para obtener cantidad original pedida
                // Nota: pedidos_val_area_new usa idlibro, no pro_codigo, entonces hacemos join doble
                ->leftJoin('libros_series as lib', 'l.pro_codigo', '=', 'lib.codigo_liquidacion')
                ->leftJoin('pedidos_val_area_new as pvn', function($join) use ($venta) {
                    $join->on('pvn.idlibro', '=', 'lib.idlibro')
                         ->where('pvn.id_pedido', '=', $venta->id_pedido ?? 0)
                         ->where('pvn.pvn_tipo', '=', 0); // Solo pedidos normales, no obsequios
                })
                ->select(
                    'dv.pro_codigo',
                    'l.pro_nombre as nombre_libro',
                    'dv.det_ven_valor_u as precio',
                    'dv.det_ven_cantidad as cantidad',
                    // Cantidad del pedido original
                    DB::raw('COALESCE(pvn.pvn_cantidad, 0) as cantidad_pedido'),
                    // Cantidad en OTRAS ventas pendientes (excluyendo esta) DEL MISMO CONTRATO
                    DB::raw("(
                        SELECT COALESCE(SUM(dv2.det_ven_cantidad), 0)
                        FROM f_detalle_venta dv2
                        INNER JOIN f_venta v2 ON dv2.ven_codigo = v2.ven_codigo
                        WHERE dv2.pro_codigo = dv.pro_codigo
                        AND v2.contrato = '{$venta->contrato}'
                        AND v2.est_ven_codigo = 2
                        AND dv2.ven_codigo != '{$ven_codigo}'
                    ) as cantidad_otras_ventas"),
                    // Cantidad TOTAL en todas las ventas pendientes (incluyendo esta) DEL MISMO CONTRATO
                    DB::raw("(
                        SELECT COALESCE(SUM(dv3.det_ven_cantidad), 0)
                        FROM f_detalle_venta dv3
                        INNER JOIN f_venta v3 ON dv3.ven_codigo = v3.ven_codigo
                        WHERE dv3.pro_codigo = dv.pro_codigo
                        AND v3.contrato = '{$venta->contrato}'
                        AND v3.est_ven_codigo = 2
                    ) as cantidad_total_vendida")
                )
                ->where('dv.ven_codigo', $ven_codigo)
                ->get();

            // 5. Formatear respuesta
            return response()->json([
                'status' => 200,
                'message' => 'Venta cargada exitosamente',
                'data' => [
                    'venta' => [
                        'ven_codigo' => $venta->ven_codigo,
                        'id_empresa' => $venta->id_empresa,
                        'ven_descuento' => floatval($venta->ven_desc_por ?? 0),
                        'ven_observacion' => $venta->ven_observacion ?? '',
                        'concepto_perseo' => $venta->ven_concepto_perseo ?? '',
                        'ven_valor' => floatval($venta->ven_valor),
                        'est_ven_codigo' => $venta->est_ven_codigo,
                        'contrato' => $venta->contrato,
                        'ven_fecha' => $venta->ven_fecha,
                        'usu_codigo' => $venta->usu_codigo ?? $venta->user_created,
                        'pedido_id' => $venta->id_pedido,
                        'id_periodo' => $venta->periodo_id,
                        'id_ins_depacho' => $venta->id_ins_depacho,
                        'ven_cliente' => $venta->ven_cliente,
                        'ruc_cliente' => $venta->ruc_cliente ?? '',
                        'clientesidPerseo' => $venta->clientesidPerseo,
                        'idtipodoc' => $venta->idtipodoc,
                        'ven_p_libros_obsequios' => $venta->ven_p_libros_obsequios,
                        'esPrimeraVenta' => $esPrimeraVenta,
                        'esVentaObsequios' => $esVentaObsequios,
                        'tipo_venta' => $venta->tipo_venta ?? null
                    ],
                    'lugarDespacho' => [
                        'idInstitucion' => $lugarDespacho->idInstitucion ?? 0,
                        'nombreInstitucion' => $lugarDespacho->nombreInstitucion ?? '',
                        'ruc' => $lugarDespacho->ruc ?? '',
                        'ciudad' => $lugarDespacho->ciudad ?? '',
                        'datosInstitucion' => $datosInstitucion
                    ],
                    'cliente' => [
                        'id' => $cliente->idusuario ?? 0,
                        'nombre' => trim($nombreCliente),
                        'cedula' => $cliente->cedula ?? '',
                        'email' => $cliente->email ?? ''
                    ],
                    'detalles' => $detalles
                ]
            ], 200);

        } catch (Exception $e) {
            // \Log::error('Error al cargar venta para editar: ' . $e->getMessage());
            
            return response()->json([
                'status' => 500,
                'message' => 'Error al cargar los datos de la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar venta con sistema de reservas de Perseo
     * POST: Venta_Perseo_Actualizar
     */
    public function actualizarVentaPerseoConReservas(Request $request)
    {
        DB::beginTransaction();

        try {
            // 1. Validar que la venta existe y está en estado editable (2 = Pendiente)
            $venta = DB::table('f_venta')->where('ven_codigo', $request->ven_codigo)->first();

            if (!$venta) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            if ($venta->est_ven_codigo != 2) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Solo se pueden editar ventas en estado Pendiente'
                ], 400);
            }

            // 2. Procesar detalles - ELIMINAR Y RECREAR TODOS
            $detallesNuevos = json_decode($request->data_detalle, true);
            
            if (empty($detallesNuevos)) {
                throw new Exception('No se enviaron detalles de la venta');
            }

            // 2.1. Calcular subtotal desde los detalles recibidos
            $subtotal = 0;
            foreach ($detallesNuevos as $detalle) {
                $cantidad = intval($detalle['ven_det_cantidad']);
                $precioUnitario = floatval($detalle['ven_det_valor_u']);
                $subtotal += $cantidad * $precioUnitario;
            }
            
            // 2.2. Calcular descuento y total
            $porcentajeDescuento = floatval($request->ven_descuento ?? 0);
            $descuentoMonto = $subtotal * $porcentajeDescuento / 100;
            $total = $subtotal - $descuentoMonto;

            // 3. ELIMINAR todos los detalles antiguos
            DB::table('f_detalle_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->delete();
            
            // 4. CREAR todos los detalles nuevos
            foreach ($detallesNuevos as $detalle) {
                $diferencia = intval($detalle['diferencia_cantidad'] ?? 0);
                
                // Insertar detalle
                DB::table('f_detalle_venta')->insert([
                    'ven_codigo' => $request->ven_codigo,
                    'id_empresa' => $venta->id_empresa,
                    'pro_codigo' => $detalle['pro_codigo'],
                    'det_ven_cantidad' => intval($detalle['ven_det_cantidad']),
                    'contrato' => $venta->contrato,
                    'det_ven_valor_u' => floatval($detalle['ven_det_valor_u']),
                    'det_ven_cantidad_despacho' => 0,
                    'idProforma' => null,
                    'det_ven_dev' => 0,
                    'det_cant_intercambio' => 0,
                    'doc_intercambio' => null,
                    'detalle_notaCreditInterna' => null,
                    'descuento' => 0
                ]);
                
                // TODO: Actualizar reservas en Perseo según la diferencia
                if ($diferencia > 0) {
                    // AUMENTÓ: restar del stock disponible (nueva reserva)
                    // \Log::info("📦 Perseo: Reservando {$diferencia} unidades adicionales de {$detalle['pro_codigo']}");
                } elseif ($diferencia < 0) {
                    // DISMINUYÓ: devolver al stock disponible (liberar reserva)
                    $cantidadLiberar = abs($diferencia);
                    // \Log::info("📦 Perseo: Liberando {$cantidadLiberar} unidades de {$detalle['pro_codigo']}");
                }
            }

            // 5. Actualizar datos básicos de la venta CON TOTALES CORRECTOS
            $updateData = [
                'ven_observacion' => $request->ven_observacion ?? '',
                'ven_concepto_perseo' => $request->concepto_perseo ?? '',
                'ven_desc_por' => $porcentajeDescuento,
                'ven_descuento' => $descuentoMonto,
                'ven_subtotal' => $subtotal,
                'ven_valor' => $total,
                'ven_cliente' => $request->ven_cliente,
                'clientesidPerseo' => $request->clientesidPerseo,
                'ruc_cliente' => $request->ruc_cliente ?? '',
                'updated_at' => now()
            ];

            $idInsDepacho = $request->id_ins_depacho;
            if ($idInsDepacho && $idInsDepacho !== 'null') {
                $updateData['institucion_id'] = $idInsDepacho;
                $updateData['id_ins_depacho'] = $idInsDepacho;
            }

            DB::table('f_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->update($updateData);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Venta actualizada exitosamente con reservas de Perseo',
                'data' => [
                    'ven_codigo' => $request->ven_codigo,
                    'subtotal' => $subtotal,
                    'descuento_porcentaje' => $porcentajeDescuento,
                    'descuento_monto' => $descuentoMonto,
                    'total' => $total,
                    'cantidad_detalles' => count($detallesNuevos),
                    'message' => 'Los cambios en las cantidades han sido procesados correctamente'
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            // \Log::error('❌ Error al actualizar venta Perseo con reservas: ' . $e->getMessage());
            
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar la venta: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Enviar venta a Perseo como pedido
     * POST /api/perseo/ventas/enviar-perseo
     */
    public function enviarAPerseo(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $ven_codigo = $request->ven_codigo;
            $empresa = $request->id_empresa;
            
            // Validar parámetros requeridos
            if (!$ven_codigo || !$empresa) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan parámetros requeridos: ven_codigo e id_empresa'
                ], 400);
            }
            
            // Obtener la venta del modelo Ventas (tabla f_venta)
            $venta = Ventas::where('ven_codigo', $ven_codigo)
                          ->where('id_empresa', $empresa)
                          ->first();
            
            // Validar que exista la venta
            if (!$venta) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'La venta no existe'
                ], 404);
            }
            
            // Validar si la venta ya fue enviada a Perseo
            if ($venta->estadoPerseo == 1) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'La venta ya fue enviada a Perseo'
                ], 400);
            }
            
            // Obtener valores de la venta
            $ven_valor = $venta->ven_valor;
            $ven_descuento = $venta->ven_descuento;
            $clientesidPerseo = $venta->clientesidPerseo;
            $discount = $venta->ven_desc_por;
            $concepto = $venta->ven_concepto_perseo ?? 'Venta Perseo';
            $observacion = $venta->ven_observacion ?? 'Pedido';
            
            // Obtener configuración de ambiente desde la tabla empresas
            $empresaData = DB::table('empresas')->where('id', $empresa)->first();
            
            if (!$empresaData) {
                throw new Exception('Empresa no encontrada');
            }
            
            // perseo_enviroment: 0 = local/desarrollo, 1 = producción
            $perseoProduccion = $empresaData->perseo_enviroment ?? 1;
            
            // Determinar qué columna de producto Perseo usar según empresa y ambiente
            if ($empresa == 1) {
                $productoBuscar = $perseoProduccion == 0 ? 'id_perseo_prolipa' : 'id_perseo_prolipa_produccion';
            } else if ($empresa == 3) {
                $productoBuscar = $perseoProduccion == 0 ? 'id_perseo_calmed' : 'id_perseo_calmed_produccion';
            } else if ($empresa == 5) {
                $productoBuscar = $perseoProduccion == 0 ? 'id_perseo_prolipa2026' : 'id_perseo_prolipa2026_produccion';
            } else if ($empresa == 4) {
                $productoBuscar = $perseoProduccion == 0 ? 'id_perseo_calmed2026' : 'id_perseo_calmed2026_produccion';
            } else {
                throw new Exception('ID de empresa no válido');
            }
            
            if(!$productoBuscar) {
                throw new Exception('No se pudo determinar la columna de producto Perseo a usar');
            }
            
            // Obtener detalles de la venta con el ID de Perseo del producto
            $detalle = DB::table('f_detalle_venta as vd')
                ->select(
                    'vd.*', 
                    DB::raw('(vd.det_ven_cantidad * vd.det_ven_valor_u) AS valorTotal'), 
                    DB::raw("`$productoBuscar` AS idPerseoProducto")
                )
                ->leftJoin('1_4_cal_producto as p', 'vd.pro_codigo', '=', 'p.pro_codigo')
                ->where('vd.ven_codigo', $ven_codigo)
                ->where('vd.id_empresa', $empresa)
                ->get();
            
            if ($detalle->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'La venta no tiene detalles'
                ], 400);
            }
            
            // Calcular total y preparar detalles
            $totalFactura = 0;
            $detalles = [];
            $productosFaltantes = [];
            
            foreach ($detalle as $d) {
                $totalFactura += $d->valorTotal;
                
                $pro_codigo = $d->pro_codigo;
                $id_perseo = $d->idPerseoProducto;
                
                if ($id_perseo == 0 || $id_perseo == null || $id_perseo == "") {
                    $productosFaltantes[] = $pro_codigo;
                } else {
                    $detalles[] = [
                        "pedidosid" => 1,
                        "centros_costosid" => 1,
                        "productosid" => $id_perseo,
                        "medidasid" => 1,
                        "almacenesid" => 1,
                        "cantidaddigitada" => $d->det_ven_cantidad,
                        "cantidad" => $d->det_ven_cantidad,
                        "cantidadfactor" => 1,
                        "precio" => $d->det_ven_valor_u,
                        "preciovisible" => $d->det_ven_valor_u,
                        "iva" => 0,
                        "precioiva" => $d->det_ven_valor_u,
                        "descuento" => $discount
                    ];
                }
            }
            
            // Verificar si hay productos sin ID de Perseo
            if (!empty($productosFaltantes)) {
                $codigosFaltantes = implode(', ', $productosFaltantes);
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => "Los siguientes códigos no se encuentran en Perseo: $codigosFaltantes. Por favor contacte con el administrador del sistema.",
                    'codigosFaltantes' => $codigosFaltantes
                ], 400);
            }
            
            $totalFactura = number_format($totalFactura, 2, '.', '');
            
            // Preparar estructura JSON para Perseo
            $formData = [
                "registro" => [
                    [
                        "pedidos" => [
                            "pedidosid" => 1,
                            "emision" => date('Ymd'),
                            "pedidos_codigo" => "P000000001",
                            "forma_pago_empresaid" => "01",
                            "facturadoresid" => 1,
                            "clientesid" => $clientesidPerseo,
                            "almacenesid" => 1,
                            "centros_costosid" => 1,
                            "vendedoresid" => 3,
                            "tarifasid" => 1,
                            "concepto" => $concepto,
                            "origen" => "0",
                            "documentosid" => 0,
                            "observacion" => $observacion,
                            "subtotalsiniva" => $ven_valor,
                            "subtotalconiva" => $ven_valor,
                            "total_descuento" => $ven_descuento,
                            "subtotalneto" => $ven_valor,
                            "total_iva" => 0,
                            "total" => $ven_valor,
                            "empresaid" => 1,
                            "usuarioid" => 3,
                            "usuariocreacion" => "IMOVIL",
                            "fechacreacion" => date('Y-m-d H:i:s'),
                            "detalles" => $detalles
                        ]
                    ]
                ]
            ];
            
            // Enviar a Perseo usando el trait
            $url = "pedidos_crear";
            $process = $this->tr_PerseoPost($url, $formData, $empresa);
            
            // Si la respuesta es exitosa, actualizar estadoPerseo y estado de venta
            if (isset($process["pedidos"])) {
                $pedidoCodigo_nuevo = $process["pedidos"][0]["pedidos_codigo"];
                $idpedidoCodigo_nuevo = $process["pedidos"][0]["pedidosid_nuevo"];

                Ventas::where('ven_codigo', $ven_codigo)
                      ->where('id_empresa', $empresa)
                      ->update([
                          'estadoPerseo' => 1,
                        //   'est_ven_codigo' => 1, // Cambiar a Despachado
                          'pedido_codigo' => $pedidoCodigo_nuevo,
                          'id_pedido_perseo' => $idpedidoCodigo_nuevo,
                          'fecha_envio_perseo' => date('Y-m-d H:i:s')
                      ]);
                
                DB::commit();
                
                return response()->json([
                    'status' => 200,
                    'message' => 'Venta enviada a Perseo y marcada como Despachada exitosamente',
                    'data' => [
                        'ven_codigo' => $ven_codigo,
                        'pedido_codigo' => $pedidoCodigo_nuevo,
                        'id_pedido_perseo' => $idpedidoCodigo_nuevo,
                        'pedidos' => $process["pedidos"]
                    ]
                ], 200);
            } else {
                // Si falla, mantener estadoPerseo en 0
                Ventas::where('ven_codigo', $ven_codigo)
                      ->where('id_empresa', $empresa)
                      ->update(['estadoPerseo' => 0]);
                
                DB::rollBack();
                
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al enviar a Perseo',
                    'response' => $process
                ], 400);
            }
            
        } catch (Exception $e) {
            DB::rollBack();
            // \Log::error('❌ Error al enviar venta a Perseo: ' . $e->getMessage());
            
            return response()->json([
                'status' => 0,
                'message' => 'Ocurrió un error al intentar enviar el pedido a Perseo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar y actualizar ID de producto en Perseo
     * POST /api/perseo/ventas/verificar-producto
     */
    public function verificarProductoPerseo(Request $request)
    {
        try {
            $codigo = $request->codigo;
            $id_empresa = $request->id_empresa;

            if (!$codigo || !$id_empresa) {
                return response()->json([
                    'estado' => 2,
                    'mensaje' => 'Faltan parámetros: codigo e id_empresa'
                ], 400);
            }

            $empresaData = DB::table('empresas')->where('id', $id_empresa)->first();

            if (!$empresaData) {
                return response()->json([
                    'estado' => 2,
                    'mensaje' => 'Empresa no encontrada'
                ], 400);
            }

            $perseoProduccion = $empresaData->perseo_enviroment != null ? $empresaData->perseo_enviroment : 1;

            $campoPerseo = '';
            if ($id_empresa == 1) {
                $campoPerseo = $perseoProduccion == 0 ? 'id_perseo_prolipa' : 'id_perseo_prolipa_produccion';
            } else if ($id_empresa == 3) {
                $campoPerseo = $perseoProduccion == 0 ? 'id_perseo_calmed' : 'id_perseo_calmed_produccion';
            } else if ($id_empresa == 5) {
                $campoPerseo = $perseoProduccion == 0 ? 'id_perseo_prolipa2026' : 'id_perseo_prolipa2026_produccion';
            } else if ($id_empresa == 4) {
                $campoPerseo = $perseoProduccion == 0 ? 'id_perseo_calmed2026' : 'id_perseo_calmed2026_produccion';
            } else {
                return response()->json([
                    'estado' => 2,
                    'mensaje' => 'ID de empresa no válido'
                ], 400);
            }

            $producto = DB::table('1_4_cal_producto')
                ->where(function ($q) use ($campoPerseo) {
                    $q->whereNull($campoPerseo)
                      ->orWhere($campoPerseo, 0);
                })
                ->where('pro_codigo', $codigo)
                ->first();

            if (!$producto) {
                return response()->json([
                    'estado' => 0,
                    'mensaje' => 'No hay registros para actualizar'
                ]);
            }

            $formData = [
                "productocodigo" => $producto->pro_codigo,
            ];

            $response = $this->tr_PerseoPost("productos_consulta", $formData, $id_empresa);

            if (isset($response["informacion"]) && $response["informacion"] === false) {
                return response()->json([
                    'estado' => 2,
                    'mensaje' => "El producto {$producto->pro_codigo} no se encuentra en Perseo"
                ]);
            }

            if (isset($response["productos"]) && isset($response["productos"][0]["productosid"])) {
                $idPerseo = $response["productos"][0]["productosid"];
                DB::table('1_4_cal_producto')
                    ->where('pro_codigo', $codigo)
                    ->update([$campoPerseo => $idPerseo]);

                return response()->json([
                    'estado' => 1,
                    'mensaje' => 'Actualización exitosa'
                ]);
            } else {
                DB::table('1_4_cal_producto')
                    ->where('pro_codigo', $codigo)
                    ->update([$campoPerseo => 0]);

                return response()->json([
                    'estado' => 2,
                    'mensaje' => 'No se obtuvo ID válido desde Perseo'
                ]);
            }

        } catch (Exception $e) {
            return response()->json([
                'estado' => 2,
                'mensaje' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Anular venta - Cambiar estado a Anulado
     * POST /api/perseo/ventas/anular
     */
    public function anularVenta(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $ven_codigo = $request->ven_codigo;
            $empresa = $request->id_empresa;
            $observacionAnulacion = $request->observacionAnulacion;
            $id_usuario = $request->id_usuario;
            
            // Validar parámetros requeridos
            if (!$ven_codigo || !$empresa) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan parámetros requeridos: ven_codigo e id_empresa'
                ], 400);
            }
            
            if (!$observacionAnulacion || trim($observacionAnulacion) === '') {
                return response()->json([
                    'status' => 0,
                    'message' => 'Debe proporcionar una observación de anulación'
                ], 400);
            }
            
            // Obtener la venta
            $venta = Ventas::where('ven_codigo', $ven_codigo)
                          ->where('id_empresa', $empresa)
                          ->first();
            
            // Validar que exista la venta
            if (!$venta) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'La venta no existe'
                ], 404);
            }
            
            // Validar que no esté ya anulada
            if ($venta->est_ven_codigo == 3) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'La venta ya está anulada'
                ], 400);
            }
            
            // Actualizar estado a Anulado
            $dataUpdate = [
                'est_ven_codigo' => 3, // Estado anulado
                'observacionAnulacion' => $observacionAnulacion,
                'user_anulado' => $id_usuario,
                'fecha_anulacion' => now()
            ];
            
            Ventas::where('ven_codigo', $ven_codigo)
                  ->where('id_empresa', $empresa)
                  ->update($dataUpdate);
            
            DB::commit();
            
            $mensaje = 'Venta anulada exitosamente';
            
            // Si estaba enviada a Perseo, agregar nota en el mensaje
            if ($venta->estadoPerseo == 1) {
                $mensaje .= '. El pedido de Perseo ' . ($venta->pedido_codigo ?? '') . ' queda marcado como no válido.';
            }
            
            return response()->json([
                'status' => 200,
                'message' => $mensaje,
                'data' => [
                    'ven_codigo' => $ven_codigo,
                    'pedido_codigo' => $venta->pedido_codigo,
                    'estadoPerseo' => $venta->estadoPerseo
                ]
            ], 200);
            
        } catch (Exception $e) {
            DB::rollBack();
            // \Log::error('❌ Error al anular venta: ' . $e->getMessage());
            
            return response()->json([
                'status' => 0,
                'message' => 'Ocurrió un error al intentar anular la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // ============================================
    // MÉTODOS PARA VENTAS DE OBSEQUIOS
    // ============================================
    
    /**
     * Obtener pedidos de obsequios por contrato
     */
    public function obtenerPedidosObsequiosPorContrato(Request $request)
    {
        try {
            $contrato = $request->input('contrato');
            
            if (empty($contrato)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'El contrato es requerido'
                ], 400);
            }
            
            $pedidosObsequios = DB::SELECT("
                SELECT pa.*, i.nombreInstitucion, c.nombre AS ciudad, p.contrato_generado,
                CONCAT(u.nombres,' ',u.apellidos) AS asesor,
                CONCAT(uf.apellidos, ' ',uf.nombres) AS facturador,
                CONCAT(uc.apellidos, ' ',uc.nombres) AS creador, 
                uc.id_group AS grupo_creador,
                CASE uc.id_group
                    WHEN 0 THEN 'ROOT'
                    WHEN 1 THEN 'ADMIN'
                    WHEN 11 THEN 'ASESOR'
                    WHEN 22 THEN 'FACTURADOR'
                    WHEN 23 THEN 'ADMIN FACTURADOR'
                    ELSE 'OTRO PERFIL'
                END AS tipo_creador,
                CONCAT(ua.apellidos, ' ',ua.nombres) AS aprobador, 
                ua.id_group AS grupo_aprobador
                FROM p_libros_obsequios pa
                LEFT JOIN pedidos p ON pa.id_pedido = p.id_pedido
                LEFT JOIN usuario u ON p.id_asesor = u.idusuario
                LEFT JOIN usuario uf ON p.id_usuario_verif = uf.idusuario
                LEFT JOIN usuario uc ON pa.user_created = uc.idusuario
                LEFT JOIN usuario ua ON pa.usuario_aprobacion_id = ua.idusuario
                LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
                LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
                WHERE p.contrato_generado = ?
                AND pa.estado_libros_obsequios IN (4,5,9)
                ORDER BY pa.id DESC
            ", [$contrato]);
            
            return response()->json([
                'status' => 200,
                'data' => $pedidosObsequios
            ], 200);
            
        } catch (Exception $e) {
            // \Log::error('Error al obtener pedidos obsequios: ' . $e->getMessage());
            
            return response()->json([
                'status' => 0,
                'message' => 'Error al obtener pedidos obsequios',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener detalle de libros de un pedido de obsequios
     * Devuelve los libros con sus cantidades aprobadas y pendientes
     */
    public function obtenerDatosDetalleLibros($id)
    {
        try {
            if (empty($id)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'El ID del pedido de obsequios es requerido'
                ], 400);
            }
            
            $detalleLibros = DB::SELECT("
                SELECT 
                    pdl.id_detalle AS id,
                    pdl.p_libros_obsequios_id,
                    pdl.pro_codigo,
                    pdl.p_libros_valor_u,
                    pdl.p_libros_cantidad,
                    pdl.p_libros_cantidad_aprobada,
                    pdl.p_libros_cantidad_pendiente,
                    l.nombre AS nombre_libro
                FROM p_detalle_libros_obsequios pdl
                INNER JOIN p_libros_obsequios po ON pdl.p_libros_obsequios_id = po.id
                LEFT JOIN libros_series l ON pdl.pro_codigo = l.codigo_liquidacion
                WHERE pdl.p_libros_obsequios_id = ?
                AND po.estado_libros_obsequios <> 0
                ORDER BY pdl.id_detalle ASC
            ", [$id]);
            
            return response()->json([
                'status' => 200,
                'data' => $detalleLibros,
                'message' => 'Detalle de libros obtenido correctamente'
            ], 200);
            
        } catch (Exception $e) {
            // \Log::error('Error al obtener detalle de libros obsequios: ' . $e->getMessage());
            
            return response()->json([
                'status' => 0,
                'message' => 'Error al obtener detalle de libros',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generar venta de obsequios (similar a generarVenta pero para obsequios)
     * Actualiza p_detalle_libros_obsequios: aprobada += vendido, pendiente -= vendido
     */
    public function generarVentaObsequios(Request $request)
    {
        DB::beginTransaction();
        
        try {
            // Obtener datos del request
            $p_libros_obsequios_id = $request->input('p_libros_obsequios_id');
            $productosVenta = $request->input('productosVenta', []);
            $infoVenta = $request->input('infoVenta');
            
            // Validaciones básicas
            if (empty($p_libros_obsequios_id)) {
                throw new Exception('El ID del pedido obsequio es requerido');
            }
            
            if (empty($productosVenta) || !is_array($productosVenta)) {
                throw new Exception('Los productos de venta son requeridos');
            }
            
            if (empty($infoVenta)) {
                throw new Exception('La información de venta es requerida');
            }
            
            // Validar campos obligatorios de infoVenta
            $camposFaltantes = [];
            
            if (empty($infoVenta['id_empresa'])) {
                $camposFaltantes[] = 'id_empresa';
            }
            
            if (empty($infoVenta['clienteId'])) {
                $camposFaltantes[] = 'clienteId';
            }

            if (!isset($infoVenta['clientesidPerseo']) || $infoVenta['clientesidPerseo'] === null || $infoVenta['clientesidPerseo'] === '') {
                $camposFaltantes[] = 'clientesidPerseo';
            }
            
            if (empty($infoVenta['ven_idUsuario'])) {
                $camposFaltantes[] = 'ven_idUsuario';
            }
            
            if (empty($infoVenta['institucion_id'])) {
                $camposFaltantes[] = 'institucion_id';
            }
            
            if (!isset($infoVenta['tip_ven_codigo'])) {
                $camposFaltantes[] = 'tip_ven_codigo';
            }
            
            if (!isset($infoVenta['ven_subtotal'])) {
                $camposFaltantes[] = 'ven_subtotal';
            }
            
            if (!isset($infoVenta['ven_desc_por'])) {
                $camposFaltantes[] = 'ven_desc_por';
            }
            
            if (!isset($infoVenta['ven_desc_valor'])) {
                $camposFaltantes[] = 'ven_desc_valor';
            }
            
            if (!isset($infoVenta['ven_valor'])) {
                $camposFaltantes[] = 'ven_valor';
            }
            
            if (empty($infoVenta['ven_observacion'])) {
                $camposFaltantes[] = 'ven_observacion';
            }
            
            if (empty($infoVenta['ven_concepto_perseo'])) {
                $camposFaltantes[] = 'ven_concepto_perseo';
            }
            
            if (!empty($camposFaltantes)) {
                throw new Exception('Faltan los siguientes campos obligatorios en infoVenta: ' . implode(', ', $camposFaltantes));
            }
            
            // Validar que exista el pedido obsequio
            $pedidoObsequio = DB::SELECT("
                SELECT po.*, p.contrato_generado, p.id_periodo 
                FROM p_libros_obsequios po
                LEFT JOIN pedidos p ON po.id_pedido = p.id_pedido
                WHERE po.id = ?
            ", [$p_libros_obsequios_id]);
            
            if (empty($pedidoObsequio)) {
                throw new Exception('Pedido obsequio no encontrado');
            }
            
            $pedidoObsequio = $pedidoObsequio[0];

             // Tipo de documento 1 = Prefactura
            $tipoDocumento = $infoVenta['ven_desc_por']==100 ? 2 : 4;  // Tipo de documento: 1 = prefactura, 2 = actas
            // Generar código de venta utilizando el método existente
            $codigoVenta = $this->generarCodigoVenta(
                $infoVenta['id_empresa'],
                $tipoDocumento, // Tipo documento prefactura
                $pedidoObsequio->contrato_generado,
                $infoVenta['ven_idUsuario']
            );
            
            // Crear registro en ventas
            DB::table('f_venta')->insert([
                'ven_codigo' => $codigoVenta,
                'contrato' => $pedidoObsequio->contrato_generado,
                'tip_ven_codigo' => $infoVenta['tip_ven_codigo'],
                'est_ven_codigo' => 2,
                'ven_idproforma' => null,
                'ven_tipo_inst' => null,
                'ven_comision' => null,
                'ven_com_porcentaje' => null,
                'ven_valor' => $infoVenta['ven_valor'],
                'ven_subtotal' => $infoVenta['ven_subtotal'],
                'ven_pagado' => null,
                'ven_desc_por' => $infoVenta['ven_desc_por'],
                'ven_descuento' => $infoVenta['ven_desc_valor'],
                'ven_iva_por' => null,
                'ven_iva' => null,
                'ven_fecha' => now(),
                'ven_transporte' => null,
                'ven_devolucion' => null,
                'ven_remision' => null,
                'ven_fech_remision' => null,
                'institucion_id' => $infoVenta['institucion_id'],
                'periodo_id' => $pedidoObsequio->id_periodo,
                'updated_at' => now(),
                'user_created' => $infoVenta['ven_idUsuario'],
                'id_empresa' => $infoVenta['id_empresa'],
                'id_ins_depacho' => null,
                'id_sucursal' => null,
                'impresion' => 0,
                'ven_observacion' => $infoVenta['ven_observacion'],
                'ven_concepto_perseo' => $infoVenta['ven_concepto_perseo'],
                'idtipodoc' => $infoVenta['ven_desc_por']==100 ? 2 : 4, // Tipo de documento: 1 = prefactura, 2 = actas
                'ven_p_libros_obsequios' => $p_libros_obsequios_id,
                'ven_cliente' => $infoVenta['clienteId'],
                'estadoPerseo' => 0,
                'idPerseo' => 0,
                'proformas_codigo' => null,
                'fecha_envio_perseo' => null,
                'clientesidPerseo' => $infoVenta['clientesidPerseo'],
                'id_factura' => null,
                'ruc_cliente' => $infoVenta['ruc_cliente'] ?? null,
                'anuladoEnPerseo' => 0,
                'doc_intercambio' => null,
                'user_intercambio' => 0,
                'fecha_intercambio' => null,
                'observacion_intercambio' => null,
                'user_anulado' => null,
                'fecha_anulacion' => null,
                'observacionAnulacion' => null,
                'id_pedido' => null,
                'fecha_notaCredito' => null,
                'fecha_sri' => null,
                'documento_sri' => null,
                'observacionRegresarAPendiente' => null,
                'fecha_update_sri' => null,
                'usuario_update_sri' => null,
                'user_despacha' => null,
                'fecha_proceso_despacho' => null,
                'destino' => 0
            ]);
            
            // Guardar detalles de venta
            foreach ($productosVenta as $producto) {
                DB::table('f_detalle_venta')->insert([
                    'ven_codigo' => $codigoVenta,
                    'id_empresa' => $infoVenta['id_empresa'],
                    'pro_codigo' => $producto['pro_codigo'],
                    'det_ven_cantidad' => $producto['cantidad'],
                    'contrato' => $pedidoObsequio->contrato_generado,
                    'det_ven_valor_u' => $producto['precio'],
                    'det_ven_cantidad_despacho' => 0,
                    'idProforma' => null,
                    'det_ven_dev' => 0,
                    'det_cant_intercambio' => 0,
                    'doc_intercambio' => null,
                    'detalle_notaCreditInterna' => null,
                    'descuento' => 0
                ]);
                
                // Actualizar p_detalle_libros_obsequios
                DB::statement("
                    UPDATE p_detalle_libros_obsequios 
                    SET p_libros_cantidad_aprobada = COALESCE(p_libros_cantidad_aprobada, 0) + ?,
                        p_libros_cantidad_pendiente = p_libros_cantidad_pendiente - ?,
                        updated_at = NOW()
                    WHERE p_libros_obsequios_id = ? 
                    AND pro_codigo = ?
                ", [
                    $producto['cantidad'],
                    $producto['cantidad'],
                    $p_libros_obsequios_id,
                    $producto['pro_codigo']
                ]);
            }
            
            // Verificar si todos los productos tienen cantidad_pendiente = 0
            $productosPendientes = DB::SELECT("
                SELECT COUNT(*) as total_pendientes
                FROM p_detalle_libros_obsequios
                WHERE p_libros_obsequios_id = ?
                AND p_libros_cantidad_pendiente > 0
            ", [$p_libros_obsequios_id]);
            
            // Si no hay productos pendientes, actualizar estado del pedido a 9 (Completado)
            if ($productosPendientes && $productosPendientes[0]->total_pendientes == 0) {
                DB::statement("
                    UPDATE p_libros_obsequios 
                    SET estado_libros_obsequios = 9,
                        updated_at = NOW()
                    WHERE id = ?
                ", [$p_libros_obsequios_id]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 200,
                'message' => 'Venta de obsequios generada exitosamente',
                'data' => [
                    'ven_codigo' => $codigoVenta,
                    'p_libros_obsequios_id' => $p_libros_obsequios_id
                ]
            ], 200);
            
        } catch (Exception $e) {
            DB::rollBack();
            // \Log::error('Error al generar venta obsequios: ' . $e->getMessage());
            
            return response()->json([
                'status' => 0,
                'message' => 'Error al generar venta de obsequios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anular venta de obsequios y revertir cantidades en p_detalle_libros_obsequios
     * POST /Venta_Obsequios_Perseo_Anular
     */
    public function anularVentaObsequios(Request $request)
    {
        DB::beginTransaction();
        try {
            $ven_codigo          = $request->ven_codigo;
            $id_empresa          = $request->id_empresa;
            $observacionAnulacion = $request->observacionAnulacion;
            $id_usuario          = $request->id_usuario;

            if (!$ven_codigo || !$id_empresa) {
                throw new Exception('Faltan parámetros obligatorios (ven_codigo, id_empresa)');
            }
            if (!$observacionAnulacion || trim($observacionAnulacion) === '') {
                throw new Exception('Debe proporcionar un motivo de anulación');
            }

            $venta = DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $id_empresa)
                ->first();

            if (!$venta) {
                throw new Exception('La venta no existe');
            }
            if ($venta->est_ven_codigo == 3) {
                throw new Exception('La venta ya está anulada');
            }
            if (!$venta->ven_p_libros_obsequios) {
                throw new Exception('Esta venta no corresponde a una venta de obsequios');
            }

            $p_libros_obsequios_id = $venta->ven_p_libros_obsequios;

            // Revertir cantidades en el detalle de obsequios
            $detalles = DB::table('f_detalle_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $id_empresa)
                ->get();

            foreach ($detalles as $detalle) {
                DB::statement("
                    UPDATE p_detalle_libros_obsequios
                    SET p_libros_cantidad_aprobada = GREATEST(0, COALESCE(p_libros_cantidad_aprobada, 0) - ?),
                        p_libros_cantidad_pendiente = COALESCE(p_libros_cantidad_pendiente, 0) + ?,
                        updated_at = NOW()
                    WHERE p_libros_obsequios_id = ? AND pro_codigo = ?
                ", [
                    $detalle->det_ven_cantidad,
                    $detalle->det_ven_cantidad,
                    $p_libros_obsequios_id,
                    $detalle->pro_codigo
                ]);
            }

            // Si el pedido de obsequios estaba en estado 9 (Completado), regresarlo a 5 (Aprobado/En proceso)
            $pedidoObsequio = DB::table('p_libros_obsequios')
                ->where('id', $p_libros_obsequios_id)
                ->first();

            if ($pedidoObsequio && $pedidoObsequio->estado_libros_obsequios == 9) {
                DB::table('p_libros_obsequios')
                    ->where('id', $p_libros_obsequios_id)
                    ->update([
                        'estado_libros_obsequios' => 5,
                        'updated_at'              => now()
                    ]);
            }

            // Anular la venta
            DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $id_empresa)
                ->update([
                    'est_ven_codigo'      => 3,
                    'observacionAnulacion' => trim($observacionAnulacion),
                    'user_anulado'        => $id_usuario,
                    'fecha_anulacion'     => now(),
                    'updated_at'          => now()
                ]);

            DB::commit();

            return response()->json([
                'status'  => 200,
                'message' => 'Venta de obsequios anulada exitosamente',
                'data'    => ['ven_codigo' => $ven_codigo]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 0,
                'message' => 'Error al anular venta de obsequios',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consultar pedido en Perseo (solo pedido, sin facturas)
     * POST /api/perseo/ventas/pedidos-consulta
     */
    public function pedidos_consulta(Request $request)
    {
        try {
            $ven_codigo = $request->ven_codigo;
            $empresa = $request->empresa;
            
            if (!$ven_codigo || !$empresa) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan parámetros requeridos: ven_codigo y empresa'
                ], 400);
            }
            
            // Obtener información de la venta
            $venta = DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $empresa)
                ->first();
            
            if (!$venta) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Venta no encontrada'
                ], 404);
            }
            
            // Validar que tenga id_pedido_perseo
            if (!$venta->id_pedido_perseo) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Esta venta no ha sido enviada a Perseo aún'
                ], 400);
            }
            
            // Preparar parámetros para la API de Perseo
            $formData = [
                "pedidosid" => $venta->id_pedido_perseo,
            ];
            
            // Llamar a la API de Perseo para consultar el pedido
            $url = "pedidos_consulta";
            $process = $this->tr_PerseoPost($url, $formData, $empresa);
            
            return response()->json([
                'status' => 200,
                'message' => 'Consulta realizada exitosamente',
                'data' => $process,
                'venta_info' => [
                    'ven_codigo' => $venta->ven_codigo,
                    'id_pedido_perseo' => $venta->id_pedido_perseo,
                    'pedido_codigo' => $venta->pedido_codigo,
                    'fecha_envio_perseo' => $venta->fecha_envio_perseo,
                    'id_factura_perseo' => $venta->id_factura_perseo,
                    'factura_perseo' => $venta->factura_perseo
                ]
            ], 200);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error al consultar pedido en Perseo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar y enlazar factura en Perseo
     * POST /api/perseo/ventas/facturas-verificar
     */
    public function facturas_verificar(Request $request)
    {
        try {
            $ven_codigo = $request->ven_codigo;
            $empresa = $request->empresa;
            
            if (!$ven_codigo || !$empresa) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan parámetros requeridos: ven_codigo y empresa'
                ], 400);
            }
            
            // Obtener información de la venta
            $venta = DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $empresa)
                ->first();
            
            if (!$venta) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Venta no encontrada'
                ], 404);
            }
            
            if (!$venta->id_pedido_perseo) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Esta venta no ha sido enviada a Perseo'
                ], 400);
            }
            
            // Primero consultar el pedido para obtener sus datos
            $formDataPedido = [
                "pedidosid" => $venta->id_pedido_perseo,
            ];
            
            $processPedido = $this->tr_PerseoPost("pedidos_consulta", $formDataPedido, $empresa);
            
            if (!isset($processPedido['pedidos']) || count($processPedido['pedidos']) == 0) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No se encontró el pedido en Perseo'
                ], 404);
            }
            
            $pedido = $processPedido['pedidos'][0];
            
            // Verificar que el pedido esté facturado (estado = 3)
            if ($pedido['estado'] != 3) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El pedido aún no está facturado',
                    'pedido_estado' => $pedido['estado']
                ], 400);
            }
            
            // El pedido está facturado, buscar la factura correspondiente
            $clientesid = $pedido['clientesid'];
            $emisionPedido = $pedido['emision'];
            $clientePedido = $pedido['cliente'] ?? '';
            $detallesPedido = $pedido['detalles'] ?? [];
            
            // Consultar facturas en Perseo
            $urlFacturas = "facturas_consulta";
            $formDataFacturas = [
                "facturaid" => "",
                "dias" => "7",
                "generarpdf" => false
            ];
            
            $processFacturas = $this->tr_PerseoPost($urlFacturas, $formDataFacturas, $empresa);
            
            $facturaInfo = null;
            $facturaActualizada = false;
            $facturaEncontrada = false;
            
            // Buscar la factura que coincida con el pedido
            if (isset($processFacturas['facturas']) && count($processFacturas['facturas']) > 0) {
                foreach ($processFacturas['facturas'] as $factura) {
                    // Verificar que la fecha de emisión coincida
                    if ($factura['emision'] == $emisionPedido && $factura['clientesid'] == $clientesid) {
                        // Obtener razón social de la factura (campo "razonsocial" en Perseo)
                        $razonSocialFactura = $factura['razonsocial'] ?? ($factura['cliente'] ?? '');
                        
                        // Comparar cliente del pedido con razón social de la factura (tolerante a espacios)
                        $nombreCoincide = $this->compararNombres($clientePedido, $razonSocialFactura);
                        
                        if (!$nombreCoincide) {
                            // No coincide el cliente, seguir buscando
                            continue;
                        }
                        
                        $detallesFactura = $factura['facturasd']['detalles'] ?? [];
                        
                        // Comparar los detalles del pedido con los de la factura
                        $coincide = $this->compararDetalles($detallesPedido, $detallesFactura);
                        
                        if ($coincide) {
                            // Encontramos la factura correcta
                            $facturaEncontrada = true;
                            $facturaPerseoID = $factura['facturasid'];
                            $establecimiento = $factura['establecimiento'];
                            $puntoEmision = $factura['puntoemision'];
                            $secuencial = $factura['secuencial'];
                            $facturaCodigo = "{$establecimiento}-{$puntoEmision}-{$secuencial}";
                            
                            // Actualizar la venta con la información de la factura
                            DB::table('f_venta')
                                ->where('ven_codigo', $ven_codigo)
                                ->where('id_empresa', $empresa)
                                ->update([
                                    'id_factura_perseo' => $facturaPerseoID,
                                    'factura_perseo' => $facturaCodigo,
                                    'updated_at' => now()
                                ]);
                            
                            $facturaInfo = [
                                'id_factura_perseo' => $facturaPerseoID,
                                'factura_perseo' => $facturaCodigo,
                                'factura_completa' => $factura
                            ];
                            
                            $facturaActualizada = true;
                            break;
                        }
                    }
                }
            }
            
            // Si no se encontró la factura, retornar info pero sin error
            if (!$facturaEncontrada) {
                return response()->json([
                    'status' => 200,
                    'message' => 'No se encontró una factura que coincida con el pedido',
                    'factura_encontrada' => false,
                    'facturas_disponibles' => $processFacturas,
                    'pedido_info' => $pedido
                ], 200);
            }
            
            return response()->json([
                'status' => 200,
                'message' => 'Factura vinculada exitosamente',
                'factura_encontrada' => true,
                'factura_info' => $facturaInfo,
                'factura_actualizada' => $facturaActualizada,
                'facturas_completo' => $processFacturas
            ], 200);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error al verificar factura en Perseo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Comparar nombres de cliente (tolerante a espacios y mayúsculas/minúsculas)
     */
    private function compararNombres($nombre1, $nombre2)
    {
        // Normalizar nombres: quitar espacios extras, convertir a minúsculas, quitar acentos
        $nombre1Normalizado = $this->normalizarNombre($nombre1);
        $nombre2Normalizado = $this->normalizarNombre($nombre2);
        
        return $nombre1Normalizado === $nombre2Normalizado;
    }
    
    /**
     * Normalizar nombre para comparación
     */
    private function normalizarNombre($nombre)
    {
        if (empty($nombre)) {
            return '';
        }
        
        // Convertir a minúsculas
        $nombre = mb_strtolower($nombre, 'UTF-8');
        
        // Quitar espacios extras (al inicio, fin, y duplicados)
        $nombre = trim($nombre);
        $nombre = preg_replace('/\s+/', ' ', $nombre);
        
        // Quitar acentos y caracteres especiales
        $acentos = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'];
        $sinAcentos = ['a', 'e', 'i', 'o', 'u', 'n', 'u'];
        $nombre = str_replace($acentos, $sinAcentos, $nombre);
        
        return $nombre;
    }
    
    /**
     * Comparar detalles del pedido con detalles de la factura
     */
    private function compararDetalles($detallesPedido, $detallesFactura)
    {
        // Si no tienen la misma cantidad de items, no coinciden
        if (count($detallesPedido) != count($detallesFactura)) {
            return false;
        }
        
        // Crear un array asociativo de los detalles del pedido por código de producto
        $pedidoMap = [];
        foreach ($detallesPedido as $detallePedido) {
            $codigo = $detallePedido['productocodigo'];
            $cantidad = $detallePedido['cantidad'];
            $pedidoMap[$codigo] = $cantidad;
        }
        
        // Verificar que cada detalle de la factura coincida con el pedido
        foreach ($detallesFactura as $detalleFactura) {
            $codigo = $detalleFactura['productocodigo'];
            $cantidad = $detalleFactura['cantidad'];
            
            // Si el código no existe en el pedido o la cantidad no coincide, no es la factura correcta
            if (!isset($pedidoMap[$codigo]) || $pedidoMap[$codigo] != $cantidad) {
                return false;
            }
        }
        
        return true;
    }

    public function vetasXDespachar(Request $request)
    {
        try {
            $estado = $request->input('estado');
            $fechaDesde = $request->input('fecha_desde');
            $fechaHasta = $request->input('fecha_hasta');
            
            $whereEstado = '';
            if ($estado == 'pendiente' || $estado == '2') {
                $whereEstado = 'AND fv.est_ven_codigo = 2';
            } elseif ($estado == 'despachado' || $estado == '1') {
                $whereEstado = 'AND fv.est_ven_codigo = 1';
            } else {
                $whereEstado = 'AND fv.est_ven_codigo IN (1, 2)';
            }

            $whereFechas = '';
            $bindings = [];
            if ($fechaDesde && $fechaHasta) {
                $whereFechas = 'AND DATE(fv.ven_fecha) BETWEEN ? AND ?';
                $bindings[] = $fechaDesde;
                $bindings[] = $fechaHasta;
            } elseif ($fechaDesde) {
                $whereFechas = 'AND DATE(fv.ven_fecha) >= ?';
                $bindings[] = $fechaDesde;
            } elseif ($fechaHasta) {
                $whereFechas = 'AND DATE(fv.ven_fecha) <= ?';
                $bindings[] = $fechaHasta;
            }
            
            $ventas = DB::SELECT("
                SELECT 
                    fv.ven_codigo,
                    fv.id_empresa,
                    fv.ven_idproforma,
                    fv.est_ven_codigo,
                    fv.idtipodoc,
                    fv.ven_subtotal,
                    fv.ven_descuento,
                    fv.ven_iva,
                    fv.ven_transporte,
                    fv.ven_valor,
                    fv.ven_observacion,
                    fv.ven_desc_por,
                    fv.user_created,
                    fv.ven_fecha,
                    fv.periodo_id,
                    fv.ven_tipo_inst,
                    fv.contrato,
                    fv.ven_p_libros_obsequios,
                    fv.ven_cliente,
                    fv.estadoPerseo,
                    fv.id_factura_perseo,
                    fv.factura_perseo,
                    fv.pedido_codigo,
                    fv.ven_concepto_perseo,
                    e.id as empresa_id,
                    e.nombre as empresa_nombre,
                    e.descripcion_corta AS empresa,
                    e.img_base64 as empresa_logo,
                    CONCAT(COALESCE(u.nombres, ''), ' ', COALESCE(u.apellidos, '')) AS cliente_nombre,
                    CONCAT(COALESCE(u.nombres, ''), ' ', COALESCE(u.apellidos, '')) AS nombre_cliente,
                    u.cedula as cliente_ruc,
                    u.cedula as cedula,
                    u.email as cliente_email,
                    u.email as email,
                    u.telefono as cliente_telefono,
                    u.telefono as telefono,
                    i.idInstitucion as institucion_id,
                    i.nombreInstitucion as institucion_nombre,
                    i.nombreInstitucion,
                    i.ruc as institucion_ruc,
                    i.direccionInstitucion as institucion_direccion,
                    i.direccionInstitucion as direccionInstitucion,
                    i.telefonoInstitucion as institucion_telefono,
                    CONCAT(COALESCE(uf.nombres, ''), ' ', COALESCE(uf.apellidos, '')) AS nombre_facturador,
                    CONCAT(COALESCE(uf.nombres, ''), ' ', COALESCE(uf.apellidos, '')) AS creado_por,
                    uf.cedula as creado_por_cedula,
                    p.periodoescolar,
                    td.tdo_id,
                    td.tdo_nombre,
                    case when fv.tip_ven_codigo = 1 then 'Venta Directa'
                        when fv.tip_ven_codigo = 2 then 'Venta Lista'
                        else 'Otro Tipo' end as tipo_venta,
                    CASE fv.est_ven_codigo
                        WHEN 1 THEN 'Despachado'
                        WHEN 2 THEN 'Pendiente'
                        WHEN 3 THEN 'Anulado'
                        WHEN 10 THEN 'Empacado'
                        WHEN 11 THEN 'Pendiente Excluido'
                        WHEN 12 THEN 'Preparado'
                        ELSE 'Desconocido'
                    END AS estado_nombre,                    
                    fv.factura_perseo,
                    fv.id_factura_perseo,
                    fv.id_pedido_guia,
                    CONCAT(uguia.nombres, ' ', uguia.apellidos) AS asesor_guia
                FROM f_venta fv
                LEFT JOIN empresas e ON e.id = fv.id_empresa
                LEFT JOIN usuario u ON u.idusuario = fv.ven_cliente
                LEFT JOIN institucion i ON i.idInstitucion = fv.institucion_id
                LEFT JOIN periodoescolar p ON p.idperiodoescolar = fv.periodo_id
                LEFT JOIN usuario uf ON uf.idusuario = fv.user_created
                LEFT JOIN f_tipo_documento td ON fv.idtipodoc = td.tdo_id
                LEFT JOIN pedidos peguia ON peguia.id_pedido = fv.id_pedido_guia
                LEFT JOIN usuario uguia ON peguia.id_asesor = uguia.idusuario
                WHERE fv.est_ven_codigo <> 3
                AND fv.estadoPerseo = 1
                $whereEstado
                $whereFechas
                ORDER BY fv.ven_fecha DESC
            ", $bindings);
            
            $totalPendientes = 0;
            $totalDespachadas = 0;
            
            foreach ($ventas as $venta) {
                if ($venta->est_ven_codigo == 2) {
                    $totalPendientes++;
                } elseif ($venta->est_ven_codigo == 1) {
                    $totalDespachadas++;
                }
            }
            
            return response()->json([
                'status' => 200,
                'data' => $ventas,
                'resumen' => [
                    'total' => count($ventas),
                    'pendientes' => $totalPendientes,
                    'despachadas' => $totalDespachadas
                ]
            ], 200);
            
        } catch (Exception $e) {
            // \Log::error('Error al obtener ventas por despachar: ' . $e->getMessage());
            
            return response()->json([
                'status' => 0,
                'message' => 'Error al obtener ventas por despachar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar como despachado una venta que tiene factura vinculada de Perseo
     * POST /api/perseo/ventas/marcar-despachado
     */
    public function marcarComoDespachadoPerseo(Request $request)
    {
        try {
            $ven_codigo = $request->ven_codigo;
            $empresa = $request->empresa;
            $usuario_id = $request->usuario_id ?? null;
            
            if (!$ven_codigo || !$empresa) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan parámetros requeridos: ven_codigo y empresa'
                ], 400);
            }
            
            // Obtener información de la venta
            $venta = DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $empresa)
                ->first();
            
            if (!$venta) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Venta no encontrada'
                ], 404);
            }
            
            // Validar que tenga factura vinculada de Perseo
            if (!$venta->factura_perseo || !$venta->id_factura_perseo) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Esta venta no tiene una factura vinculada de Perseo. Primero debe verificar y vincular la factura.'
                ], 400);
            }
            
            // Validar que esté en estado pendiente (2)
            if ($venta->est_ven_codigo != 2) {
                $estadoActual = $venta->est_ven_codigo == 1 ? 'Despachado' : 'Otro estado';
                return response()->json([
                    'status' => 0,
                    'message' => "Esta venta ya se encuentra en estado: {$estadoActual}"
                ], 400);
            }
            
            // Cambiar estado a despachado (1)
            DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $empresa)
                ->update([
                    'est_ven_codigo' => 1,
                    'user_despacha' => $usuario_id,
                    'fecha_proceso_despacho' => now(),
                    'updated_at' => now()
                ]);
            
            return response()->json([
                'status' => 200,
                'message' => 'Venta marcada como despachada exitosamente',
                'data' => [
                    'ven_codigo' => $ven_codigo,
                    'factura_perseo' => $venta->factura_perseo,
                    'id_factura_perseo' => $venta->id_factura_perseo,
                    'est_ven_codigo' => 1
                ]
            ], 200);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error al marcar como despachado',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
