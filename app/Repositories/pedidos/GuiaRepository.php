<?php
namespace App\Repositories\pedidos;

use App\Models\_14Empresa;
use App\Models\_14Producto;
use App\Models\_14ProductoStockHistorico;
use App\Models\DetalleVentas;
use App\Models\f_tipo_documento;
use App\Models\PedidoHistoricoActas;
use App\Models\Pedidos;
use App\Models\Pedidos_val_area_new;
use App\Models\PedidoValArea;
use App\Models\Ventas;
use App\Models\Usuario;
use App\Repositories\BaseRepository;
use App\Repositories\Codigos\CodigosRepository;
use DB;
class  GuiaRepository extends BaseRepository
{
    protected $codigoRepository;
    public function __construct(Pedidos $pedidoRepository, CodigosRepository $codigoRepository)
    {
        parent::__construct($pedidoRepository);
        $this->codigoRepository = $codigoRepository;
    }
       /**
     * Obtener la secuencia según la empresa.
     */
    public function obtenerSecuenciaxEmpresa($empresa_id)
    {
        $getSecuencia = f_tipo_documento::obtenerSecuencia("GUIA");
        if (!$getSecuencia) {
            return null;
        }
        $empresa = _14Empresa::find($empresa_id);
        if (!$empresa) {
            return null;
        }

        $secuencia = _14Empresa::obtenerSecuenciaGuia($empresa_id, $getSecuencia);


        return ['secuencia' => $secuencia, 'letra' => $empresa->letra_empresa];
    }

    /**
     * Generar el código del acta de guías.
     */
    public function generarCodigoActa($request, $secuenciaData)
    {
        $secuencia = $secuenciaData['secuencia'] + 1;
        $letra     = $secuenciaData['letra'];
        $format_id_pedido = f_tipo_documento::formatSecuencia($secuencia);
        return 'A-' . $letra . '-' . $request->codigo_contrato . '-' . $request->codigo_usuario_fact . '-' . $format_id_pedido;
    }

    public function actualizarGuia($request, $codigo_ven, $secuenciaData, $tipo)
    {
        try {
            //tipo  0 => padre; 1 => pendientes
            $secuencia      = $secuenciaData['secuencia'] + 1;
            $estado_entrega = $request->ifAprobarSinStock == '1' ? '3' : '1';
            $fechaActual    = now();

            if ($tipo == 0) {
                // Actualizar el pedido
                Pedidos::where('id_pedido', $request->id_pedido)
                    ->update([
                        'ven_codigo'                    => $codigo_ven,
                        'id_usuario_verif'              => $request->usuario_fact,
                        'fecha_aprobado_facturacion'    => $fechaActual,
                        'estado_entrega'                => $estado_entrega,
                        'empresa_id'                    => $request->empresa_id,
                    ]);
            } else {
               $this->actualizarEstadoPendientes($request->id_pedido,$request->ifnuevo,$request->empresa_id);
            }

            // Actualizar la secuencia
            f_tipo_documento::updateSecuencia("GUIA", $request->empresa_id, $secuencia);
        } catch (\Exception $e) {
            // Manejar la excepción
            throw new \Exception("Error al actualizar la guía: " . $e->getMessage());
        }
    }
    /**
     * Actualizar los datos del pedido.
     */
    public function actualizarGuiaPerseo($guias_send, $empresa_id, $id_pedido, $secuenciaData)
    {
        try {

            if (empty($guias_send)) {
                throw new \Exception("No se enviaron guías para procesar.");
            }

            $isCompleted = 1; // 1 = completado, 0 = con pendientes

            foreach ($guias_send as $guia) {

                if (!isset($guia->codigoFact) || !isset($guia->cantidad_solicitada)) {
                    throw new \Exception("Datos incompletos en la guía enviada.");
                }

                if (!is_numeric($guia->cantidad_solicitada)) {
                    throw new \Exception("La cantidad_solicitada para el producto {$guia->codigoFact} no es válido.");
                }

                $cantidadAutorizada =  DetalleVentas::join('f_venta', function($join) {
                    $join->on('f_venta.id_empresa', '=', 'f_detalle_venta.id_empresa')
                        ->on('f_venta.ven_codigo', '=', 'f_detalle_venta.ven_codigo');
                })
                ->where('f_detalle_venta.pro_codigo', $guia->codigoFact)
                ->where('f_venta.est_ven_codigo', '<>', 3)
                ->where('f_venta.id_pedido_guia', $id_pedido)
                ->sum('f_detalle_venta.det_ven_cantidad');

                if ($cantidadAutorizada <= 0) {
                    throw new \Exception("No existe detalle de venta autorizado para el producto {$guia->codigoFact}.");
                }

                $totalPedida     = (int) $guia->cantidad_solicitada;
                $totalAutorizado = (int) $cantidadAutorizada;

                // Si no coincide exactamente, queda como pendiente
                if ($totalPedida !== $totalAutorizado) {
                    $isCompleted = 0;
                }
            }

            $estadoEntrega = $isCompleted === 1 ? 1 : 3;

            // Actualizar secuencia
            $secuencia = $secuenciaData['secuencia'] + 1;
            f_tipo_documento::updateSecuencia("GUIA", $empresa_id, $secuencia);

            // Actualizar pedido (lanza excepción si no existe)
            $pedido = Pedidos::findOrFail($id_pedido);
            $pedido->estado_entrega = $estadoEntrega;
            $pedido->save();

            return true;

        } catch (\Exception $e) {
            throw new \Exception("Error al actualizar la guía: " . $e->getMessage());
        }
    }

