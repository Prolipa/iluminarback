<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\_14Producto;
use App\Models\_14ProductoStockHistorico;
use App\Models\CodigosLibros;
use App\Models\f_tipo_documento;
use App\Models\LibroSerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PedidoGuiaDevolucion;
use App\Models\PedidoGuiaDevolucionDetalle;
use App\Models\PedidoHistoricoActas;
use App\Models\PedidoGuiaTemp;
use App\Models\Pedidos;
use App\Models\Pedidos_val_area_new;
use App\Models\PedidosGuiasBodega;
use App\Models\PedidoValArea;
use App\Models\DetalleVentas;
use App\Models\Usuario;
use App\Models\Ventas;
use App\Repositories\Codigos\CodigosRepository;
use App\Repositories\pedidos\GuiaRepository;
use App\Traits\Codigos\TraitCodigosGeneral;
use App\Traits\Pedidos\TraitGuiasGeneral;
use App\Traits\Pedidos\TraitPedidosGeneral;
use Exception;
use Illuminate\Support\Facades\Http;
class GuiasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    use TraitGuiasGeneral;
    use TraitPedidosGeneral;
    use TraitCodigosGeneral;
    protected $guiaRepository;
    protected $codigoRepository;
    public function __construct(GuiaRepository $guiaRepository, CodigosRepository $codigoRepository)
    {
        $this->guiaRepository = $guiaRepository;
        $this->codigoRepository = $codigoRepository;
    }
    //API:get/guias
    public function index(Request $request)
    {
        //listado de guias para devolver
        if($request->listadoGuias){
            return $this->listadoGuias();
        }
        //validar que no solo haya devolucion abierta
        if($request->validarGenerar){
            return $this->validarGenerar($request->asesor_id);
        }
        //listado de devoluciones
        if($request->devolucion){
            return $this->getDevolucionesBodega($request->asesor_id,$request->periodo_id);
        }//listado de guias devueltas
        if($request->detalle){
            return $this->getDetalle($request->id);
        }
        //ver stock guias
        if($request->verStock){
            return $this->verStock($request->id_pedido,$request->empresa);
        }
        //ver stock guias NEW
        if($request->verStock_new){
            return $this->verStock_new($request->id_pedido,$request->empresa);
        }
        //stock de las
        if($request->verStockGuiasProlipa){
            return $this->verStockGuiasProlipa($request->id_pedido,$request->acta);
        }
        //stock de las new
        if($request->verStockGuiasProlipa_new){
            return $this->verStockGuiasProlipa_new($request->id_pedido,$request->acta);
        }
        //dashboard guias bodega
        if($request->datosGuias){
            return $this->datosGuias();
        }
        //api:get/guias?listadoGuiasXEstado=1&estado_entrega=
        //listado por tipo de entrega
        if($request->InformacionPedidoGuia){ return $this->InformacionPedidoGuia($request->id_pedido); }
        if($request->listadoGuiasXEstado) { return $this->listadoGuiasXEstado($request->estado_entrega); }
        if($request->listadoDocumentoVentaGuias) { return $this->listadoDocumentoVentaGuias($request->id_pedido); }
        if($request->CargarDocumentosDetallesGuias) { return $this->CargarDocumentosDetallesGuias($request); }
    }
    public function verStock($id_pedido,$empresa){
        try {
            //consultar el stock
            $arregloCodigos     = $this->get_val_pedidoInfo($id_pedido);
            $contador = 0;
            $form_data_stock = [];
            foreach($arregloCodigos as $key => $item){
                $stockAnterior      = 0;
                $id                 = $arregloCodigos[$contador]["id"];
                $codigo             = $arregloCodigos[$contador]["codigo_liquidacion"];
                $codigoFact         = "G".$codigo;
                $nombrelibro        = $arregloCodigos[$contador]["nombrelibro"];
                $cantidad_pendiente = $arregloCodigos[$contador]["cantidad_pendiente"];
                $valor              = $arregloCodigos[$contador]["valor"];
                //get stock
                $getStock           = _14Producto::obtenerProducto($codigoFact);
                //prolipa
                if($empresa == 1 || $empresa == 5){
                    $stockAnterior  = $getStock->pro_stock;
                }
                //calmed
                if($empresa == 3 || $empresa == 4){
                    $stockAnterior  = $getStock->pro_stockCalmed;
                }
                $valorNew           = $arregloCodigos[$contador]["valor"];
                $nuevoStock         = ($stockAnterior < 0 ? 0 : $stockAnterior) - $valorNew;
                $form_data_stock[$contador] = [
                "id"                => $id,
                "nombrelibro"       => $nombrelibro,
                "stockAnterior"     => $stockAnterior,
                "valorNew"          => $valorNew,
                "nuevoStock"        => $nuevoStock,
                "codigoFact"        => $codigoFact,
                "codigo"            => $codigo,
                "cantidad_pendiente" => $cantidad_pendiente,
                "valor"             => $valor,
                ];
                $contador++;
            }
            return $form_data_stock;
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor". $ex->getMessage()];
        }
    }
    //PARA VER EL STOCK INGRESADO DE LA ACTA
    public function verStockGuiasProlipa($id_pedido,$acta){
        try {
            //consultar el stock
            $arregloCodigos = $this->get_val_pedidoInfo($id_pedido);
            $contador = 0;
            $form_data_stock = [];
            $contador = 0;
            foreach($arregloCodigos as $key => $item){
                //variables
                $codigo         = $arregloCodigos[$contador]["codigo_liquidacion"];
                $nombrelibro    = $arregloCodigos[$contador]["nombrelibro"];
                $valorNew       = $arregloCodigos[$contador]["valor"];
                //consulta
                $query = DB::SELECT("SELECT * FROM pedidos_historico_actas pa
                WHERE pa.ven_codigo = '$acta'
                AND pa.pro_codigo = '$codigo'
                LIMIT 1
                ");
                if(empty($query)){
                    $form_data_stock[$contador] = [
                    "nombrelibro"    => $nombrelibro,
                    "stockAnterior"  => "",
                    "valorNew"       => $valorNew,
                    "nuevoStock"     => "",
                    "codigo"         => $codigo
                    ];
                }else{
                    $stockAnterior  = $query[0]->stock_anterior;
                    $nuevo_stock    = $query[0]->nuevo_stock;
                    $form_data_stock[$contador] = [
                    "nombrelibro"    => $nombrelibro,
                    "stockAnterior"  => $stockAnterior,
                    "valorNew"       => $valorNew,
                    "nuevoStock"     => $nuevo_stock,
                    "codigo"         => $codigo
                    ];
                }
                $contador++;
            }
            return $form_data_stock;
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }
    }
    //guias?datosGuias=yes
    public function datosGuias(){
        $guiasSinDespachar      = 0;
        $guiasDespachadas       = 0;
        $devueltasPendientes    = 0;
        $devueltasAprobadas     = 0;
        $query = Pedidos::Where("tipo","1")->where('estado','1')->select('id_pedido','estado_entrega')->get();
        //guias
        if(count($query) > 0){ $query->map(function($item) use (&$guiasSinDespachar,&$guiasDespachadas){ if($item->estado_entrega == 1){ $guiasSinDespachar++; } if($item->estado_entrega == 2){ $guiasDespachadas++; } }); }
        //devoluciones de guias
        $query2 = PedidoGuiaDevolucion::all();
        if(count($query2) > 0){
            $query2->map(function($item2) use (&$devueltasPendientes,&$devueltasAprobadas) {
                if($item2->estado == 0){ $devueltasPendientes++; }
                if($item2->estado == 1){ $devueltasAprobadas++; }
            });
         }
        return [ "guiasSinDespachar" => $guiasSinDespachar, "guiasDespachadas" => $guiasDespachadas, "devueltasPendientes" => $devueltasPendientes,"devueltasAprobadas" => $devueltasAprobadas ];
    }
    //api:get/>>guias?listadoGuiasXEstado=1&estado_entrega=2
    public function listadoGuiasXEstado($estado_entrega){
        $query = $this->tr_guiasXEstado($estado_entrega);
        //coleccion
        $coleccion = collect($query);
        //ordenar por fecha_entrega_bodega asc
        $coleccion = $coleccion->sortBy('fecha_entrega_bodega');
        return $coleccion->values();
    }

    //api:get/guias?listadoDocumentoVentaGuias=1&id_pedido=2750
    public function listadoDocumentoVentaGuias($id_pedido){
    $query = DB::SELECT("SELECT
        v.*,
        e.descripcion_corta AS empresa_descripcion,
        pe.periodoescolar,
        CONCAT(u.nombres,' ', u.apellidos) AS asesor,
        CONCAT(cli.nombres,' ', cli.apellidos) AS cliente_nombre,
        CONCAT(fact.nombres,' ', fact.apellidos) AS facturador_nombre,
        CONCAT(user_anul.nombres,' ', user_anul.apellidos) AS anulador_nombre,
        CONCAT(user_despacho.nombres,' ', user_despacho.apellidos) AS despachador_nombre,

        COALESCE(d.total_items, 0) AS total_items,
        COALESCE(d.total_libros, 0) AS total_libros

        FROM f_venta v

        LEFT JOIN empresas e
            ON e.id = v.id_empresa

        LEFT JOIN periodoescolar pe
            ON pe.idperiodoescolar = v.periodo_id

        LEFT JOIN pedidos p
            ON p.id_pedido = v.id_pedido_guia

        LEFT JOIN usuario u
            ON u.idusuario = p.id_asesor

        LEFT JOIN usuario cli
            ON cli.idusuario = v.ven_cliente

        LEFT JOIN usuario fact
            ON fact.idusuario = v.user_created

        LEFT JOIN usuario user_anul
            ON user_anul.idusuario = v.user_anulado

        LEFT JOIN usuario user_despacho
            ON user_despacho.idusuario = v.user_despacha

        -- 🔥 AQUÍ ESTÁ LA MAGIA
        LEFT JOIN (
            SELECT
                ven_codigo,
                id_empresa,
                COUNT(*) AS total_items,
                SUM(det_ven_cantidad) AS total_libros
            FROM f_detalle_venta
            GROUP BY ven_codigo, id_empresa
        ) d
            ON d.ven_codigo = v.ven_codigo
            AND d.id_empresa = v.id_empresa

        WHERE v.id_pedido_guia = '$id_pedido';
        ");
        return $query;
    }

    //api:get/>guias?CargarDocumentosDetallesGuias=1&ven_codigo=A-C-S26-ER-0055079&id_empresa=3
    public function CargarDocumentosDetallesGuias($request){
        $query = DB::SELECT("SELECT
        dv.*,
        p.pro_nombre AS nombre_producto, p.pro_descripcion
        FROM f_detalle_venta dv
        LEFT JOIN 1_4_cal_producto p
            ON p.pro_codigo = dv.pro_codigo
        WHERE dv.ven_codigo = '$request->ven_codigo';
        ");
        return $query;
    }


    public function listadoGuias(){
        $query = DB::SELECT("SELECT pd.*,
        CONCAT(u.nombres,' ',u.apellidos) as asesor,
        u.cedula,
        (
            SELECT SUM(pg.cantidad_devuelta) AS cantidad
            FROM pedidos_guias_devolucion_detalle  pg
            WHERE pg.pedidos_guias_devolucion_id = pd.id

        ) as cantidad_devolver, pe.codigo_contrato,pe.region_idregion,u.iniciales,
        pe.pedido_facturacion, pe.pedido_bodega, pe.pedido_asesor,pe.periodoescolar as periodo
        FROM pedidos_guias_devolucion pd
        LEFT JOIN usuario u ON pd.asesor_id = u.idusuario
        LEFT JOIN periodoescolar pe ON pd.periodo_id = pe.idperiodoescolar

        ORDER BY pd.id DESC
        ");
        return $query;
    }
    public function validarGenerar($asesor_id){
        $query = DB::SELECT("SELECT * FROM pedidos_guias_devolucion p
        WHERE p.asesor_id = '$asesor_id'
        AND p.estado = '0'
        ");
        if(count($query) > 0){
            return ["status" => "0", "message" => "Existe una devolución abierta en algun periodo"];
        }
    }
    public function getDevolucionesBodega($asesor_id,$periodo_id){
        $query = DB::SELECT("SELECT pd.*,
        CONCAT(u.nombres,' ',u.apellidos) as asesor,
        (
            SELECT SUM(pg.cantidad_devuelta + pg.cantidad_devuelta_codigoslibros) AS cantidad
            FROM pedidos_guias_devolucion_detalle  pg
            WHERE pg.pedidos_guias_devolucion_id = pd.id

        ) as cantidad_devolver
        FROM pedidos_guias_devolucion pd
        LEFT JOIN usuario u ON pd.asesor_id = u.idusuario
        WHERE pd.asesor_id = '$asesor_id'
        AND periodo_id = '$periodo_id'
        ORDER BY pd.id DESC
        ");
        return $query;
    }
    public function getDetalle($id){
        $query = DB::SELECT("SELECT  pg.* , pr.pro_nombre as nombrelibro
        FROM pedidos_guias_devolucion_detalle pg
        LEFT JOIN 1_4_cal_producto pr ON pg.pro_codigo = pr.pro_codigo
        WHERE pg.pedidos_guias_devolucion_id = '$id'
        ORDER BY pr.pro_nombre
        ");
        return $query;
    }
    //api:post//guardarDevolucionBDMilton
    public function guardarDevolucionBDMilton(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        try {
            //transaccion
            DB::beginTransaction();
            //variables
            $id_pedido                      = $request->id_pedido;
            $iniciales                      = $request->iniciales;
            $fechaActual                    = date("Y-m-d H:i:s");
            $empresa_id                     = $request->empresa_id;
            $periodo_id                     = $request->periodo_id;
            $user_created                   = $request->user_created;
            $secuencia                      = 0;
            //obtener el id de la institucion de facturacion
            $query = DB::SELECT("SELECT * FROM usuario s
            WHERE s.iniciales = '$request->iniciales'
            ");
            if(empty($query)){
                return ["status" => "0", "message" => "No esta configurado las iniciales del asesor"];
            }
            $asesor_id                      = $query[0]->idusuario;
            $letra                          = "";
            //get secuencia
            $getSecuencia                   = f_tipo_documento::obtenerSecuencia("DEVOLUCION-GUIA");
            if(!$getSecuencia)              { return ["status" => "0", "message" => "No se pudo obtener la secuencia de guias"]; }
            //prolipa
            if($empresa_id == 1)            { $secuencia = $getSecuencia->tdo_secuencial_Prolipa; $letra = "P"; }
            //calmed
            if($empresa_id == 3)            { $secuencia = $getSecuencia->tdo_secuencial_calmed; $letra = "C"; }
            //VARIABLES
            $secuencia                      = $secuencia + 1;
            //format secuencia
            $format_id_pedido               = f_tipo_documento::formatSecuencia($secuencia);
            //codigo de devolucion de guia
            $codigo_ven = 'NCI-'.$letra.'-'.$iniciales . '-'. $format_id_pedido;
            //================SAVE PEDIDO======================
            //================SAVE DETALLE DE LAS GUIAS======================
            //obtener las guias por libros
            $detalleGuias = $this->getDetalle($id_pedido);
            //Si no hay nada en detalle de venta
            if(empty($detalleGuias)){ return ["status" => "0", "message" => "No hay ningun libro para el detalle de las guias a devolver"];}
            //===ACTUALIZAR STOCK========
            $this->actualizarStockFacturacionDevolucion($detalleGuias,$codigo_ven,$empresa_id,$asesor_id,$periodo_id,$user_created,$id_pedido);
            //ACTUALIZAR VEN CODIGO - FECHA APROBACION-
            $query = "UPDATE `pedidos_guias_devolucion` SET `ven_codigo` = '$codigo_ven', `fecha_aprobacion` = '$fechaActual', `estado` = '1', `empresa_id` = '$empresa_id', `user_devuelve` = '$user_created' WHERE `id` = $id_pedido;";
            DB::UPDATE($query);
            //ACTUALIZAR LA SECUENCIA
            f_tipo_documento::updateSecuencia("DEVOLUCION-GUIA",$empresa_id,$secuencia);
            //COMMIT
            DB::commit();
            return response()->json(['status' => '1', 'message' => 'Guías guardadas correctamente'], 200);
         } catch (\Exception  $ex) {
            //ROLLBACK
            DB::rollBack();
            return ["status" => "0","message" => "Hubo problemas al devolver las guias".$ex];
        }

    }
    //actualizar stock
    public function actualizarStockFacturacionDevolucion(
        $arregloCodigos,
        $codigo_ven,
        $empresa_id,
        $asesor_id,
        $periodo_id,
        $user_created,
        $id_pedido
    )
    {
        $arrayOldValuesStock = [];
        $arrayNewValuesStock = [];
        foreach ($arregloCodigos as $item) {
            // Datos iniciales
            $codigoFact = $item->pro_codigo;
            $producto   = _14Producto::obtenerProducto($codigoFact);

            if (!$producto) {
                throw new \Exception("Producto no encontrado para código: " . $codigoFact);
            }

            // Stock reserva antes
            $stockAnteriorReserva = $producto->pro_reservar;

            // Stock por empresa
            if ($empresa_id == 1) {
                $stockEmpresa = $producto->pro_stock;
            } elseif ($empresa_id == 3) {
                $stockEmpresa = $producto->pro_stockCalmed;
            } else {
                $stockEmpresa = 0;
            }

            // Cálculo total devuelto
            $valorNew = $item->cantidad_devuelta + $item->cantidad_devuelta_codigoslibros;
            $cantidad_devuelta_codigoslibros = $item->cantidad_devuelta_codigoslibros;

            /**
             * --------------------------------------------
             *  PROCESAMIENTO DE GUIAS codigoslibros
             * --------------------------------------------
             */
            if ($cantidad_devuelta_codigoslibros > 0) {
                // Código sin la "G"
                $getCodigoLibro = substr($item->pro_codigo, 1);

                $getDatoLibro = LibroSerie::where('codigo_liquidacion', $getCodigoLibro)->first();

                if (!$getDatoLibro) {
                    throw new \Exception(
                        "No se encontró libro con código de liquidación: " . $getCodigoLibro
                    );
                }

                $idLibro = $getDatoLibro->idLibro;
                // return $idLibro;
                // Obtener códigos que el asesor tiene asignados
                $getCodigoslibros = DB::select("
                    SELECT * FROM codigoslibros c
                    WHERE c.estado_liquidacion = '4'
                    AND c.prueba_diagnostica = '0'
                    AND c.asesor_id = ?
                    AND c.bc_periodo = ?
                    AND c.libro_idlibro = ?
                    LIMIT $cantidad_devuelta_codigoslibros
                ", [
                    $asesor_id,
                    $periodo_id,
                    $idLibro
                ]);

                if (count($getCodigoslibros) != $cantidad_devuelta_codigoslibros) {
                    throw new \Exception(
                        "La cantidad de guías no coincide con lo que se debe devolver para el código: " .
                        $getCodigoLibro
                    );
                }

                /**
                 * --------------------------------------------
                 *  ACTUALIZAR CODIGOS A ESTADO NORMAL
                 * --------------------------------------------
                 */
                foreach ($getCodigoslibros as $itemCodigo) {

                    $comentario = "Devolución de guías por parte del asesor";

                    // Obtener registro
                    $codigo = CodigosLibros::find($itemCodigo->codigo);
                    if (!$codigo) {
                        throw new \Exception("Código no encontrado: " . $itemCodigo->codigo);
                    }

                    $old_valuesCodigo = json_encode($codigo->getAttributes());

                    // Limpieza del código
                    DB::table('codigoslibros')
                        ->where('codigo', $itemCodigo->codigo)
                        ->update([
                            'estado_liquidacion'      => '1',
                            'asesor_id'               => null,
                            'bc_periodo'              => null,
                            'bc_institucion'          => null,
                            'venta_lista_institucion' => null,
                            'codigo_paquete'          => null,
                            'combo'                   => null,
                            'codigo_combo'            => null,
                            'codigo_proforma'         => null,
                            'proforma_empresa'        => null,
                            'plus'                    => 0,
                            'factura'                 => null,
                            'bc_estado'               => '1',
                            'venta_estado'            => '0',
                        ]);

                    // Guardar histórico
                    $this->GuardarEnHistorico(
                        0,
                        0,
                        $periodo_id,
                        $itemCodigo->codigo,
                        $user_created,
                        $comentario,
                        $old_valuesCodigo,
                        json_encode($codigo->fresh()->getAttributes()),
                        null,
                        null
                    );

                    /**
                     * --------------------------------------------
                     *  SI EL CODIGO TIENE UNION, ACTUALIZAR TODOS
                     * --------------------------------------------
                     */
                    $codigo_union = $codigo->codigo_union;

                    if ($codigo_union) {

                        $cu = CodigosLibros::where('codigo', $codigo_union)->first();

                        if ($cu) {

                            $old_values = json_encode($cu->getAttributes());

                            DB::table('codigoslibros')
                                ->where('codigo', $cu->codigo)
                                ->update([
                                    'estado_liquidacion'      => '1',
                                    'asesor_id'               => null,
                                    'bc_periodo'              => null,
                                    'bc_institucion'          => null,
                                    'venta_lista_institucion' => null,
                                    'codigo_paquete'          => null,
                                    'combo'                   => null,
                                    'codigo_combo'            => null,
                                    'codigo_proforma'         => null,
                                    'proforma_empresa'        => null,
                                    'plus'                    => 0,
                                    'factura'                 => null,
                                    'bc_estado'               => '1',
                                    'venta_estado'            => '0',
                                ]);

                            $this->GuardarEnHistorico(
                                0,
                                0,
                                $periodo_id,
                                $cu->codigo,
                                $user_created,
                                $comentario,
                                $old_values,
                                json_encode($cu->fresh()->getAttributes()),
                                null,
                                null
                            );
                        }
                    }

                }
            }
            //====== FIN CODIGOSLIBROS
            /**
             * --------------------------------------------
             *  ACTUALIZAR STOCK
             * --------------------------------------------
             */
            // old values STOCK
            $oldValuesStock    = $this->codigoRepository->save_historicoStockOld($codigoFact);
            $nuevoStockReserva = $stockAnteriorReserva + $valorNew;
            $nuevoStockEmpresa = $stockEmpresa + $valorNew;

            _14Producto::updateStock(
                $codigoFact,
                $empresa_id,
                $nuevoStockReserva,
                $nuevoStockEmpresa
            );
            // new values STOCK
            $newValuesStock          = $this->codigoRepository->save_historicoStockNew($codigoFact);
            $arrayOldValuesStock[]   = $oldValuesStock;
            $arrayNewValuesStock[]   = $newValuesStock;
        }// end foreach
        //======================================actualizar historico stock=======================
            // Agrupar oldValues por pro_codigo, tomando el último valor
            $groupedOldValues = $this->guiaRepository->agruparPorCodigoPrimerValor($arrayOldValuesStock);
            $groupedNewValues = $this->guiaRepository->agruparPorCodigo($arrayNewValuesStock);
            // Historico
            if(count($groupedOldValues) > 0){
                _14ProductoStockHistorico::insert([
                    'psh_old_values'                        => json_encode($groupedOldValues),
                    'psh_new_values'                        => json_encode($groupedNewValues),
                    'psh_tipo'                              => 14,
                    'id_pedidos_guias_devolucion'           => $id_pedido,
                    'user_created'                          => $user_created,
                    'created_at'                            => now(),
                    'updated_at'                            => now(),
                ]);
            }
        //=======================================================================================
        // Retorno final
        return ["status" => "1", "message" => "Stock actualizado correctamente", "codigo_ven" => $codigo_ven];
    }

        /**
     * Agrupa arrayOldValues por pro_codigo, tomando el último valor de cada campo
     * @param array $arrayOldValues
     * @return array
     */

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
    //api:post:/guias
    public function store(Request $request)
    {
       if($request->GuiasGenerarDocumentoVenta) { return $this->GuiasGenerarDocumentoVenta($request); }
       if($request->guardarGuiasPendientes)     { return $this->guardarGuiasPendientes($request); }
       if($request->anularPedidoAprobado)       { return $this->anularPedidoAprobado($request); }
       if($request->enviarAPerseo)             { return $this->enviarAPerseo($request); }
       if($request->anularDocumentoVenta)       { return $this->anularDocumentoVenta($request); }
    }

    //api:post/guias?GuiasGenerarDocumentoVenta=1
    public function GuiasGenerarDocumentoVenta(Request $request){
        set_time_limit(600);
        ini_set('max_execution_time', 600);

        DB::beginTransaction();
        try {
            $request->validate([
                'empresa_id'            => 'required|integer',
                'id_pedido'             => 'required|integer',
                'id_periodo'            => 'required|integer',
                'codigo_contrato'       => 'required|string',
                'guias_send'            => 'required|json',
                'usuario_fact'          => 'required|integer',
                'codigo_usuario_fact'   => 'required|string',
                'id_cliente_perseo'     => 'required|integer',
                'cliente_cedula'        => 'required|string',
                'email_cliente'         => 'required|string',
                'cliente_nombre'        => 'required|string',
                'ven_observacion'       => 'nullable|string',
                'ven_concepto_perseo'   => 'nullable|string',
            ]);

            $ven_cliente = 0;
            $porcentaje_descuento = $request->porcentaje_descuento ?? 0;

            // Obtener usuario
            $getVenCliente = Usuario::where('cedula', $request->cliente_cedula)->first();

            if (!$getVenCliente) {

                $partes     = explode(' ', trim($request->cliente_nombre));
                $apellidos  = implode(' ', array_slice($partes, 0, 2));
                $nombres    = implode(' ', array_slice($partes, 2)) ?: $apellidos;

                $usuarioNuevo = $this->guiaRepository->crearUsuario(
                    $request->cliente_cedula,
                    $request->email_cliente,
                    $nombres,
                    $apellidos
                );

                $ven_cliente = $usuarioNuevo->idusuario;

            } else {
                $ven_cliente = $getVenCliente->idusuario;
            }

            $guias_send = json_decode($request->guias_send);

            if (empty($guias_send)) {
                throw new \Exception("No hay ningún registro para generar el documento de venta");
            }

            $pedido = Pedidos::where('id_pedido', $request->id_pedido)->first();

            if (!$pedido) {
                throw new \Exception("Pedido no encontrado");
            }

            if ($pedido->estado_entrega == '2') {
                throw new \Exception("El pedido ya se encuentra aprobado");
            }

            // Secuencia
            $secuenciaData = $this->guiaRepository->obtenerSecuenciaxEmpresa($request->empresa_id);

            if (!$secuenciaData) {
                throw new \Exception("No se pudo obtener la secuencia");
            }

            $codigo_ven = $this->guiaRepository->generarCodigoActa($request, $secuenciaData);

            // Crear venta
            $this->guiaRepository->crearVentaPerseo(
                $codigo_ven,
                $request->empresa_id,
                $request->id_periodo,
                $request->usuario_fact,
                $request->id_pedido,
                $ven_cliente,
                $request->cliente_cedula,
                $request->id_cliente_perseo,
                $request->ven_observacion,
                $request->ven_concepto_perseo
            );

            // Crear detalle
            $this->guiaRepository->crearDetalleVentaPerseo(
                $guias_send,
                $codigo_ven,
                $request->empresa_id,
            );

            // Totales
            $this->guiaRepository->actualizarTotalesVenta(
                $codigo_ven,
                $request->empresa_id,
                $porcentaje_descuento
            );

            // Actualizar pedido
            $this->guiaRepository->actualizarGuiaPerseo(
                $guias_send,
                $request->empresa_id,
                $request->id_pedido,
                $secuenciaData
            );

            DB::commit();

            return [
                "status" => "1",
                "message" => "Documento creado exitosamente"
            ];

        } catch (\Exception $ex) {

            DB::rollBack();

            return [
                "status" => "0",
                "message" => "Hubo problemas: " . $ex->getMessage()
            ];
        }
    }

    //api:post/guias?guardarGuiasPendientes=1
    public function guardarGuiasPendientes(Request $request)
    {
        set_time_limit(600);
        ini_set('max_execution_time', 600);

        $request->validate([
            'asesor_id'             => 'required|integer',
            'id_pedido'             => 'required|integer',
            'id_periodo'            => 'required|integer',
            'codigo_contrato'       => 'required|string',
            'codigo_usuario_fact'   => 'required|string',
            'usuario_fact'          => 'required|integer',
            'iniciales'             => 'required|string',
            'empresa_id'            => 'required|integer',
            'guias_send'            => 'required|json',
            'ifnuevo'               => 'required|boolean',
            'ifAprobarSinStock'     => 'required|boolean',
            'ifAprobarPendientes'   => 'required|boolean',
            'ven_codigoPadre'       => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Variables iniciales
            $guias_send = json_decode($request->guias_send);
            if (empty($guias_send)) {
                throw new \Exception("No hay ningún libro para el detalle de venta");
            }

            //si el pedido se encuentra en estado_entrega 2 entonces ya no se puede agregar pendientes porque ya esta aprobado
            $pedido = Pedidos::where('id_pedido', $request->id_pedido)->first();
            if ($pedido->estado_entrega == '2') {
                throw new \Exception("El pedido de guias ya se encuentra aprobado y no se puede agregar pendientes");
            }

            // Validar que haya pendientes
            $validatePendientes = $this->guiaRepository->actualizarEstadoPendientes(
                $request->id_pedido,
                $request->ifnuevo,
                $request->empresa_id
            );

            if ($validatePendientes == '0') {
                throw new \Exception("Ya no hay pendientes para crear el acta de guías");
            }

            // Manejo de secuencia
            $secuenciaData = $this->guiaRepository->obtenerSecuenciaxEmpresa($request->empresa_id);
            if (!$secuenciaData) {
                throw new \Exception("No se pudo obtener la secuencia de guías");
            }

            $codigo_ven = $this->guiaRepository->generarCodigoActa($request, $secuenciaData);

            // Crear f_venta
            $this->guiaRepository->crearVenta(
                $codigo_ven,
                $request->empresa_id,
                $request->id_periodo,
                $request->usuario_fact,
                $request->id_pedido
            );

            // Crear f_detalle_venta
            $this->guiaRepository->crearDetalleVenta(
                $guias_send,
                $codigo_ven,
                $request->empresa_id,
                $request->id_periodo,
                $request->usuario_fact,
                $request->id_pedido
            );

            // Actualizar stock
            $resultado = $this->guiaRepository->actualizarStockFacturacion($guias_send, $codigo_ven, $request->empresa_id, 1, $request->id_pedido, $request->usuario_fact);
            if (isset($resultado["status"]) && $resultado["status"] == "0") {
                throw new \Exception($resultado["message"]);
            }
            // Actualizar pendientes si es necesario
            $this->guiaRepository->actualizarPendientes($guias_send, $request->ifnuevo,1);

            // Actualizar pedido
            $this->guiaRepository->actualizarGuia($request, $codigo_ven, $secuenciaData, 1);

            // Confirmar transacción
            DB::commit();

            return response()->json(['status' => '1', 'message' => 'Guías guardadas correctamente'], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(["status" => "0", "message" => "Error: " . $ex->getMessage()], 200);
        }
    }
    //api:post/guias?anularPedidoAprobado=1

    public function anularPedidoAprobado(Request $request){
        try {
            DB::beginTransaction();

            $id_pedido          = $request->id_pedido;
            $ifnuevo            = $request->ifnuevo;
            $user_created       = $request->user_created;
            $message_anulado    = $request->message_anulado;

            if (!$id_pedido) {
                return ["status" => "0", "message" => "El pedido no existe"];
            }

            $getPedido = Pedidos::findOrFail($id_pedido);

            if ($getPedido->estado_entrega == '2') {
                return ["status" => "0", "message" => "El pedido ya fue entregado"];
            }

            $id_empresa = $getPedido->empresa_id;
            $arrayDetalles = $ifnuevo == '0'
                ? $this->verStock($id_pedido, $id_empresa)
                : $this->verStock_new($id_pedido, $id_empresa);

            if (empty($arrayDetalles)) {
                return ["status" => "0", "message" => "No hay detalle de pedido de guias para el pedido $id_pedido"];
            }

            $arrayDetalles = array_map(fn($item) => (object) $item, $arrayDetalles);
            if(count($arrayDetalles) == 0){
                return ["status" => "0", "message" => "No hay detalle de pedido de guias para el pedido $id_pedido"];
            }
            $arrayOldValues = [];
            $arrayNewValues = [];
            foreach ($arrayDetalles as $item) {
                $codigo        = $item->codigoFact;
                $valorAumentar = $item->valor - $item->cantidad_pendiente;
                $producto = _14Producto::obtenerProducto($codigo);

                if (!$producto) {
                    return ["status" => "0", "message" => "No se pudo obtener el producto $codigo"];
                }

                $oldValues = [
                    'pro_codigo'         => $producto->pro_codigo,
                    'pro_reservar'       => $producto->pro_reservar ?? 0,
                    'pro_stock'          => $producto->pro_stock ?? 0,
                    'pro_stockCalmed'    => $producto->pro_stockCalmed ?? 0,
                    'pro_deposito'       => $producto->pro_deposito ?? 0,
                    'pro_depositoCalmed' => $producto->pro_depositoCalmed ?? 0,
                ];

                $stockReserva = $producto->pro_reservar + $valorAumentar;

                if ($id_empresa == 1) {
                    $stockEmpresa = $producto->pro_stock + $valorAumentar;
                } elseif ($id_empresa == 3) {
                    $stockEmpresa = $producto->pro_stockCalmed + $valorAumentar;
                } else {
                    $stockEmpresa = 0;
                }
                // Actualizar stock
                _14Producto::updateStock($codigo, $id_empresa, $stockReserva, $stockEmpresa);

                $getNuevo = _14Producto::obtenerProducto($codigo);
                $newValues = [
                    'pro_codigo'         => $getNuevo->pro_codigo,
                    'pro_reservar'       => $getNuevo->pro_reservar,
                    'pro_stock'          => $getNuevo->pro_stock,
                    'pro_stockCalmed'    => $getNuevo->pro_stockCalmed,
                    'pro_deposito'       => $getNuevo->pro_deposito,
                    'pro_depositoCalmed' => $getNuevo->pro_depositoCalmed,
                ];
                // Guardar en historico en el array
                $arrayOldValues[] = $oldValues;
                $arrayNewValues[] = $newValues;
            }
            // Historico
            _14ProductoStockHistorico::insert([
                'psh_old_values' => json_encode($arrayOldValues),
                'psh_new_values' => json_encode($arrayNewValues),
                'psh_tipo'       => 5,
                'id_pedido'      => $id_pedido,
                'user_created'   => $user_created,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            // Actualizar en pedido de guias a anulado
            Pedidos::where('id_pedido', $id_pedido)->update([
                'estado' => '2',
                'user_anulado' => $user_created,
                'message_anulado' => $message_anulado
            ]);

            DB::commit();
            return ["status" => "1", "message" => "Se anuló correctamente el pedido aprobado"];
        } catch (\Exception $ex) {
            DB::rollBack();
            return ["status" => "0", "message" => "Error al anular el pedido aprobado".$ex->getMessage()];
        }
    }

    //api:post/guias?enviarAPerseo=1
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
                if($request->byGuia){
                    Ventas::where('ven_codigo', $ven_codigo)
                      ->where('id_empresa', $empresa)
                      ->update([
                          'estadoPerseo' => 1,
                        //   'est_ven_codigo' => 1, // Cambiar a Despachado
                          'pedido_codigo' => $pedidoCodigo_nuevo,
                          'id_pedido_perseo' => $idpedidoCodigo_nuevo,
                          'fecha_envio_perseo' => date('Y-m-d H:i:s')
                      ]);
                }else{
                     Ventas::where('ven_codigo', $ven_codigo)
                      ->where('id_empresa', $empresa)
                      ->update([
                          'estadoPerseo' => 1,
                          'est_ven_codigo' => 1, // Cambiar a Despachado
                          'pedido_codigo' => $pedidoCodigo_nuevo,
                          'id_pedido_perseo' => $idpedidoCodigo_nuevo,
                          'fecha_envio_perseo' => date('Y-m-d H:i:s')
                      ]);
                }


                DB::commit();

                return response()->json([
                    'status' => 200,
                    'message' => 'Venta enviada a Perseo y marcada como Despachada exitosamente',
                    'data' => [
                        'ven_codigo' => $ven_codigo,
                        'pedido_codigo' => $pedidoCodigo_nuevo,
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

    //api:post/guias?anularDocumentoVenta=1
    public function anularDocumentoVenta(Request $request){
        try {
            $ven_codigo = $request->ven_codigo;
            $id_empresa = $request->id_empresa;
            $id_usuario = $request->id_usuario;
            $observacionAnulacion = $request->observacionAnulacion;

            if (!$ven_codigo || !$id_empresa) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan parámetros requeridos: ven_codigo e id_empresa'
                ], 400);
            }

            if (!$observacionAnulacion || trim($observacionAnulacion) === '') {
                return response()->json([
                    'status' => 0,
                    'message' => 'La observación de anulación es obligatoria'
                ], 400);
            }

            $venta = Ventas::where('ven_codigo', $ven_codigo)
                          ->where('id_empresa', $id_empresa)
                          ->first();

            if (!$venta) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No se encontró el documento de venta'
                ], 404);
            }

            if ($venta->est_ven_codigo == 3) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El documento de venta ya está anulado'
                ], 400);
            }

            if ($venta->estadoPerseo == 1) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El documento de venta ya ha sido enviado a Perseo y no se puede anular'
                ], 400);
            }

            // Actualizar usando DB::table directamente
            DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->where('id_empresa', $id_empresa)
                ->update([
                    'est_ven_codigo' => 3,
                    'user_anulado' => $id_usuario,
                    'observacionAnulacion' => $observacionAnulacion,
                    'fecha_anulacion' => now()
                ]);

            // validar si no hay documentos en f_venta con el id_pedido_guia en pedidos cambiar a estado_entrega 0
            $validateDocumentos = Ventas::where('id_pedido_guia', $venta->id_pedido_guia)
                                ->where('est_ven_codigo', '!=', 3)
                                ->count();
            if($validateDocumentos == 0){
                Pedidos::where('id_pedido', $venta->id_pedido_guia)
                    ->update(['estado_entrega' => '0']);
            }

            return response()->json([
                'status' => 1,
                'message' => 'Documento de venta anulado correctamente'
            ], 200);
        }
        catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Ocurrió un error al intentar anular el documento de venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //api:post/saveDevolucionGuiasBodega
    public function saveDevolucionGuiasBodega(Request $request)
    {
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);

        DB::beginTransaction(); // 🚀 INICIO TRANSACCIÓN

        try {

            $detalles   = json_decode($request->data_detalle);
            $asesor_id  = $request->asesor_id;
            $periodo_id = $request->periodo_id;

            if ($request->id == 0) {

                // Crear nueva devolución
                $devolucion = new PedidoGuiaDevolucion();
                $devolucion->periodo_id = $periodo_id;
                $devolucion->asesor_id  = $asesor_id;
                $devolucion->save();

            } else {

                // Buscar devolución existente
                $devolucion = PedidoGuiaDevolucion::findOrFail($request->id);

                // Validar estado
                if ($devolucion->estado != 0) {
                    DB::rollBack();
                    return [
                        "status"  => "0",
                        "message" => "La solicitud de la devolución no se encuentra activa"
                    ];
                }
            }

            // Eliminar detalles anteriores
            DB::delete("
                DELETE FROM pedidos_guias_devolucion_detalle
                WHERE pedidos_guias_devolucion_id = ?
                AND asesor_id = ?
                AND periodo_id = ?
            ", [ $devolucion->id, $asesor_id, $periodo_id ]);

            // Reajustar el autoincremento
            $ultimoId = PedidoGuiaDevolucionDetalle::max('id') + 1;
            DB::statement('ALTER TABLE pedidos_guias_devolucion_detalle AUTO_INCREMENT = ' . $ultimoId);

            // Guardar detalles nuevos
            foreach ($detalles as $item) {
                 $this->saveDevolucionDetalle($item, $devolucion, $asesor_id, $periodo_id);
            }

            // Todo ok → guardar
            DB::commit();

            return [
                "status"  => "1",
                "message" => "Se guardó correctamente"
            ];

        } catch (\Exception $e) {

            DB::rollBack(); // ❌ ERROR → revertir todo

            return [
                "status"  => "0",
                "message" => "Error al guardar la devolución",
                "error"   => $e->getMessage() // opcional, para debug
            ];
        }
    }

    public function saveDevolucionDetalle($tr,$devolucion,$asesor_id,$periodo_id){
        $cantidadDisponiblePedido = $tr->cantidadDisponiblePedido;
        $cantidadDisponiblePedido;
        $formato                  = $tr->formato;
        $residuo                  = 0;
        $cantidad_devuelta_codigoslibros = 0;
        if($formato > $cantidadDisponiblePedido){
            $residuo = $formato - $cantidadDisponiblePedido;
        }
        // el residuo se lo resta a la bodega
        if($residuo > 0){
            $cantidad_devuelta_codigoslibros = $residuo;
            $formato              = $formato - $residuo;
        }
        // validar la cantidad
        //validar que el libro ya haya sido devuelto
        $validate = DB::SELECT("SELECT * FROM  pedidos_guias_devolucion_detalle
        WHERE pro_codigo = '$tr->pro_codigo'
        AND pedidos_guias_devolucion_id = '$devolucion->id'
        AND asesor_id = '$asesor_id'
        AND periodo_id = '$periodo_id'
        LIMIT 1
        ");
        if(count($validate) > 0){
            $getId = $validate[0]->id;
            $detalle = PedidoGuiaDevolucionDetalle::findOrFail($getId);
        }else{
            $detalle = new PedidoGuiaDevolucionDetalle();
        }
        $detalle->pro_codigo                        = $tr->pro_codigo;
        $detalle->cantidad_devuelta                 = $formato;
        $detalle->cantidad_devuelta_codigoslibros   = $cantidad_devuelta_codigoslibros;
        $detalle->pedidos_guias_devolucion_id       = $devolucion->id;
        $detalle->asesor_id                         = $asesor_id;
        $detalle->periodo_id                        = $periodo_id;
        $detalle->save();
    }
    //api:post/eliminarDevolucionGuias
    public function eliminarDevolucionGuias(Request $request){
        $devolucion = PedidoGuiaDevolucion::findOrFail($request->id)->delete();
        DB::DELETE("DELETE FROM pedidos_guias_devolucion_detalle WHERE pedidos_guias_devolucion_id = '$request->id'");
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    //api:get/guias/{id}
    public function show($id)
    {
        $Devolucion = PedidoGuiaDevolucion::findOrFail($id);
        $detalleDevolucion = DB::SELECT("SELECT d.*, ls.pro_nombre AS nombrelibro
        FROM pedidos_guias_devolucion_detalle d
        LEFT JOIN 1_4_cal_producto ls ON ls.pro_codigo = d.pro_codigo
        WHERE d.pedidos_guias_devolucion_id = '$id'
        ");
        $Devolucion->detalleDevolucion = $detalleDevolucion;
        return $Devolucion;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

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
    //api:post/guias/cambiar
    public function changeGuiaSTOCK(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $guias = json_decode($request->data_guias);
        $contador = 0;
        try {
            foreach($guias as $key => $item){
                $form_data_stock = [];
                $codigo         = $item->pro_codigo;
                $codigoFact     = $item->codigoFact;
                //get stock
               // $getStock       = Http::get('http://186.4.218.168:9095/api/f2_Producto/Busquedaxprocodigo?pro_codigo='.$codigo);
                $getStock       = Http::get('http://186.4.218.168:9095/api/f2_Producto/Busquedaxprocodigo?pro_codigo='.$codigoFact);
                $json_stock     = json_decode($getStock, true);
                $stockAnterior  = $json_stock["producto"][0]["proStock"];
                //post stock
                $valorNew       = $item->cantidad;
                $nuevoStock     = $stockAnterior - $valorNew;
                $form_data_stock = [
                    "proStock"     => $nuevoStock,
                ];
                //test
                //$postStock = Http::post('http://186.4.218.168:9095/api/f_Producto/ActualizarStockProducto?proCodigo='.$codigo,$form_data_stock);
                //$postStock = Http::post('http://186.4.218.168:9095/api/f_Producto/ActualizarStockProducto?proCodigo='.$codigoFact,$form_data_stock);
                //prod
                //$postStock = Http::post('http://186.4.218.168:9095/api/f2_Producto/ActualizarStockProducto?proCodigo='.$codigo,$form_data_stock);
                $postStock = Http::post('http://186.4.218.168:9095/api/f2_Producto/ActualizarStockProducto?proCodigo='.$codigoFact,$form_data_stock);
                $json_StockPost = json_decode($postStock, true);

                $historico = new PedidoHistoricoActas();
                $historico->cantidad        = $valorNew;
                $historico->ven_codigo      = "A-S23-FR-000053427";
                $historico->pro_codigo      = $codigo;
                $historico->stock_anterior  = $stockAnterior;
                $historico->nuevo_stock     = $nuevoStock;
                $historico->save();
                if($historico){
                    $contador++;
                }
                //save Historico
                // $historico = new PedidoGuiaTemp();
                // $historico->cantidad        = $valorNew;
                // $historico->pro_codigo      = $codigo;
                // $historico->stock_anterior  = $stockAnterior;
                // $historico->nuevo_stock     = $nuevoStock;
                // $historico->tipo            = $request->tipo;
                // $historico->save();
                // if($historico){
                //     $contador++;
                // }
            }
            return ["cambiados" => $contador];
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }
    }
    public function corregirChequeContabilidad(Request $request){
        DB::UPDATE("UPDATE pedidos_historico
        SET `$request->fecha` = null,
        `$request->sendFile` = null,
         `estado` = '$request->estado'
         WHERE `id_pedido` = '$request->id_pedido'
         ");
    }

    //INICIO METODOS JEYSON

    public function get_val_pedidoInfo($pedido){
        //Este metodo esta redirigido al TraitPedidosGeneral.php
        return $this->tr_get_val_pedidoInfo($pedido);
    }

    public function get_val_pedidoInfo_new($pedido){
        //Este metodo esta redirigido al TraitPedidosGeneral.php
        return $this->tr_get_val_pedidoInfo_new($pedido);
    }

    //api:get/guias?InformacionPedidoGuia=1&id_pedido=2753
    public function InformacionPedidoGuia($id_pedido){
        try {
            $infoPedido = Pedidos::findOrFail($id_pedido);
            $id_periodo = $infoPedido->id_periodo;
            // Si el periodo es menor o igual al configurado -> usa pedidos viejos
            $nuevo = ($id_periodo <= $this->tr_periodoPedido) ? 0 : 1;
            // Pedidos (viejo o nuevo según $nuevo)
            if ($nuevo == 0) {
                $arregloCodigos    = $this->get_val_pedidoInfo($id_pedido);
            } else {
                //consultar el stock
                $arregloCodigos     = $this->get_val_pedidoInfo_new($id_pedido);
            }
            $contador = 0;
            $form_data_stock = [];
            foreach($arregloCodigos as $key => $item){
                $id                     = $arregloCodigos[$contador]["id"];
                // $cantidad_pendiente     = $arregloCodigos[$contador]["cantidad_pendiente"];
                $codigo                 = $arregloCodigos[$contador]["codigo_liquidacion"];
                $codigoFact             = "G".$codigo;
                $nombrelibro            = $arregloCodigos[$contador]["nombrelibro"];
                $cantidad_solicitada    = $arregloCodigos[$contador]["valor"];

                $form_data_stock[$contador] = [
                "id"                => $id,
                "nombrelibro"       => $nombrelibro,
                "codigoFact"        => $codigoFact,
                "codigo"            => $codigo,
                // "cantidad_pendiente"=> $cantidad_pendiente,
                "cantidad_solicitada"=> $cantidad_solicitada,
                ];
                // traer de documentos de venta cuanto se ha enviado
               $cantidadAutorizada =  DetalleVentas::join('f_venta', function($join) {
                    $join->on('f_venta.id_empresa', '=', 'f_detalle_venta.id_empresa')
                        ->on('f_venta.ven_codigo', '=', 'f_detalle_venta.ven_codigo');
                })
                ->where('f_detalle_venta.pro_codigo', $codigoFact)
                ->where('f_venta.est_ven_codigo', '<>', 3)
                ->where('f_venta.id_pedido_guia', $id_pedido)
                ->sum('f_detalle_venta.det_ven_cantidad');
                $form_data_stock[$contador]["cantidadAutorizada"] = $cantidadAutorizada;
                //CANTIDAD PENDIENTE RESTAR CANTIDAD  SOLICITADA - CANTIDAD AUTORIZADA
                $form_data_stock[$contador]["cantidad_pendiente"] = $cantidad_solicitada - $cantidadAutorizada;
                $contador++;
            }
            return $form_data_stock;
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => $ex->getMessage()];
        }
    }

    public function verStock_new($id_pedido,$empresa){
        try {
            //consultar el stock
            $arregloCodigos     = $this->get_val_pedidoInfo_new($id_pedido);
            $contador = 0;
            $form_data_stock = [];
            foreach($arregloCodigos as $key => $item){
                $stockAnterior      = 0;
                $id                 = $arregloCodigos[$contador]["id"];
                $cantidad_pendiente = $arregloCodigos[$contador]["cantidad_pendiente"];
                $codigo             = $arregloCodigos[$contador]["codigo_liquidacion"];
                $codigoFact         = "G".$codigo;
                $nombrelibro        = $arregloCodigos[$contador]["nombrelibro"];
                $valor              = $arregloCodigos[$contador]["valor"];
                //get stock
                $getStock           = _14Producto::obtenerProducto($codigoFact);
                //prolipa
                if($empresa == 1 || $empresa == 5){
                    $stockAnterior  = $getStock->pro_stock;
                }
                //calmed
                if($empresa == 3 || $empresa == 4){
                    $stockAnterior  = $getStock->pro_stockCalmed;
                }

                $valorNew           = $arregloCodigos[$contador]["valor"];
                $nuevoStock         = ($stockAnterior < 0 ? 0 : $stockAnterior) - $valorNew;
                $form_data_stock[$contador] = [
                "id"                => $id,
                "nombrelibro"       => $nombrelibro,
                "stockAnterior"     => $stockAnterior,
                "valorNew"          => $valorNew,
                "nuevoStock"        => $nuevoStock,
                "codigoFact"        => $codigoFact,
                "codigo"            => $codigo,
                "cantidad_pendiente" => $cantidad_pendiente,
                "valor"             => $valor,
                ];
                $contador++;
            }
            return $form_data_stock;
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => $ex->getMessage()];
        }
    }

    //PARA VER EL STOCK INGRESADO DE LA ACTA
    public function verStockGuiasProlipa_new($id_pedido,$acta){
        try {
            //consultar el stock
            $arregloCodigos = $this->get_val_pedidoInfo_new($id_pedido);
            $contador = 0;
            $form_data_stock = [];
            $contador = 0;
            foreach($arregloCodigos as $key => $item){
                //variables
                $codigo         = $arregloCodigos[$contador]["codigo_liquidacion"];
                $nombrelibro    = $arregloCodigos[$contador]["nombrelibro"];
                $valorNew       = $arregloCodigos[$contador]["valor"];
                //consulta
                $query = DB::SELECT("SELECT * FROM pedidos_historico_actas pa
                WHERE pa.ven_codigo = '$acta'
                AND pa.pro_codigo = '$codigo'
                LIMIT 1
                ");
                if(empty($query)){
                    $form_data_stock[$contador] = [
                    "nombrelibro"    => $nombrelibro,
                    "stockAnterior"  => "",
                    "valorNew"       => $valorNew,
                    "nuevoStock"     => "",
                    "codigo"         => $codigo
                    ];
                }else{
                    $stockAnterior  = $query[0]->stock_anterior;
                    $nuevo_stock    = $query[0]->nuevo_stock;
                    $form_data_stock[$contador] = [
                    "nombrelibro"    => $nombrelibro,
                    "stockAnterior"  => $stockAnterior,
                    "valorNew"       => $valorNew,
                    "nuevoStock"     => $nuevo_stock,
                    "codigo"         => $codigo
                    ];
                }
                $contador++;
            }
            return $form_data_stock;
            // foreach($arregloCodigos as $key => $item){
            //     $codigo         = $arregloCodigos[$contador]["codigo_liquidacion"];
            //     $codigoFact     = "G".$codigo;
            //     $nombrelibro    = $arregloCodigos[$contador]["nombrelibro"];
            //     //get stock
            //     $getStock       = Http::get('http://186.4.218.168:9095/api/f2_Producto/Busquedaxprocodigo?pro_codigo='.$codigoFact);
            //     $json_stock     = json_decode($getStock, true);
            //     $stockAnterior  = $json_stock["producto"][0]["proStock"];
            //     //post stock
            //     $valorNew       = $arregloCodigos[$contador]["valor"];
            //     $nuevoStock     = $stockAnterior - $valorNew;
            //     $form_data_stock[$contador] = [
            //     "nombrelibro"    => $nombrelibro,
            //     "stockAnterior"  => $stockAnterior,
            //     "valorNew"       => $valorNew,
            //     "nuevoStock"     => $nuevoStock,
            //     "codigoFact"     => $codigoFact,
            //     "codigo"         => $codigo
            //     ];
            //     $contador++;
            // }
            return $form_data_stock;
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }
    }
    //FIN METODOS JEYSON
}