    public function actualizarEstadoPendientes($id_pedido, $ifnuevo, $empresa_id)
    {
        // Obtener el modelo según ifnuevo
        $model = $ifnuevo == '0'
            ? PedidoValArea::where('id_pedido', $id_pedido)->get()
            : Pedidos_val_area_new::where('id_pedido', $id_pedido)->get();

        // Verificar si hay algún registro con cantidad_pendiente > 0
        $hayPendientes = $model->filter(function ($item) {
            return $item->cantidad_pendiente > 0;
        })->isNotEmpty();

        if ($hayPendientes) {
            // Si hay pendientes, no hacer nada
            return 1;
        }

        // Si no hay pendientes, actualizar el estado a 1 (aprobado)
        Pedidos::where('id_pedido', $id_pedido)
            ->update([
                'estado_entrega' => 1,
                'empresa_id'     => $empresa_id,
            ]);
        return 0;
    }


    /**
     * Actualizar los pendientes en las tablas correspondientes.
     */
    public function actualizarPendientes($detalleGuias, $ifnuevo,$tipo)
    {
        //tipo 0 => padre; 1 => pendientes
        foreach ($detalleGuias as $item) {
            $model = $ifnuevo == '0'
                ? PedidoValArea::findOrFail($item->id)
                : Pedidos_val_area_new::findOrFail($item->id);

            if($tipo == 0){
                $model->cantidad_pendiente += $item->cantidad_pendiente;
                $model->cantidad_pendiente_especifico += $item->cantidad_pendiente;
            }else{
                $model->cantidad_pendiente -= $item->cantidadAutorizar;
            }
            $model->save();

            if (!$model) {
                throw new \Exception("No se pudo actualizar en el modelo correspondiente para {$item->codigoFact}");
            }
        }
    }
    //actualizar stock
    public function actualizarStockFacturacion($arregloCodigos, $codigo_ven, $empresa_id,$tipo,$id_pedido,$user_created)
    {
        //tipo  0 => padre; 1 => pendientes
        $contador = 0;

        // Validación inicial
        if (empty($arregloCodigos) || !is_array($arregloCodigos)) {
            return ["status" => "0", "message" => "El arreglo de códigos es inválido."];
        }

        try {
            DB::beginTransaction(); // Iniciar transacción
            $arrayOldValuesStock = [];
            $arrayNewValuesStock = [];
            foreach ($arregloCodigos as $item) {
                // Validación de item
                if (!isset($item->codigo, $item->codigoFact, $item->valorNew, $item->cantidad_pendiente)) {
                    throw new \Exception("Datos incompletos en uno de los elementos del arreglo.");
                }

                $producto = _14Producto::obtenerProducto($item->codigoFact);
                if (!$producto) {
                    throw new \Exception("Producto no encontrado: {$item->codigoFact}");
                }

                $stockAnteriorReserva   = $producto->pro_reservar;
                $stockEmpresa           = ($empresa_id == 1 || $empresa_id == 5) ? $producto->pro_stock : (($empresa_id == 3 || $empresa_id == 4) ? $producto->pro_stockCalmed : 0);

                //tipo  0 => padre; 1 => pendientes
                if($tipo == 0){
                    $cantidadDescontar      = $item->valorNew - $item->cantidad_pendiente;
                    $nuevoStockReserva      = $stockAnteriorReserva - $cantidadDescontar;
                    $nuevoStockEmpresa      = $stockEmpresa - $cantidadDescontar;
                }else{
                    $cantidadDescontar      = $item->cantidadAutorizar;
                    $nuevoStockReserva      = $stockAnteriorReserva - $cantidadDescontar;
                    $nuevoStockEmpresa      = $stockEmpresa - $cantidadDescontar;
                }

                //si el nuevoStockReserva es menor a 0 no se actualiza el stock mostrar un mensaje de alerta
                if($nuevoStockReserva < 0 || $nuevoStockEmpresa < 0){
                    throw new \Exception("No hay stock suficiente para el producto {$item->codigoFact}");
                }
                // old values STOCK
                $oldValuesStock    = $this->codigoRepository->save_historicoStockOld($item->codigoFact);
                // Actualizar stock
                _14Producto::updateStock($item->codigoFact, $empresa_id, $nuevoStockReserva, $nuevoStockEmpresa);
                // new values STOCK
                $newValuesStock          = $this->codigoRepository->save_historicoStockNew($item->codigoFact);
                $arrayOldValuesStock[]   = $oldValuesStock;
                $arrayNewValuesStock[]   = $newValuesStock;
                $contador++;
            }//END FOREACH
            //======================================actualizar historico stock=======================
                // Agrupar oldValues por pro_codigo, tomando el último valor
                $groupedOldValues = $this->agruparPorCodigoPrimerValor($arrayOldValuesStock);
                $groupedNewValues = $this->agruparPorCodigo($arrayNewValuesStock);
                // Historico
                if(count($groupedOldValues) > 0){
                    _14ProductoStockHistorico::insert([
                        'psh_old_values'                        => json_encode($groupedOldValues),
                        'psh_new_values'                        => json_encode($groupedNewValues),
                        'psh_tipo'                              => 13,
                        'id_pedido'                             => $id_pedido,
                        'user_created'                          => $user_created,
                        'created_at'                            => now(),
                        'updated_at'                            => now(),
                    ]);
                }
            //=======================================================================================

            DB::commit(); // Confirmar transacción

            return ["status" => "1", "message" => "Se guardó correctamente", "procesados" => $contador];
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir transacción en caso de error
            return ["status" => "0", "message" => "Error: " . $e->getMessage()];
        }
    }

    public function agruparPorCodigo($arrayOldValues) {
        $grouped = [];

        foreach ($arrayOldValues as $item) {
            $codigo = $item['pro_codigo'];

            // Reemplazamos el registro completo con el último valor para este pro_codigo
            $grouped[$codigo] = [
                'pro_codigo' => $codigo,
                'pro_reservar' => $item['pro_reservar'],
                'pro_stock' => $item['pro_stock'],
                'pro_stockCalmed' => $item['pro_stockCalmed'],
                'pro_deposito' => $item['pro_deposito'],
                'pro_depositoCalmed' => $item['pro_depositoCalmed']
            ];
        }

        // Convertimos el array asociativo a un array indexado
        return array_values($grouped);
    }
    public function agruparPorCodigoPrimerValor($arrayOldValues) {
        $grouped = [];

        foreach ($arrayOldValues as $item) {
            $codigo = $item['pro_codigo'];

            // Solo asignamos el registro si no existe ya para este pro_codigo
            if (!isset($grouped[$codigo])) {
                $grouped[$codigo] = [
                    'pro_codigo' => $codigo,
                    'pro_reservar' => $item['pro_reservar'],
                    'pro_stock' => $item['pro_stock'],
                    'pro_stockCalmed' => $item['pro_stockCalmed'],
                    'pro_deposito' => $item['pro_deposito'],
                    'pro_depositoCalmed' => $item['pro_depositoCalmed']
                ];
            }
        }

        // Convertimos el array asociativo a un array indexado
        return array_values($grouped);
    }

    public function crearVentaPerseo($codigo_ven, $id_empresa, $id_periodo, $user_created, $idPadre,$ven_cliente,$ruc_cliente,$id_cliente_perseo, $ven_observacion, $ven_concepto_perseo)
    {
        try {
            // Verificar si ya existe un registro con el mismo ven_codigo
            $ventaExistente = Ventas::where('ven_codigo', $codigo_ven)->where('id_empresa', $id_empresa)->first();

            if ($ventaExistente) {
                return ["status" => "0", "message" => "La venta con el código ya existe."];
            }

            // Crear una nueva venta si no existe
            $venta                          = new Ventas();
            $venta->ven_codigo              = $codigo_ven;
            $venta->id_empresa              = $id_empresa;
            $venta->est_ven_codigo          = '2';
            $venta->periodo_id              = $id_periodo;
            $venta->idtipodoc               = 12;
            $venta->tip_ven_codigo          = 1;
            $venta->ven_fecha               = now();
            $venta->user_created            = $user_created;
            $venta->id_pedido_guia          = $idPadre;
            $venta->ven_cliente             = $ven_cliente; // Asignar el valor correspondiente si es necesario
            $venta->ruc_cliente             = $ruc_cliente; // Asignar el valor correspondiente si es necesario
            $venta->clientesidPerseo        = $id_cliente_perseo;
            $venta->ven_observacion         = $ven_observacion;
            $venta->ven_concepto_perseo     = $ven_concepto_perseo;
            $venta->save();
            if(!$venta){
                throw new \Exception("No se pudo guardar la venta");
            }

            return ["status" => "1", "message" => "Venta creada exitosamente."];
        } catch (\Exception $e) {
            throw new \Exception("Error al crear la venta: " . $e->getMessage());
        }
    }

    public function crearDetalleVentaPerseo($guias_send, $codigo_ven, $id_empresa)
    {
        try {
            foreach ($guias_send as $guia) {
                // Verificar si ya existe un registro con el mismo ven_codigo
                $pro_CodigoExists = DetalleVentas::where('ven_codigo', $codigo_ven)->where('pro_codigo', $guia->codigoFact)->where('id_empresa', $id_empresa)->first();
                if (!$pro_CodigoExists) {
                    //NO HAGO NADA
                }
                $detalleVenta                          = new DetalleVentas();
                $detalleVenta->ven_codigo              = $codigo_ven;
                $detalleVenta->id_empresa              = $id_empresa;
                $detalleVenta->pro_codigo              = $guia->codigoFact;
                $detalleVenta->det_ven_cantidad        = $guia->cantidadAutorizar;
                $detalleVenta->det_ven_valor_u         = 1; // Por defecto las guias valdran 1 dolar
                $detalleVenta->save();
                if(!$detalleVenta){
                    throw new \Exception("No se pudo guardar el detalle de venta");
                }
            }
            return ["status" => "1", "message" => "Detalle de venta creada exitosamente."];
        } catch (\Exception $e) {
            throw new \Exception("Error al crear los detalles de venta: " . $e->getMessage());
        }
    }

     public function crearVenta($codigo_ven, $id_empresa, $id_periodo, $user_created, $idPadre)
    {
        try {
            // Verificar si ya existe un registro con el mismo ven_codigo
            $ventaExistente = Ventas::where('ven_codigo', $codigo_ven)->where('id_empresa', $id_empresa)->first();

            if ($ventaExistente) {
                return ["status" => "0", "message" => "La venta con el código ya existe."];
            }

            // Crear una nueva venta si no existe
            $venta                          = new Ventas();
            $venta->ven_codigo              = $codigo_ven;
            $venta->id_empresa              = $id_empresa;
            $venta->est_ven_codigo          = '1';
            $venta->periodo_id              = $id_periodo;
            $venta->idtipodoc               = 12;
            $venta->tip_ven_codigo          = 1;
            $venta->ven_fecha               = now();
            $venta->user_created            = $user_created;
            $venta->id_pedido               = $idPadre;
            $venta->save();
            if(!$venta){
                throw new \Exception("No se pudo guardar la venta");
            }

            return ["status" => "1", "message" => "Venta creada exitosamente."];
        } catch (\Exception $e) {
            throw new \Exception("Error al crear la venta: " . $e->getMessage());
        }
    }

    public function crearDetalleVenta($guias_send, $codigo_ven, $id_empresa, $id_periodo, $user_created, $idPadre)
    {
        try {
            foreach ($guias_send as $guia) {
                // Verificar si ya existe un registro con el mismo ven_codigo
                $pro_CodigoExists = DetalleVentas::where('ven_codigo', $codigo_ven)->where('pro_codigo', $guia->codigoFact)->where('id_empresa', $id_empresa)->first();
                if (!$pro_CodigoExists) {
                    //NO HAGO NADA
                }
                $detalleVenta                          = new DetalleVentas();
                $detalleVenta->ven_codigo              = $codigo_ven;
                $detalleVenta->id_empresa              = $id_empresa;
                $detalleVenta->pro_codigo              = $guia->codigoFact;
                $detalleVenta->det_ven_cantidad        = $guia->cantidadAutorizar;
                $detalleVenta->save();
                if(!$detalleVenta){
                    throw new \Exception("No se pudo guardar el detalle de venta");
                }
            }
            return ["status" => "1", "message" => "Detalle de venta creada exitosamente."];
        } catch (\Exception $e) {
            throw new \Exception("Error al crear los detalles de venta: " . $e->getMessage());
        }
    }
    public function getPendientes($id_pedido){
        $query = DB::SELECT("
            SELECT
                SUBSTRING(v2.pro_codigo, 2) AS pro_codigo,
                SUM(v2.det_ven_cantidad) AS cantidad
            FROM f_venta v
            LEFT JOIN f_detalle_venta v2
                ON v2.ven_codigo = v.ven_codigo
                AND v2.id_empresa = v.id_empresa
            WHERE v.id_pedido = ?
            GROUP BY SUBSTRING(v2.pro_codigo, 2)
        ", [$id_pedido]);

        return $query;
    }

    public function crearUsuario($cedula, $email, $nombres, $apellidos){
        try {
            $usuario                              = new Usuario();
            $usuario->cedula                      = $cedula;
            $usuario->email                       = $email;
            $usuario->nombres                     = $nombres;
            $usuario->apellidos                   = $apellidos;
            $usuario->id_group                    = 33;
            $usuario->password                    = sha1(md5($cedula));
            $usuario->estado_idEstado             = 1;
            $usuario->institucion_idInstitucion   = 66;
            $usuario->save();
            if (!$usuario->idusuario) {
                throw new \Exception("No se pudo guardar el usuario");
            }
            return $usuario;
        } catch (\Exception $e) {
            throw new \Exception("Error al crear el usuario: " . $e->getMessage());
        }
    }

    /**
     * Actualiza ven_valor, ven_subtotal, ven_desc_por y ven_descuento en f_venta
     * sumando el detalle de venta ya guardado (det_ven_cantidad × det_ven_valor_u).
     *
     * @param string $codigo_ven      Código de la venta (ven_codigo)
     * @param int    $id_empresa      ID de la empresa
     * @param float  $porcentaje_desc Porcentaje de descuento (0–100). Por defecto 0.
     */
    public function actualizarTotalesVenta($codigo_ven, $id_empresa, $porcentaje_desc = 0)
    {
        try {
            // Sumar det_ven_cantidad * det_ven_valor_u del detalle de esta venta
            $ven_valor = DetalleVentas::where('ven_codigo', $codigo_ven)
                ->where('id_empresa', $id_empresa)
                ->sum(\DB::raw('det_ven_cantidad * det_ven_valor_u'));

            $ven_desc_por   = (float) $porcentaje_desc;
            $ven_descuento  = round($ven_valor * $ven_desc_por / 100, 2);
            $ven_subtotal   = round($ven_valor - $ven_descuento, 2);

            Ventas::where('ven_codigo', $codigo_ven)
                ->where('id_empresa', $id_empresa)
                ->update([
                    'ven_valor'     => $ven_valor,
                    'ven_subtotal'  => $ven_subtotal,
                    'ven_desc_por'  => $ven_desc_por,
                    'ven_descuento' => $ven_descuento,
                ]);

            return [
                'status'        => '1',
                'ven_valor'     => $ven_valor,
                'ven_subtotal'  => $ven_subtotal,
                'ven_desc_por'  => $ven_desc_por,
                'ven_descuento' => $ven_descuento,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Error al actualizar totales de la venta: " . $e->getMessage());
        }
    }

}
