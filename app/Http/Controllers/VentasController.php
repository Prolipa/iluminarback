<?php

namespace App\Http\Controllers;
use App\Models\Ventas;
use App\Models\DetalleVentas;
use App\Models\VentasF;
use App\Models\DetalleVentasF;
use App\Models\Proforma;
use App\Models\f_tipo_documento;
use App\Models\_14Producto ;
use App\Http\Controllers\Log;
use App\Repositories\Facturacion\ProformaRepository;
use DB;
use App\Http\Controllers\Controller;
use App\Models\CodigosLibros;
use App\Models\CodigosLibrosDevolucionSon;
use App\Models\Pedidos;
use App\Models\Periodo;
use App\Models\User;
use App\Models\_14ProductoStockHistorico;
use App\Traits\Pedidos\TraitProforma;
use App\Repositories\pedidos\PedidosRepository;
use App\Traits\Codigos\TraitCodigosGeneral;
use App\Traits\Pedidos\TraitPedidosGeneral;
use App\Repositories\Facturacion\DevolucionRepository;
use App\Repositories\Facturacion\VentaRepository;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\_14ProductoController;
use App\Models\LibroSerie;
use App\Models\Usuario;

class VentasController extends Controller
{
    use TraitProforma;
    use TraitCodigosGeneral;
    use TraitPedidosGeneral;
    protected $pedidosRepository;
    protected $proformaRepository;
    protected $devolucionRepository;
    protected $ventasRepository;

    public function __construct(PedidosRepository $pedidosRepository, ProformaRepository $proformaRepository, DevolucionRepository $devolucionRepository, VentaRepository $ventaRepository)
    {
        $this->pedidosRepository    = $pedidosRepository;
        $this->proformaRepository   = $proformaRepository;
        $this->devolucionRepository = $devolucionRepository;
        $this->ventasRepository     = $ventaRepository;
    }
    //
    public function Get_tdocu(){
        $query = DB::SELECT("SELECT *FROM f_tipo_documento where (tdo_id=1 or tdo_id=3 or tdo_id=4) and tdo_estado=1");
            return $query;
    }
    public function Get_tipoVenta(){
        $query = DB::SELECT("SELECT *FROM 1_4_tipo_venta");
            return $query;
    }
    public function GetVentas(Request $request){
        if($request->tipo==0){
            $query1 = DB::SELECT("SELECT dv.pro_codigo,  SUM(dv.det_ven_cantidad) AS cantidad FROM f_venta AS v
            INNER JOIN f_detalle_venta AS dv ON v.ven_codigo=dv.ven_codigo
            INNER JOIN f_proforma AS p ON p.prof_id=v.ven_idproforma
            INNER JOIN pedidos AS pe ON pe.id_pedido=p.pedido_id
            WHERE pe.id_pedido=$request->pedido_id AND (v.est_ven_codigo = 2 OR v.est_ven_codigo = 1) AND p.pedido_id=$request->pedido_id
            GROUP BY dv.pro_codigo");
            return $query1;
        }else if($request->tipo== 1){
            $query1 = DB::SELECT("SELECT SUM(dv.det_ven_cantidad) AS cantidad, dv.pro_codigo,v.ven_desc_por, ls.nombre as nombrelibro, dv.det_ven_valor_u
            FROM f_venta v
            INNER JOIN f_detalle_venta dv ON v.ven_codigo=dv.ven_codigo AND v.id_empresa=dv.id_empresa
            INNER JOIN libros_series as ls ON dv.pro_codigo=ls.codigo_liquidacion
            INNER JOIN f_proforma fp ON fp.prof_id=v.ven_idproforma
            WHERE fp.idPuntoventa = '$request->pedido_id'
            AND v.est_ven_codigo <> 3
            GROUP BY dv.pro_codigo");
            return $query1;
        }else if($request->tipo== 2){
            $query1 = DB::SELECT("SELECT SUM(v.ven_descuento) AS descuento, sum(v.ven_transporte) as transporte FROM f_venta v
                INNER JOIN f_proforma fp ON fp.prof_id=v.ven_idproforma and fp.emp_id=v.id_empresa
                WHERE fp.idPuntoventa = '$request->pedido_id' ");
            return $query1;
        }
    }
    // listar las facturas
    public function GetFacturasX(Request $request){
        $query = DB::SELECT("SELECT ins.ruc as rucPuntoVenta, em.nombre as empresa,
          CONCAT(usa.nombres, ' ',usa.apellidos) as cliente, fv.*, ins.nombreInstitucion,ins.direccionInstitucion, ins.telefonoInstitucion, ins.asesor_id, concat(u.nombres,' ',u.apellidos) as asesor,
        usa.nombres, usa.apellidos, fpr.prof_observacion, fpr.idPuntoventa,
        COUNT(DISTINCT dfv.pro_codigo) AS item, CONCAT(us.nombres,' ',us.apellidos) AS responsable,
        CONCAT(interc.nombres,' ',interc.apellidos) AS user_intercambio, CONCAT(ua.nombres,' ',ua.apellidos) AS usuario_anulado,
        camb.ven_codigo as documentoOrigen, camb.idtipodoc as tipoDocumentoOrigen,
        (SELECT SUM(det_ven_cantidad)
        FROM f_detalle_venta
         WHERE ven_codigo = fv.ven_codigo and id_empresa=fv.id_empresa) AS libros,usa.cedula, usa.email,usa.telefono
        FROM f_venta fv
        LEFT JOIN f_proforma fpr ON fpr.prof_id = fv.ven_idproforma
        LEFT JOIN empresas em ON fpr.emp_id = em.id
        -- LEFT JOIN pedidos pe ON fpr.idPuntoventa = pe.ca_codigo_agrupado
        LEFT JOIN institucion ins ON fpr.id_ins_depacho = ins.idInstitucion
        LEFT JOIN usuario usa ON fpr.ven_cliente = usa.idusuario
        INNER JOIN usuario us ON fv.user_created = us.idusuario
        LEFT JOIN usuario u ON ins.asesor_id = u.idusuario
        LEFT JOIN f_detalle_venta dfv ON fv.ven_codigo = dfv.ven_codigo and fv.id_empresa=dfv.id_empresa
        LEFT JOIN usuario as interc ON fv.user_intercambio = interc.idusuario
        LEFT JOIN usuario ua ON fv.user_anulado = ua.idusuario
        LEFT JOIN f_venta camb ON camb.doc_intercambio = fv.ven_codigo  AND camb.id_empresa = fv.id_empresa
        WHERE fpr.idPuntoventa = '$request->prof_id' and fv.periodo_id=$request->periodo and (fv.idtipodoc=1 or fv.idtipodoc=3 or fv.idtipodoc=4)
        GROUP BY fv.ven_codigo,  usa.nombres, usa.apellidos, fpr.prof_observacion order by ven_fecha desc
        ");
        return $query;
    }
    //verificacion documento de venta con proformas
    public function VerificacionDVenta(Request $request){
        $query = DB::SELECT("SELECT SUM(dfv.det_ven_cantidad) AS venta, SUM(dp.det_prof_cantidad)as proforma
        from f_detalle_proforma dp
        left join f_detalle_venta dfv on dfv.idProforma=dp.prof_id
        where dp.prof_id='$request->id'");
    }


    public function VerificacionDVentaProforma(Request $request) {
        if ($request->id) {
            // Buscar la proforma por el ID proporcionado
            $proforma = DB::table('f_proforma')
                ->where('id', $request->id)
                ->whereIn('prof_estado', [3,6])  // Solo proformas con estado 'Finalizada' (prof_estado = 3)
                ->first();

            if ($proforma) {
                // Verificar si hay detalles de la proforma para este ID
                $detallesProforma = DB::table('f_detalle_proforma as fp')
                    ->where('fp.prof_id', $proforma->id)
                    ->select('fp.pro_codigo', DB::raw('SUM(fp.det_prof_cantidad) as cantidad_proforma'))
                    ->groupBy('fp.pro_codigo')  // Agrupar por pro_codigo
                    ->get();

                // Verificar si los detalles de la proforma están vacíos
                if ($detallesProforma->isEmpty()) {
                    return response()->json(['message' => 'No hay detalles para esta proforma', 'status' => '0']);
                }

                // Obtener ventas relacionadas con esta proforma que no estén anuladas
                $ventas = DB::table('f_venta')
                    ->where('ven_idproforma', $proforma->prof_id)
                    ->where('est_ven_codigo', '!=', 3)  // Excluir ventas anuladas
                    ->get();

                if ($ventas->isNotEmpty()) {
                    // Variable para almacenar ventas que no cumplieron los detalles de la proforma
                    $ventasIncompletas = [];
                    $detallesCoinciden = true; // Inicializamos la variable como verdadera

                    // Iterar sobre cada venta para verificar sus detalles
                    foreach ($ventas as $venta) {
                        // Obtener los detalles de la venta agrupados por pro_codigo
                        $detalleVentas = DB::table('f_detalle_venta as fv')
                            ->where('fv.ven_codigo', $venta->ven_codigo)
                            ->groupBy('fv.pro_codigo')
                            ->select('fv.pro_codigo', DB::raw('SUM(fv.det_ven_cantidad) as cantidad_venta'))
                            ->get();

                        // Comparar los detalles de la venta con los de la proforma
                        foreach ($detallesProforma as $detalleProforma) {
                            $detalleVenta = $detalleVentas->firstWhere('pro_codigo', $detalleProforma->pro_codigo);

                            // Si no se encuentra el producto en los detalles de la venta
                            if (!$detalleVenta) {
                                $ventasIncompletas[] = $venta;
                                $detallesCoinciden = false; // Si no coincide, cambia el valor a false
                                break;
                            }

                            // Si la cantidad de la venta no es suficiente según lo detallado en la proforma
                            if ($detalleVenta->cantidad_venta < $detalleProforma->cantidad_proforma) {
                                $ventasIncompletas[] = $venta;
                                $detallesCoinciden = false; // Si no coincide, cambia el valor a false
                                break;
                            }
                        }
                    }

                    // Si alguna venta está incompleta, retornarla
                    if (!empty($ventasIncompletas)) {
                        $result = [
                            'ventas' => $ventasIncompletas,
                            'count' => count($ventasIncompletas),
                            'detallesCoinciden' => $detallesCoinciden,  // Agregar si los detalles coinciden
                            'detallesProforma' => $detallesProforma,    // Agregar detalles de la proforma
                            'detallesVentas' => $detalleVentas,         // Agregar detalles de la venta
                        ];
                        return response()->json($result);
                    }

                    // Si todas las ventas cumplen con los detalles de la proforma
                    $result = [
                        'ventas' => $ventas,
                        'count' => $ventas->count(),
                        'detallesCoinciden' => $detallesCoinciden,  // Agregar si los detalles coinciden
                        'detallesProforma' => $detallesProforma,    // Agregar detalles de la proforma
                        'detallesVentas' => $detalleVentas,         // Agregar detalles de la venta
                    ];
                    return response()->json($result);

                } else {
                    $detallesCoinciden = false;

                    // Si no se encontraron ventas
                    $result = [
                        'ventas' => [],
                        'count' => 0,
                        'detallesCoinciden' => $detallesCoinciden,  // Agregar si los detalles coinciden
                        'detallesProforma' => $detallesProforma,    // Agregar detalles de la proforma
                        'detallesVentas' => [],                     // No hay detalles de venta si no hay ventas
                    ];
                    return response()->json($result);
                }
            } else {
                return response()->json(['message' => 'Proforma no encontrada', 'status' => '0']);
            }
        }
        return response()->json(['message' => 'No ID provided', 'status' => '0']);
    }

    //api:get/GetFacturasTodas
    public function GetFacturasTodas(Request $request){
        $query = DB::SELECT("SELECT ins.ruc as rucPuntoVenta, em.nombre as empresa,
        CONCAT(usa.nombres, ' ',usa.apellidos) as cliente, fv.*, ins.nombreInstitucion,ins.direccionInstitucion, ins.telefonoInstitucion, ins.asesor_id, concat(u.nombres,' ',u.apellidos) as asesor,
        usa.nombres, usa.apellidos, fpr.prof_observacion, fpr.idPuntoventa,
        COUNT(DISTINCT dfv.pro_codigo) AS item, CONCAT(us.nombres,' ',us.apellidos) AS responsable,
        (SELECT SUM(det_ven_cantidad)
        FROM f_detalle_venta
        WHERE ven_codigo = fv.ven_codigo and id_empresa=fv.id_empresa ) AS libros,usa.cedula, usa.email,usa.telefono
        FROM f_venta fv
        LEFT JOIN f_proforma fpr ON fpr.prof_id = fv.ven_idproforma
        LEFT JOIN empresas em ON fpr.emp_id = em.id
        LEFT JOIN pedidos pe ON fpr.idPuntoventa = pe.ca_codigo_agrupado
        LEFT JOIN institucion ins ON fpr.id_ins_depacho = ins.idInstitucion
        LEFT JOIN usuario usa ON fpr.ven_cliente = usa.idusuario
        INNER JOIN usuario us ON fv.user_created = us.idusuario
        LEFT JOIN usuario u ON ins.asesor_id = u.idusuario
        LEFT JOIN f_detalle_venta dfv ON fv.ven_codigo = dfv.ven_codigo and fv.id_empresa=dfv.id_empresa
        WHERE fv.periodo_id=$request->periodo and fv.idtipodoc=1
        GROUP BY fv.ven_codigo,  usa.nombres, usa.apellidos, fpr.prof_observacion order by ven_fecha desc
      ");
      if($request->estadoPerseo){
        if(count($query) == 0) { return $query; }
        $resultado = collect($query)->where('estadoPerseo',0)->where('est_ven_codigo','<>','3')->values();
        return $resultado;
      }
      return $query;
    }
    public function GetPreFacturasxAgrupa(Request $request){
        if(!$request->busqueda){
            return;
        }else{
            $query = DB::SELECT("SELECT
            fv.ven_codigo,
            fv.ven_fecha,
            fv.ven_idproforma,
            fv.est_ven_codigo,
            fv.ven_valor,
            fv.id_empresa,
            fv.estadoPerseo,
            fv.*,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono,
            em.nombre,
            COUNT(DISTINCT dfv.pro_codigo) AS item,
            SUM(dfv.det_ven_cantidad) AS libros,
            SUM(dfv.det_ven_dev) AS devuelto
        FROM
            f_venta fv
            INNER JOIN institucion ins ON fv.institucion_id = ins.idInstitucion
            INNER JOIN usuario usa ON fv.ven_cliente = usa.idusuario
            INNER JOIN f_detalle_venta dfv ON fv.ven_codigo = dfv.ven_codigo
            INNER JOIN empresas em ON em.id = fv.id_empresa
        WHERE
            (ins.nombreInstitucion LIKE '%$request->busqueda%' OR usa.cedula='$request->busqueda')
            AND fv.id_empresa=$request->empresa
            AND dfv.id_empresa = fv.id_empresa
            AND fv.idtipodoc = 1
            AND fv.id_factura  IS NULL
            AND fv.periodo_id=$request->periodo
            AND fv.est_ven_codigo!=3
        GROUP BY
            fv.ven_codigo,
            fv.ven_fecha,
            fv.ven_idproforma,
            fv.est_ven_codigo,
            fv.ven_valor,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono
        ORDER BY
            fv.ven_fecha DESC");
    return $query;
        }
    }
    public function GetFacturasxAgrupa(){

            $query = DB::SELECT("SELECT
            fv.id_factura,
            fv.ven_cliente,
            fv.ven_fecha,
            fv.ven_valor,
            fv.id_empresa,
            fv.estadoPerseo,
            fv.*,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono,
            em.descripcion_corta as nombre,
            CONCAT(us.nombres,' ',us.apellidos) AS responsable,
            COUNT(DISTINCT dfv.pro_codigo) AS item,
            SUM(dfv.det_ven_cantidad) AS libros
        FROM
            f_venta_agrupado fv
            INNER JOIN institucion ins ON fv.institucion_id = ins.idInstitucion
            INNER JOIN usuario usa ON fv.ven_cliente = usa.idusuario
            INNER JOIN f_detalle_venta_agrupado dfv ON fv.id_factura = dfv.id_factura
            INNER JOIN empresas em ON em.id = fv.id_empresa
            INNER JOIN usuario us ON us.idusuario=fv.user_created
        WHERE dfv.id_empresa = fv.id_empresa
            AND fv.idtipodoc = 11
        GROUP BY
            fv.id_factura,
            fv.ven_cliente,
            fv.ven_fecha,
            fv.ven_valor,
            fv.ven_valor,
            fv.id_empresa,
            fv.estadoPerseo,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono
        ORDER BY
            fv.ven_fecha DESC");
            foreach($query as $key => $item){
                if($item->id_factura){
                    $quer1=DB::SELECT("select ven_codigo from f_venta where id_factura= '$item->id_factura' AND id_empresa=$item->id_empresa");
                    $query[$key]->prefacturas =$quer1;
                }
              }
    return $query;
    }

    public function GetFacturasxAgrupaXPeriodo(Request $request){
            $periodo = $request->periodo;
            $query = DB::SELECT("SELECT
            fv.id_factura,
            fv.ven_cliente,
            fv.ven_fecha,
            fv.ven_valor,
            fv.id_empresa,
            fv.estadoPerseo,
            fv.*,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono,
            em.descripcion_corta as nombre,
            CONCAT(us.nombres,' ',us.apellidos) AS responsable,
            COUNT(DISTINCT dfv.pro_codigo) AS item,
            SUM(dfv.det_ven_cantidad) AS libros
        FROM
            f_venta_agrupado fv
            INNER JOIN institucion ins ON fv.institucion_id = ins.idInstitucion
            INNER JOIN usuario usa ON fv.ven_cliente = usa.idusuario
            INNER JOIN f_detalle_venta_agrupado dfv ON fv.id_factura = dfv.id_factura
            INNER JOIN empresas em ON em.id = fv.id_empresa
            INNER JOIN usuario us ON us.idusuario=fv.user_created
        WHERE dfv.id_empresa = fv.id_empresa
            AND fv.idtipodoc = 11
            AND fv.periodo_id = $periodo
        GROUP BY
            fv.id_factura,
            fv.ven_cliente,
            fv.ven_fecha,
            fv.ven_valor,
            fv.ven_valor,
            fv.id_empresa,
            fv.estadoPerseo,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono
        ORDER BY
            fv.ven_fecha DESC");
            foreach($query as $key => $item){
                if($item->id_factura){
                    $quer1=DB::SELECT("select ven_codigo from f_venta where id_factura= '$item->id_factura' AND id_empresa=$item->id_empresa");
                    $query[$key]->prefacturas =$quer1;
                }
            }
        return $query;
    }


    public function Get_DFactura(Request $request){
        $query = DB::SELECT("SELECT dv.det_ven_codigo, dv.pro_codigo, dv.det_ven_dev, dv.det_ven_cantidad, dv.det_ven_valor_u,
        l.descripcionlibro, ls.nombre, s.nombre_serie, ls.id_serie FROM f_detalle_venta as dv
   inner join f_venta as fv on dv.ven_codigo=fv.ven_codigo
   INNER JOIN libros_series as ls ON dv.pro_codigo=ls.codigo_liquidacion
   INNER JOIN series as s ON ls.id_serie=s.id_serie
    INNER JOIN libro l ON ls.idLibro = l.idlibro
        WHERE dv.ven_codigo='$request->ven_codigo' and dv.id_empresa=fv.id_empresa
        and fv.id_empresa=$request->idemp  order by dv.pro_codigo");

             return $query;
    }
    //lista de datos la factura
    public function Get_DatoFactura(Request $request){
        $query = DB::SELECT("SELECT dv.det_ven_codigo, dv.pro_codigo,  dv.det_ven_cantidad, dv.det_ven_valor_u,
        l.descripcionlibro, ls.nombre, ls.id_serie FROM f_detalle_venta_agrupado as dv
   inner join f_venta_agrupado as fv on dv.id_factura=fv.id_factura
   INNER JOIN libros_series as ls ON dv.pro_codigo=ls.codigo_liquidacion
    INNER JOIN libro l ON ls.idLibro = l.idlibro
        WHERE dv.id_factura='$request->ven_codigo' and dv.id_empresa=fv.id_empresa
        and fv.id_empresa=$request->idemp  order by dv.pro_codigo");

             return $query;
    }
    //funcion para traer la imagen de la empresa
    public function GetVuser(Request $request){
        $query1 = DB::SELECT("SELECT  img_base64 as img FROM empresas
        where id=$request->id");
        $img=$query1[0]->img;
             return $img;
    }
    //api:get/empresaActual
    public function empresaActual(Request $request){
        $query = DB::SELECT("SELECT e.id, e.descripcion_corta FROM empresas e
            WHERE e.imagen_utilizada = '1'
        ");
        return $query;
    }
    public function Get_CodVenta(Request $request){
        if($request->id==1){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_Prolipa as cod from  f_tipo_documento where tdo_letra='$request->letra'");
        }else if ($request->id==3){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_calmed as cod from  f_tipo_documento where tdo_letra='$request->letra'");
        }
        $pre="$request->letra";
        $getSecuencia = 1;
            if(!empty($query1)){
                $pre= $query1[0]->tdo_letra;
                $codi=$query1[0]->cod;
               $getSecuencia=(int)$codi+1;
                if($getSecuencia>0 && $getSecuencia<10){
                    $secuencia = "000000".$getSecuencia;
                } else if($getSecuencia>9 && $getSecuencia<100){
                    $secuencia = "00000".$getSecuencia;
                } else if($getSecuencia>99 && $getSecuencia<1000){
                    $secuencia = "0000".$getSecuencia;
                }else if($getSecuencia>999 && $getSecuencia<10000){
                    $secuencia = "000".$getSecuencia;
                }else if($getSecuencia>9999 && $getSecuencia<100000){
                    $secuencia = "00".$getSecuencia;
                }else if($getSecuencia>99999 && $getSecuencia<1000000){
                    $secuencia = "0".$getSecuencia;
                }else if($getSecuencia>999999 && $getSecuencia<10000000){
                    $secuencia = $getSecuencia;
                }
                $array = array($pre,$secuencia);
             }
             return $array;
    }
    public function Verificarventa(Request $request){
        $query = DB::SELECT("SELECT fdv.ven_codigo, fdv.id_empresa, fdv.pro_codigo, SUM(fdv.det_ven_cantidad) as venta, SUM(fdp.det_prof_cantidad)AS proforma FROM f_venta fv
        INNER JOIN f_detalle_venta fdv ON fv.ven_codigo=fdv.ven_codigo
        INNER JOIN f_proforma fp ON fp.prof_id=fv.ven_idproforma
        INNER JOIN f_detalle_proforma fdp ON fdp.prof_id=fp.id
        WHERE fv.id_empresa=fdv.id_empresa AND fv.id_empresa=fp.emp_id AND fdv.pro_codigo=fdp.pro_codigo AND fv.ven_idproforma='$request->prof_id' AND fv.est_ven_codigo<>3
        GROUP BY fdv.ven_codigo, fdv.id_empresa, fdv.pro_codigo");
                return $query;
    }
    public function imprimirDVenta(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validar si existe la venta
            $venta = Ventas::where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->id_empresa)
                ->first();

            if (!$venta) {
                return response()->json([
                    "status" => "0",
                    "message" => "El ven_codigo no existe en la base de datos"
                ], 200);
            }
            if($venta->est_ven_codigo == 3){
                return response()->json([
                    "status" => "0",
                    "message" => "El documento ya fue anulado"
                ], 200);
            }
            // Actualizar la tabla f_venta
            DB::table('f_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->id_empresa)
                ->update([
                    'impresion' => '1',
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'message' => 'Impresión exitosa'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback(); // Asegurar que se haga el rollback

            return response()->json([
                "status" => "0",
                "message" => "No se pudo imprimir",
                'error' => $e->getMessage()
            ], 200);
        }
    }

    public function getNumeroDocumento($empresa,$letra){
        if($empresa==1){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_Prolipa as cod from  f_tipo_documento where tdo_letra='$letra'");
        }else if ($empresa==3){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_calmed as cod from  f_tipo_documento where tdo_letra='$letra'");
        }
        $pre="$letra";
        $getSecuencia = 1;
        if(!empty($query1)){
            $pre= $query1[0]->tdo_letra;
            $codi=$query1[0]->cod;
            $getSecuencia=(int)$codi+1;
            if($letra == 'F'){
                if($getSecuencia>0 && $getSecuencia<10){
                    $secuencia = "000000".$getSecuencia;
                } else if($getSecuencia>9 && $getSecuencia<100){
                    $secuencia = "00000".$getSecuencia;
                } else if($getSecuencia>99 && $getSecuencia<1000){
                    $secuencia = "0000".$getSecuencia;
                }else if($getSecuencia>999 && $getSecuencia<10000){
                    $secuencia = "000".$getSecuencia;
                }else if($getSecuencia>9999 && $getSecuencia<100000){
                    $secuencia = "00".$getSecuencia;
                }else if($getSecuencia>99999 && $getSecuencia<1000000){
                    $secuencia = "0".$getSecuencia;
                }else if($getSecuencia>999999 && $getSecuencia<10000000){
                    $secuencia = $getSecuencia;
                }
                $secuencia = $secuencia;
                return $secuencia;
            }else{
                return $getSecuencia;
            }
        }
    }
    public function updateSubtotal(Request $request)
    {
        try {
            DB::beginTransaction();

            // Recupera todos los IDs de proforma con ventas no nulas
            $validated = DB::select("SELECT DISTINCT ven_idproforma FROM f_venta WHERE ven_idproforma IS NOT NULL");

            // Extrae los IDs de proforma de los resultados
            $proformaIds = array_map(function($item) {
                return $item->ven_idproforma;
            }, $validated);

            // Recupera todas las ventas con los ID de proforma proporcionados
            $ventas = Ventas::whereIn('ven_idproforma', $proformaIds)->get();

            foreach ($ventas as $venta) {
                // Calcula el nuevo subtotal
                $venta->ven_subtotal = $venta->ven_valor + $venta->ven_descuento;
                // Guarda el cambio
                $venta->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Subtotal actualizado correctamente',
                'data' => $ventas
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'message' => 'No se pudo actualizar el subtotal'
            ], 500);
        }
    }
    public function Postventa_Registra(Request $request){
        $id_empresa         = $request->id_empresa;

        try {
            $idProforma = $request->idProforma;
            set_time_limit(6000000);
            ini_set('max_execution_time', 6000000);
            $miarray = json_decode($request->data_detalleventa);
            //setMenu 0 => por pedidos, 1 => por punto de venta
            $setMenu                        = $request->setMenu;
            //tipod 0 => despacho en institucion; 1 => despacho en punto de venta
            $tipodoc                        = $request->tipodoc;
            //si es setMenu 0 voy a validar que este colocado el ca_codigo_agrupado de la tabla pedidos
            if($setMenu==0){
                $query = DB::SELECT("SELECT ca_codigo_agrupado, id_institucion FROM pedidos WHERE id_pedido=$request->id_pedido");
                //si es tipodoc 1 voy a validar que este colocado el ca_codigo_agrupado de la tabla pedidos
                if($tipodoc == 1){
                    if(empty($query)){ return ["status" => "0", "No se encontro el pedido"]; }
                    //si el ca_codigo_agrupado es nulo o cero o vacio mandar un mensaje seleccione el despacho
                    if($query[0]->ca_codigo_agrupado==0 || $query[0]->ca_codigo_agrupado==null || $query[0]->ca_codigo_agrupado==""){
                        return ["status" => "0", "message" => "Seleccione el despacho"];
                    }
                }
                //si es tipodoc 0 por institucion voy a tomar el id_institucion y voy a colocar en el ca_codigo_agrupado en la tabla pedidos
                if($tipodoc == 0){

                }
            }
            DB::beginTransaction();
            //si el ven_codigo ya existe no creo
            $query = DB::SELECT("SELECT ven_codigo FROM f_venta WHERE ven_codigo='$request->ven_codigo' AND id_empresa = '$request->id_empresa'");
            if(!empty($query)){
                $this->finalizarDocumento($idProforma);
                return ["status" => "2", "message" => "El ven_codigo ya existe"];
            }else{
                $id_empresa                 = $request->id_empresa;
                $datoProforma               = Proforma::findOrFail($request->idProforma);
                $clientesidPerseo           = $datoProforma->clientesidPerseo;
                $venta                      = new Ventas;
                $venta->ven_codigo          = $request->ven_codigo;
                $venta->tip_ven_codigo      = $request->tip_ven_codigo;
                $venta->est_ven_codigo      = $request->est_ven_codigo;
                $venta->ven_tipo_inst       = $request->ven_tipo_inst;
                $venta->ven_subtotal        = $request->ven_subtotal;
                $venta->ven_valor           = $request->ven_valor;
                $venta->ven_com_porcentaje  = $request->ven_com_porcentaje;
                $venta->ven_iva_por         = $request->ven_iva_por;
                $venta->ven_iva             = $request->ven_iva;
                $venta->ven_desc_por        = $request->ven_desc_por;
                $venta->ven_descuento       = $request->ven_descuento;
                $venta->ven_fecha           = $request->ven_fecha;
                $venta->ven_idproforma      = $request->ven_idproforma;
                $venta->ven_transporte      = $request->ven_transporte;
                // $venta->institucion_id      = $request->institucion_id;
                $venta->periodo_id          = $request->periodo_id;
                $venta->user_created        = $request->user_created;
                $venta->id_empresa          = $request->id_empresa;
                $venta->institucion_id      = $request->institucion_id;
                // $venta->id_sucursal         = $request->id_sucursal;
                $venta->idtipodoc           = $request->l;
                $venta->ven_cliente         = $request->ven_cliente;
                $user                       = User::where('idusuario',$request->ven_cliente)->first();
                $getCedula                  = $user->cedula;
                $venta->ruc_cliente         = $getCedula;
                $venta->clientesidPerseo    = $clientesidPerseo;
                $venta->save();
                $oldvalue                   = Proforma::findOrFail($request->idProforma);
                $proform                    = Proforma::findOrFail($request->idProforma);
                if($request->prof_estado == 2){

                }else{
                    $proform->prof_estado       = $request->prof_estado;
                }

                $proform->user_editor       = $request->user_created;
                $proform->save();
                if($proform){
                    $newvalue               = Proforma::findOrFail($request->idProforma);
                    $this->tr_GuardarEnHistorico($request->ven_idproforma,$request->user_created,$oldvalue,$newvalue);
                }
                if($request->id_empresa==1){
                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from  f_tipo_documento where tdo_letra='$request->letra'");
                }else if ($request->id_empresa==3){
                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from  f_tipo_documento where tdo_letra='$request->letra'");
                }
                $id=$query1[0]->id;
                $codi=$query1[0]->cod;
                $co=(int)$codi+1;
                $tipo_doc = f_tipo_documento::findOrFail($id);
                if($request->id_empresa==1){
                    $tipo_doc->tdo_secuencial_Prolipa = $co;
                }else if ($request->id_empresa==3){
                    $tipo_doc->tdo_secuencial_calmed = $co;
                }
                $tipo_doc->save();
                foreach($miarray as $key => $item){
                    if($item->cantidad > 0){
                       // `pro_stockCalmed` INT(11) NULL DEFAULT NULL COMMENT 'factura CALMED',
                        //`pro_depositoCalmed` INT(11) NULL DEFAULT NULL COMMENT 'notas CALMED',
                        //l 1 => facturas; 3 y 4 notas
                        //id_empresa 1 => Prolipa; 2 = Iluminar; 3 = Grupo Calmed
                        //si es prolipa id_empresa=1
                        if($id_empresa==1){
                            if($request->l==1){
                                $query2 = DB::SELECT("SELECT pro_stock as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }else{
                                $query2 = DB::SELECT("SELECT pro_deposito as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }
                        }
                        //grupo calmed
                        if($id_empresa==3){
                            if($request->l==1){
                                $query2 = DB::SELECT("SELECT pro_stockCalmed as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }else{
                                $query2 = DB::SELECT("SELECT pro_depositoCalmed as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }
                        }
                        $codi=$query2[0]->stoc;
                        //INICIO SECCION HISTORICO PRODUCTOS
                        $return_descontarStock = $this->descontarStock($codi,$item,$request->l,$id_empresa);
                        // DB::rollback();
                        // return [
                        //     'codi' => $codi,
                        //     'item' => $item,
                        //     'requestl' => $request->l,
                        //     'id_empresa' => $id_empresa,
                        //     'resultado' => $this->descontarStock($codi, $item, $request->l, $id_empresa),
                        //     'mensaje' => 0
                        // ];
                        // Agregar al historial
                        $HistoricoStock[] = [
                            'psh_old_values' => json_encode($return_descontarStock['old_values']),
                            'psh_new_values' => json_encode($return_descontarStock['new_values']),
                        ];
                        //FIN SECCION HISTORICO PRODUCTOS
                        $venta1=new DetalleVentas;
                        $venta1->ven_codigo         = $request->ven_codigo;
                        $venta1->id_empresa         = $request->id_empresa;
                        $venta1->pro_codigo         = $item->pro_codigo;
                        $venta1->det_ven_cantidad   = $return_descontarStock['totalRestado'];
                        $venta1->det_ven_valor_u    = $item->det_prof_valor_u;
                        $venta1->idProforma         = $idProforma;
                        $venta1->save();
                    }
                }
            }
            $registroHistorial = [
                'psh_old_values' => json_encode(array_column($HistoricoStock, 'psh_old_values', 'pro_codigo')),
                'psh_new_values' => json_encode(array_column($HistoricoStock, 'psh_new_values', 'pro_codigo')),
                'psh_tipo' => 4, //Tipo generacion documento venta
                'psh_id_ven_codigo' => $request->ven_codigo,
                'user_created' => $request->user_creator_doc,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            _14ProductoStockHistorico::insert($registroHistorial);
            $this->finalizarDocumento($idProforma);
            //finalizar documento
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ["error" => "0", "message" => "No se pudo guardar", "exception" => $e->getMessage()];
        }
        return $venta1;
    }
    public function finalizarDocumento($idProforma){
        $proforma       = Proforma::where('id', $idProforma)->first();
        //obtengo los valores de la proforma
        $dataProforma   = DB::SELECT("SELECT * FROM f_detalle_proforma dp
        WHERE dp.prof_id  = '$idProforma';
        ");
        $datosFinalizar = [];
        foreach($dataProforma as $key => $item){
            $cantidadProforma = $item->det_prof_cantidad;
            //buscar la cantidad facturada
            $getFacturada = DB::SELECT("SELECT SUM(d.det_ven_cantidad) AS cantidadFacturada
            FROM f_detalle_venta d
            INNER JOIN f_venta v ON d.ven_codigo = v.ven_codigo AND v.id_empresa = d.id_empresa
            WHERE d.idProforma = '$idProforma'
            AND d.pro_codigo = '$item->pro_codigo'
            AND v.est_ven_codigo <> '3'
            ");
            //si la cantidadFacturada es igual a la cantidadFacturada guardo el producto con un estado 2 en un array
            if($getFacturada[0]->cantidadFacturada == $cantidadProforma){
                $datosFinalizar[] = [
                    'pro_codigo' => $item->pro_codigo,
                    'estado'     => 2
                ];
            }
            //de lo contrato con estado 1
            else{
                $datosFinalizar[] = [
                    'pro_codigo' => $item->pro_codigo,
                    'estado'     => 1
                ];
            }
        }
        //si dentro de mi array datosFinalizar no encuentro un estado 1 que significa que todos los productos fueron facturados
        //cambio el estado a 2 el prof_estado
        $ifEncontrarNoFinalizado =0;
        foreach($datosFinalizar as $key => $item){
            if($item['estado'] == 1){
                $ifEncontrarNoFinalizado = 1;
            }
        }
        if($ifEncontrarNoFinalizado == 0){
            $proforma->prof_estado = 2;
            $proforma->save();
            return "finalizado";
        }else{
            $venta = Ventas::where('ven_idproforma', $proforma->prof_id)
             ->where('est_ven_codigo','<>', 3)
             ->count();
            if($venta >0){
                $proforma->prof_estado = 6;
            }else{
                $proforma->prof_estado = 3;
            }
            $proforma->save();
            return "no finalizado";
        }
    }
    public function descontarStock($codi, $item, $tipo, $id_empresa)
    {
        $pro_codigo_recibido = _14Producto::findOrFail($item->pro_codigo);
        if (!in_array($id_empresa, [1, 3])) {
            return 'Empresa no controlada';
        }
        // Guardar valores antes de actualizar (old_values)
        $old_values = [
            'pro_codigo' => $pro_codigo_recibido->pro_codigo,
            'pro_reservar' => $pro_codigo_recibido->pro_reservar,
            'pro_stock' => $pro_codigo_recibido->pro_stock,
            'pro_stockCalmed' => $pro_codigo_recibido->pro_stockCalmed,
            'pro_deposito' => $pro_codigo_recibido->pro_deposito,
            'pro_depositoCalmed' => $pro_codigo_recibido->pro_depositoCalmed,
        ];
        // Definir los campos en el orden correcto según empresa y tipo
        if ($id_empresa == 1) {
            if ($tipo == 1) {
                $campos = ['pro_stock', 'pro_deposito', 'pro_stockCalmed', 'pro_depositoCalmed'];
            } else if ($tipo == 3 || $tipo == 4) {
                $campos = ['pro_deposito', 'pro_stock', 'pro_depositoCalmed', 'pro_stockCalmed'];
            } else {
                return 'Tipo no controlado';
            }
        } else { // id_empresa == 3
            if ($tipo == 1) {
                $campos = ['pro_stockCalmed', 'pro_depositoCalmed', 'pro_stock', 'pro_deposito'];
            } else if ($tipo == 3 || $tipo == 4) {
                $campos = ['pro_depositoCalmed', 'pro_stockCalmed', 'pro_deposito', 'pro_stock'];
            } else {
                return 'Tipo no controlado';
            }
        }
        // Inicializar variables
        $cantidadRestante = $item->cantidad;
        $totalRestado = 0;
        // Recorrer los campos y descontar stock
        foreach ($campos as $campo) {
            if ($cantidadRestante <= 0) {
                break;
            }
            if ($pro_codigo_recibido->$campo > 0) {
                $aRestar = min($cantidadRestante, $pro_codigo_recibido->$campo);
                $pro_codigo_recibido->$campo -= $aRestar;
                $pro_codigo_recibido->save();
                $cantidadRestante -= $aRestar;
                $totalRestado += $aRestar;
            }
        }
        // Guardar valores después de actualizar (new_values)
        $new_values = [
            'pro_codigo' => $pro_codigo_recibido->pro_codigo,
            'pro_reservar' => $pro_codigo_recibido->pro_reservar,
            'pro_stock' => $pro_codigo_recibido->pro_stock,
            'pro_stockCalmed' => $pro_codigo_recibido->pro_stockCalmed,
            'pro_deposito' => $pro_codigo_recibido->pro_deposito,
            'pro_depositoCalmed' => $pro_codigo_recibido->pro_depositoCalmed,
        ];
        return [
            'old_values' => $old_values,
            'new_values' => $new_values,
            'totalRestado' => $totalRestado
        ];
    }
    //Cambiar el estado de venta
    public function Desactivar_venta(Request $request)
    {
        if($request->tipo=='anular'){
            try{
                set_time_limit(6000000);
                ini_set('max_execution_time', 6000000);
                $miarray=json_decode($request->dat);
                $id_usuario = $request->id_usuario;
                $datosUsuario  = Usuario::find($id_usuario);
                $id_group      = $datosUsuario->id_group;
                DB::beginTransaction();
                $venta = Ventas::where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->empresa)
                ->firstOrFail();
                    if (!$venta){
                        return "El ven_codigo no existe en la base de datos";
                    }
                    // Si el venta fue despachada, no se puede anular
                    // if($id_group == 22 || $id_group == 23){
                    if($venta->est_ven_codigo != 2){
                        return ["status" => "0", "message" => "No puede anular el documento por que fue despachado"];
                    }
                    $validateDevuelto = DB::SELECT("SELECT * FROM f_detalle_venta v
                        WHERE v.ven_codigo = '$request->ven_codigo'
                        AND v.det_ven_dev > 0
                        AND v.id_empresa =  '$request->empresa'
                    ");
                    if(count($validateDevuelto) > 0){
                        // Si ya se ha devuelto, devolver error
                        DB::rollBack();
                        return ["status" => "0", "message" => "El documento $request->ven_codigo tiene devoluciones registradas, no se anular"];
                    }
                    // }
                    DB::table('f_venta')
                    ->where('ven_codigo', $request->ven_codigo)
                    ->where('id_empresa', $request->empresa)
                    ->update([
                        'est_ven_codigo'       => $request->est_ven_codigo,
                        'user_anulado'         => $id_usuario,
                        'observacionAnulacion' => $request->observacionAnulacion,
                        'fecha_anulacion'      => now(),
                    ]);
                    foreach($miarray as $key => $item){
                        //con pro_stock y  pro_reservar
                        if($request->empresa==1){
                            if($request->tipodoc==1){
                                $query1 = DB::SELECT("SELECT pro_stock as stoc, pro_reservar as res from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }else{
                                $query1 = DB::SELECT("SELECT pro_deposito as stoc, pro_reservar as res from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }
                        }
                        //grupo calmed
                        if($request->empresa==3){
                            if($request->tipodoc==1){
                                $query1 = DB::SELECT("SELECT pro_stockCalmed as stoc, pro_reservar as res from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }else{
                                $query1 = DB::SELECT("SELECT pro_depositoCalmed as stoc, pro_reservar as res from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            }
                        }
                        //obtener stock y reservar o bodega general
                        $codi       = $query1[0]->stoc;
                        // $res        = $query1[0]->res;
                        //l 1 => facturas; 3 y 4 notas
                        //id_empresa 1 => Prolipa; 2 = Iluminar; 3 = Grupo Calmed
                        //regresar stock
                        $this->regresarStock($codi,$item,$request->tipodoc,$request->empresa);

                    }
                    $proform= Proforma::where('prof_id', $venta->ven_idproforma)->where('emp_id', $venta->id_empresa)->firstOrFail();
                    $this->finalizarDocumento($proform->id);
                    // $proform->prof_estado = 3;
                    // $proform->save();
                    // SECCION PARA ACTUALIZAR STOCK
                    $productoController = new _14ProductoController();
                    // Llamar al método Mover_Stock_SoloTxt_Todo_A_DepositoCALMED
                    $productoController->Mover_Stock_SoloTxt_Todo_A_DepositoCALMED();
                DB::commit();
                return response()->json(['message' => 'Anulación exitosa de la venta.'], 200);
            }catch(\Exception $e){
                return response()->json(["error"=>"0", "message" => "No se pudo anular","error"=>$e->getMessage()],500);
                DB::rollback();
            }

        }else{
            $datosUsuario  = Usuario::find($request->user_despacha);
            $id_group      = $datosUsuario->id_group;
            $venta= Ventas::where('ven_codigo',$request->ven_codigo)->where('id_empresa', $request->id_empresa)->first();
            if (!$venta){
                return "El ven_codigo no existe en la base de datos";
            }
            // si el documento de venta fue anulado, no se puede desapachar
            if($venta->est_ven_codigo == 3){
                return ["status" => "0", "message" => "No puede desapachar el documento por que fue anulado"];
            }
            DB::table('f_venta')
            ->where('ven_codigo', $request->ven_codigo)
            ->where('id_empresa', $request->id_empresa)
            ->update([
                'est_ven_codigo'       => $request->est_ven_codigo,
                'user_despacha'        => $request->user_despacha,
                'fecha_proceso_despacho'       => now(),
                'observacionRegresarAPendiente' => null
            ]);
            // SECCION PARA ACTUALIZAR STOCK
            $productoController = new _14ProductoController();
            // Llamar al método Mover_Stock_SoloTxt_Todo_A_DepositoCALMED
            $productoController->Mover_Stock_SoloTxt_Todo_A_DepositoCALMED();
            return $venta;
        }
    }
    public function regresarStock($codi,$item,$tipo,$id_empresa){
        //tipo 1 => facturas; 3 y 4 notas
        //id_empresa 1 => Prolipa; 2 = Iluminar; 3 = Grupo Calmed
        // $re         = (int)$res+$item->det_ven_cantidad;
        $co         = (int)$codi+(int)$item->det_ven_cantidad;
        $pro        = _14Producto::findOrFail($item->pro_codigo);
        //PROLIPA
        if($id_empresa ==1){
            // $pro->pro_reservar          = $re;
            if($tipo==1){
                $pro->pro_stock         = $co;
            }else{
                $pro->pro_deposito      = $co;
            }
            $pro->save();
        }
        //CALMED
        if($id_empresa ==3){
            // $pro->pro_reservar          = $re;
            if($tipo==1){
                $pro->pro_stockCalmed   = $co;
            }else{
                $pro->pro_depositoCalmed= $co;
            }
            $pro->save();
        }
    }


    //api:get/GetVentasPendientes?op=1&anio=2021&mes=12
    public function GetVentasPendientes(Request $request) {
        // Definir el estado basado en la opción proporcionada
        // $estado = null;
        // switch ($request->op) {
        //     case 0:
        //         $estado = 2; // Pendientes
        //         break;
        //     case 1:
        //         $estado = 11; // Excluir
        //         break;
        //     case 2:
        //         $estado = 12; // Preparado
        //         break;
        //     case 3:
        //         $estado = 10; // Empacado
        //         break;
        //     case 4:
        //         $estado = 1; // Despachadas
        //         break;
        //     case 5:
        //         $estado = [11, 12, 10, 2]; // Todos los estados
        //         break;
        //     default:
        //         $estado = 2; // Por defecto, estado pendiente
        //         break;
        // }

        // // Recibir el año y el mes desde el request
        // $anio = $request->anio;
        // $mes = $request->mes;

        // // Consulta principal
        // $query = DB::SELECT("SELECT f.*,
        //         tv.tip_ven_nombre,
        //         i.nombreInstitucion,
        //         us.cedula,
        //         us.email,
        //         us.telefono,
        //         i.telefonoInstitucion,
        //         i.direccionInstitucion,
        //         i.asesor_id,
        //         CONCAT(usa.nombres,' ',usa.apellidos) AS asesor,
        //         em.nombre,
        //         pe.periodoescolar,
        //         CONCAT(u.nombres,' ',u.apellidos) AS responsable,
        //         CONCAT(us.nombres,' ',us.apellidos) AS cliente,
        //         pr.pedido_id,
        //         pr.idPuntoventa,
        //         pr.prof_observacion,
        //         pl.id_pedido AS pedido_id,
        //         SUM(dv.det_ven_cantidad) AS cantidad_despacho,
        //         pl.porcentaje_descuento,
        //         camb.ven_codigo as documentoOrigen, camb.idtipodoc as tipoDocumentoOrigen
        //     FROM f_venta f
        //     INNER JOIN 1_4_tipo_venta tv ON f.tip_ven_codigo = tv.tip_ven_codigo
        //     INNER JOIN institucion i ON f.institucion_id = i.idInstitucion
        //     INNER JOIN empresas em ON f.id_empresa = em.id
        //     INNER JOIN periodoescolar pe ON f.periodo_id = pe.idperiodoescolar
        //     INNER JOIN usuario u ON f.user_created = u.idusuario
        //     LEFT JOIN usuario us ON f.ven_cliente = us.idusuario
        //     LEFT JOIN pedidos_beneficiarios pb ON f.ven_cliente = pb.id_beneficiario_pedido
        //     LEFT JOIN usuario user ON pb.id_usuario = user.idusuario
        //     INNER JOIN f_detalle_venta dv ON f.ven_codigo = dv.ven_codigo AND f.id_empresa = dv.id_empresa
        //     LEFT JOIN usuario usa ON i.asesor_id = usa.idusuario
        //     LEFT JOIN f_proforma pr ON pr.prof_id = f.ven_idproforma
        //     LEFT JOIN p_libros_obsequios pl ON pl.id = f.ven_p_libros_obsequios
        //     LEFT JOIN f_venta camb ON camb.doc_intercambio = f.ven_codigo  AND camb.id_empresa = f.id_empresa
        //     WHERE f.est_ven_codigo " . (is_array($estado) ? 'IN (' . implode(',', $estado) . ')' : '= ' . $estado) . "
        //     AND NOT (f.idtipodoc IN (3, 4) AND f.doc_intercambio IS NOT NULL)
        //     " . ($anio ? " AND YEAR(f.ven_fecha) = $anio" : "") . "
        //     " . ($mes ? " AND MONTH(f.ven_fecha) = $mes" : "") . "
        //     GROUP BY f.ven_codigo, f.id_empresa, f.tip_ven_codigo, i.nombreInstitucion, us.cedula, us.email,
        //             us.telefono, i.telefonoInstitucion, i.direccionInstitucion, i.asesor_id,
        //             CONCAT(usa.nombres,' ',usa.apellidos), em.nombre,
        //             pe.periodoescolar, CONCAT(u.nombres,' ',u.apellidos),
        //             CONCAT(us.nombres,' ',us.apellidos),
        //             pr.pedido_id, pr.idPuntoventa, pr.prof_observacion, pl.id_pedido
        //     ORDER BY COALESCE(f.fecha_proceso_despacho, f.ven_fecha) DESC
        // ");

        // Definir el estado basado en la opción proporcionada
        $estado = null;
        switch ($request->op) {
            case 0:
                $estado = 2; // Pendientes
                break;
            case 1:
                $estado = 11; // Excluir
                break;
            case 2:
                $estado = 12; // Preparado
                break;
            case 3:
                $estado = 10; // Empacado
                break;
            case 4:
                $estado = 1; // Despachadas
                break;
            case 5:
                $estado = [11, 12, 10, 2]; // Todos los estados
                break;
            default:
                $estado = 2; // Por defecto, estado pendiente
                break;
        }

        // Recibir los parámetros desde el request
        $anio = $request->anio;
        $mes = $request->mes;
        $sinfecha = $request->sinfecha ?? 0;

        // Construir condiciones de fecha si sinfecha es 0
        $condicionFecha = '';
        if ($sinfecha == 0) {
            if ($anio) {
                $condicionFecha .= " AND YEAR(f.ven_fecha) = $anio";
            }
            if ($mes) {
                $condicionFecha .= " AND MONTH(f.ven_fecha) = $mes";
            }
        }

        // Consulta principal
        $query = DB::SELECT("SELECT f.*,
                tv.tip_ven_nombre,
                i.nombreInstitucion,
                us.cedula,
                us.email,
                us.telefono,
                i.telefonoInstitucion,
                i.direccionInstitucion,
                i.asesor_id,
                CONCAT(usa.nombres,' ',usa.apellidos) AS asesor,
                em.nombre,
                pe.periodoescolar,
                CONCAT(u.nombres,' ',u.apellidos) AS responsable,
                CONCAT(us.nombres,' ',us.apellidos) AS cliente,
                pr.pedido_id,
                pr.idPuntoventa,
                pr.prof_observacion,
                pl.id_pedido AS pedido_id,
                SUM(dv.det_ven_cantidad) AS cantidad_despacho,
                pl.porcentaje_descuento,
                camb.ven_codigo as documentoOrigen, camb.idtipodoc as tipoDocumentoOrigen
            FROM f_venta f
            INNER JOIN 1_4_tipo_venta tv ON f.tip_ven_codigo = tv.tip_ven_codigo
            INNER JOIN institucion i ON f.institucion_id = i.idInstitucion
            INNER JOIN empresas em ON f.id_empresa = em.id
            INNER JOIN periodoescolar pe ON f.periodo_id = pe.idperiodoescolar
            INNER JOIN usuario u ON f.user_created = u.idusuario
            LEFT JOIN usuario us ON f.ven_cliente = us.idusuario
            LEFT JOIN pedidos_beneficiarios pb ON f.ven_cliente = pb.id_beneficiario_pedido
            LEFT JOIN usuario user ON pb.id_usuario = user.idusuario
            INNER JOIN f_detalle_venta dv ON f.ven_codigo = dv.ven_codigo AND f.id_empresa = dv.id_empresa
            LEFT JOIN usuario usa ON i.asesor_id = usa.idusuario
            LEFT JOIN f_proforma pr ON pr.prof_id = f.ven_idproforma
            LEFT JOIN p_libros_obsequios pl ON pl.id = f.ven_p_libros_obsequios
            LEFT JOIN f_venta camb ON camb.doc_intercambio = f.ven_codigo  AND camb.id_empresa = f.id_empresa
            WHERE f.est_ven_codigo " . (is_array($estado) ? 'IN (' . implode(',', $estado) . ')' : '= ' . $estado) . "
            AND NOT (f.idtipodoc IN (3, 4) AND f.doc_intercambio IS NOT NULL)
            $condicionFecha
            GROUP BY f.ven_codigo, f.id_empresa, f.tip_ven_codigo, i.nombreInstitucion, us.cedula, us.email,
                    us.telefono, i.telefonoInstitucion, i.direccionInstitucion, i.asesor_id,
                    CONCAT(usa.nombres,' ',usa.apellidos), em.nombre,
                    pe.periodoescolar, CONCAT(u.nombres,' ',u.apellidos),
                    CONCAT(us.nombres,' ',us.apellidos),
                    pr.pedido_id, pr.idPuntoventa, pr.prof_observacion, pl.id_pedido
            ORDER BY COALESCE(f.fecha_proceso_despacho, f.ven_fecha) DESC
        ");

        // Adicionar la lógica para obtener los contratos
        foreach ($query as $key => $item) {
            if ($item->idPuntoventa) {
                $quer1 = DB::SELECT("SELECT pe.contrato_generado FROM pedidos pe WHERE pe.ca_codigo_agrupado = '$item->idPuntoventa'");
                $query[$key]->contratos = $quer1;
            } else {
                if ($item->pedido_id) {
                    $quer1 = DB::SELECT("SELECT pe.contrato_generado FROM pedidos pe WHERE pe.id_pedido = '$item->pedido_id'");
                    $query[$key]->contratos = $quer1;
                }else{
                    $query[$key]->contratos = [];
                }
            }
            //reempacado
            $getReempacado = DB::SELECT("SELECT r.id as id_remision,r.remi_fecha_inicio, r.remi_codigo, r.remi_idempresa,
            r.remi_num_factura, r.remi_guia_remision, r.archivo, r.url, r.remi_estado
            FROM empacado_remision r
            WHERE r.remi_num_factura = '$item->ven_codigo'
            AND r.remi_estado <> '0'
            ");
            if(count($getReempacado) > 0){
                  // Detalle de empaque
                $detalleEmpaque = DB::table('empacado_rempacado_detalle')
                ->where('empacado_rempacado_id', $getReempacado[0]->id_remision)
                ->where('idempresa', $getReempacado[0]->remi_idempresa)
                ->sum('cantidad');

                $getReempacado[0]->cantidad = $detalleEmpaque;

                // Detalle de unidades libros de pre-factura
                $detalleLibros = DB::table('f_detalle_venta')
                    ->where('ven_codigo', $getReempacado[0]->remi_num_factura)
                    ->where('id_empresa', $getReempacado[0]->remi_idempresa)
                    ->select(DB::raw('sum(det_ven_cantidad_despacho) as libros'))
                    ->get();
                if(count($detalleLibros) > 0){
                    $getReempacado[0]->libros = $detalleLibros[0]->libros;
                }else{
                    $getReempacado[0]->libros = 0;
                }
                $query[$key]->empacado = $getReempacado;
            }else{
                $query[$key]->empacado = [];
            }
        }

        if($request->contarArray){
            return count($query);
        }
        return $query;
    }


    public function GetVentasDespachadasxParametro(Request $request){
        if($request->opcion==0){
            $query = DB::select("SELECT f.*,
                tv.tip_ven_nombre,
                i.nombreInstitucion,
                us.cedula,
                us.email,
                i.telefonoInstitucion,
                i.direccionInstitucion,
                em.nombre,
                user.cedula AS cedula1,
                pe.periodoescolar,
                CONCAT(u.nombres,' ',u.apellidos) AS responsable,
                CONCAT(us.nombres,' ',us.apellidos) AS cliente,
                CONCAT(user.nombres,' ',user.apellidos) AS cliente1,
                CONCAT(interc.nombres,' ',interc.apellidos) AS user_intercambio,
                pr.pedido_id,
                pr.idPuntoventa,
                pl.id_pedido AS pedido_id,
                SUM(dv.det_ven_cantidad) AS cantidad_despacho,
                CONCAT(ua.nombres,' ',ua.apellidos) AS usuario_anulado
                FROM f_venta f
                INNER JOIN 1_4_tipo_venta tv ON f.tip_ven_codigo = tv.tip_ven_codigo
                INNER JOIN institucion i ON f.institucion_id = i.idInstitucion
                INNER JOIN empresas em ON f.id_empresa = em.id
                INNER JOIN periodoescolar pe ON f.periodo_id = pe.idperiodoescolar
                INNER JOIN usuario u ON f.user_created = u.idusuario
                INNER JOIN usuario us ON f.ven_cliente = us.idusuario
                LEFT JOIN pedidos_beneficiarios pb ON f.ven_cliente = pb.id_beneficiario_pedido
                LEFT JOIN usuario user ON pb.id_usuario = user.idusuario
                INNER JOIN f_detalle_venta dv ON f.ven_codigo = dv.ven_codigo AND f.id_empresa = dv.id_empresa
                LEFT JOIN f_proforma pr ON pr.prof_id = f.ven_idproforma
                LEFT JOIN p_libros_obsequios pl ON pl.id = f.ven_p_libros_obsequios
                LEFT JOIN usuario AS interc ON f.user_intercambio = interc.idusuario
                LEFT JOIN usuario ua ON f.user_anulado = ua.idusuario
                WHERE (f.ven_codigo LIKE ? OR f.ven_cliente LIKE ? OR i.nombreInstitucion LIKE ?)
                GROUP BY f.ven_codigo, f.id_empresa, f.tip_ven_codigo, i.nombreInstitucion, us.cedula, us.email,
                    us.telefono, i.telefonoInstitucion, i.direccionInstitucion, i.asesor_id,
                    CONCAT(u.nombres,' ',u.apellidos), em.nombre, user.cedula,
                    pe.periodoescolar, CONCAT(u.nombres,' ',u.apellidos),
                    CONCAT(us.nombres,' ',us.apellidos), CONCAT(user.nombres,' ',user.apellidos),
                    pr.pedido_id, pr.idPuntoventa, pr.prof_observacion, pl.id_pedido
                ORDER BY f.ven_fecha
            ", ['%' . $request->parte_documento . '%', '%' . $request->parte_documento . '%', '%' . $request->parte_documento . '%']);

            foreach ($query as $key => $item) {
                if ($item->idPuntoventa) {
                    // Usamos un solo SELECT para evitar ejecutar múltiples consultas
                    $quer1 = DB::select("SELECT pe.contrato_generado FROM pedidos pe WHERE pe.ca_codigo_agrupado = ?", [$item->idPuntoventa]);
                    $query[$key]->contratos = $quer1;
                } else {
                    if ($item->pedido_id) {
                        // Usamos un solo SELECT para evitar ejecutar múltiples consultas
                        $quer1 = DB::select("SELECT pe.contrato_generado FROM pedidos pe WHERE pe.id_pedido = ?", [$item->pedido_id]);
                        $query[$key]->contratos = $quer1;
                    }else{
                        $query[$key]->contratos = [];
                    }
                }
                  //reempacado
                $getReempacado = DB::SELECT("SELECT r.id as id_remision,
                r.remi_fecha_inicio, r.remi_codigo
                FROM empacado_remision r
                WHERE r.remi_num_factura = '$item->ven_codigo'
                AND r.remi_estado <> '0'
                ");
                if(count($getReempacado) > 0){
                    $query[$key]->empacado = $getReempacado;
                }else{
                    $query[$key]->empacado = [];
                }
            }

        }else if($request->opcion==1){
            $query=DB::SELECT("SELECT f.*, tv.tip_ven_nombre,i.nombreInstitucion,
               us.cedula,
               us.email,
               i.telefonoInstitucion,
               i.direccionInstitucion,
               em.nombre,
               user.cedula as cedula1,
               pe.periodoescolar,
               CONCAT(u.nombres,' ',u.apellidos) AS responsable,
               CONCAT(us.nombres,' ',us.apellidos) AS cliente,
               CONCAT(user.nombres,' ',user.apellidos) AS cliente1,
               CONCAT(interc.nombres,' ',interc.apellidos) AS user_intercambio,
               pr.pedido_id,
               pr.idPuntoventa,
               pl.id_pedido AS pedido_id,
               camb.ven_codigo as documentoOrigen, camb.idtipodoc as tipoDocumentoOrigen,
              SUM(dv.det_ven_cantidad) AS cantidad_despacho,
              CONCAT(au.nombres,' ',au.apellidos) AS usuario_anulado
              FROM f_venta f
              INNER JOIN 1_4_tipo_venta tv ON f.tip_ven_codigo = tv.tip_ven_codigo
              INNER JOIN institucion i ON f.institucion_id = i.idInstitucion
              INNER JOIN empresas em ON f.id_empresa = em.id
              INNER JOIN periodoescolar pe ON f.periodo_id = pe.idperiodoescolar
              INNER JOIN usuario u ON f.user_created = u.idusuario
              INNER JOIN usuario us ON f.ven_cliente = us.idusuario
              LEFT JOIN pedidos_beneficiarios pb ON f.ven_cliente =pb.id_beneficiario_pedido
              LEFT JOIN usuario user ON pb.id_usuario = user.idusuario
              INNER JOIN f_detalle_venta dv ON f.ven_codigo = dv.ven_codigo AND f.id_empresa = dv.id_empresa
              LEFT JOIN  f_proforma  pr on pr.prof_id=f.ven_idproforma
              LEFT JOIN p_libros_obsequios pl ON pl.id=f.ven_p_libros_obsequios
              LEFT JOIN usuario as interc ON f.user_intercambio = interc.idusuario
              LEFT JOIN usuario au ON au.idusuario = f.user_anulado
              LEFT JOIN f_venta camb ON camb.doc_intercambio = f.ven_codigo  AND camb.id_empresa = f.id_empresa
              WHERE (f.ven_codigo like'%$request->parte_documento%' or f.ruc_cliente like'%$request->parte_documento%' OR i.nombreInstitucion like'%$request->parte_documento%')
              and f.periodo_id=$request->periodo
              GROUP BY f.ven_codigo, f.id_empresa, f.tip_ven_codigo, i.nombreInstitucion, us.cedula, us.email,
             us.telefono, i.telefonoInstitucion, i.direccionInstitucion, i.asesor_id,
             CONCAT(u.nombres,' ',u.apellidos), em.nombre, user.cedula,
             pe.periodoescolar, CONCAT(u.nombres,' ',u.apellidos),
             CONCAT(us.nombres,' ',us.apellidos), CONCAT(user.nombres,' ',user.apellidos),
             pr.pedido_id, pr.idPuntoventa, pr.prof_observacion, pl.id_pedido
              order by f.ven_fecha");
            foreach($query as $key => $item){
                if($item->idPuntoventa){
                    $quer1=DB::SELECT("select pe.contrato_generado from pedidos pe where pe.ca_codigo_agrupado= '$item->idPuntoventa'");
                    $query[$key]->contratos =$quer1;
                }else{
                    if($item->pedido_id){
                        $quer1=DB::SELECT("select pe.contrato_generado from pedidos pe where pe.id_pedido= '$item->pedido_id'");
                        $query[$key]->contratos =$quer1;
                    }else{
                        $query[$key]->contratos = [];
                    }
                }
                //reempacado
                $getReempacado = DB::SELECT("SELECT r.id as id_remision,
                r.remi_fecha_inicio, r.remi_codigo
                FROM empacado_remision r
                WHERE r.remi_num_factura = '$item->ven_codigo'
                AND r.remi_estado <> '0'
                ");
                if(count($getReempacado) > 0){
                    $query[$key]->empacado = $getReempacado;
                }else{
                    $query[$key]->empacado = [];
                }
            }
        }
        else if($request->opcion==2){
            $query=DB::SELECT("SELECT f.*, tv.tip_ven_nombre,i.nombreInstitucion,
               us.cedula,
               us.email,
               i.telefonoInstitucion,
               i.direccionInstitucion,
               em.nombre,
               user.cedula as cedula1,
               pe.periodoescolar,
               CONCAT(u.nombres,' ',u.apellidos) AS responsable,
               CONCAT(us.nombres,' ',us.apellidos) AS cliente,
               CONCAT(user.nombres,' ',user.apellidos) AS cliente1,
               CONCAT(interc.nombres,' ',interc.apellidos) AS user_intercambio,
               pr.pedido_id,
               pr.idPuntoventa,
               pl.id_pedido AS pedido_id,
               camb.ven_codigo as documentoOrigen, camb.idtipodoc as tipoDocumentoOrigen,
              SUM(dv.det_ven_cantidad) AS cantidad_despacho,
              CONCAT(au.nombres,' ',au.apellidos) AS usuario_anulado
              FROM f_venta f
              INNER JOIN 1_4_tipo_venta tv ON f.tip_ven_codigo = tv.tip_ven_codigo
              INNER JOIN institucion i ON f.institucion_id = i.idInstitucion
              INNER JOIN empresas em ON f.id_empresa = em.id
              INNER JOIN periodoescolar pe ON f.periodo_id = pe.idperiodoescolar
              INNER JOIN usuario u ON f.user_created = u.idusuario
              INNER JOIN usuario us ON f.ven_cliente = us.idusuario
              LEFT JOIN pedidos_beneficiarios pb ON f.ven_cliente =pb.id_beneficiario_pedido
              LEFT JOIN usuario user ON pb.id_usuario = user.idusuario
              INNER JOIN f_detalle_venta dv ON f.ven_codigo = dv.ven_codigo AND f.id_empresa = dv.id_empresa
              LEFT JOIN  f_proforma  pr on pr.prof_id=f.ven_idproforma
              LEFT JOIN p_libros_obsequios pl ON pl.id=f.ven_p_libros_obsequios
              LEFT JOIN usuario as interc ON f.user_intercambio = interc.idusuario
              LEFT JOIN usuario au ON au.idusuario = f.user_anulado
              LEFT JOIN f_venta camb ON camb.doc_intercambio = f.ven_codigo  AND camb.id_empresa = f.id_empresa
              WHERE (f.ven_codigo like'%$request->parte_documento%' or f.ruc_cliente like'%$request->parte_documento%')
              GROUP BY f.ven_codigo, f.id_empresa, f.tip_ven_codigo, i.nombreInstitucion, us.cedula, us.email,
             us.telefono, i.telefonoInstitucion, i.direccionInstitucion, i.asesor_id,
             CONCAT(u.nombres,' ',u.apellidos), em.nombre, user.cedula,
             pe.periodoescolar, CONCAT(u.nombres,' ',u.apellidos),
             CONCAT(us.nombres,' ',us.apellidos), CONCAT(user.nombres,' ',user.apellidos),
             pr.pedido_id, pr.idPuntoventa, pr.prof_observacion, pl.id_pedido
              order by f.ven_fecha");
            foreach($query as $key => $item){
                if($item->idPuntoventa){
                    $quer1=DB::SELECT("select pe.contrato_generado from pedidos pe where pe.ca_codigo_agrupado= '$item->idPuntoventa'");
                    $query[$key]->contratos =$quer1;
                }else{
                    if($item->pedido_id){
                        $quer1=DB::SELECT("select pe.contrato_generado from pedidos pe where pe.id_pedido= '$item->pedido_id'");
                        $query[$key]->contratos =$quer1;
                    }else{
                        $query[$key]->contratos = [];
                    }
                }
                //reempacado
                $getReempacado = DB::SELECT("SELECT r.id as id_remision,
                r.remi_fecha_inicio, r.remi_codigo
                FROM empacado_remision r
                WHERE r.remi_num_factura = '$item->ven_codigo'
                AND r.remi_estado <> '0'
                ");
                if(count($getReempacado) > 0){
                    $query[$key]->empacado = $getReempacado;
                }else{
                    $query[$key]->empacado = [];
                }
            }
        }
        return $query;

    }
    public function GetDetalleVentaxNumeroDocumentoParametro(Request $request){
        $query = DB::SELECT("SELECT dv.*, p.pro_nombre FROM f_detalle_venta dv
        INNER JOIN 1_4_cal_producto p ON dv.pro_codigo = p.pro_codigo
        WHERE dv.ven_codigo = '$request->numero_documento'");
        return $query;
    }
    public function get_facturasNotasxParametro(Request $request){
        $query = DB::SELECT("SELECT fv.* FROM f_venta fv
        WHERE fv.institucion_id='$request->institucion'
        AND fv.periodo_id='$request->periodo'
        AND fv.id_empresa='$request->empresa'
        AND fv.est_ven_codigo <> 3");
        return $query;
    }
    public function InstitucionesDesp(Request $request){
        if($request->op==0){
            $query = DB::SELECT("SELECT inst.idInstitucion, inst.*, ci.nombre AS ciudad, CONCAT(usa.nombres,' ', usa.apellidos) AS cliente,fv_filtered.id_empresa
            FROM institucion inst
            INNER JOIN (
                SELECT DISTINCT id_institucion, id_empresa
                FROM f_venta
                WHERE id_empresa = $request->id AND (est_ven_codigo = 10 OR est_ven_codigo = 2)
            ) fv_filtered ON fv_filtered.id_institucion = inst.idInstitucion
            INNER JOIN usuario usa ON usa.idusuario = (SELECT ven_cliente FROM f_venta WHERE id_institucion = inst.idInstitucion LIMIT 1)
            INNER JOIN ciudad ci ON ci.idciudad = inst.ciudad_id;");
            // SELECT inst.*, fv.id_empresa, ci.nombre AS ciudad, CONCAT(usa.nombres, usa.apellidos) AS cliente FROM institucion inst
            // INNER JOIN f_venta  fv ON fv.institucion_id=inst.idInstitucion
            // INNER JOIN usuario usa ON usa.idusuario=fv.ven_cliente
            // INNER JOIN ciudad ci ON ci.idciudad=inst.ciudad_id
            // WHERE fv.id_empresa=$request->id and (fv.est_ven_codigo=10 OR fv.est_ven_codigo=2)
            // GROUP BY inst.idInstitucion
            // SELECT inst.*, fv.id_empresa, ci.nombre AS ciudad, CONCAT(usa.nombres, usa.apellidos) AS cliente FROM institucion inst
            // INNER JOIN f_venta  fv ON fv.institucion_id=inst.idInstitucion
            // INNER JOIN usuario usa ON usa.idusuario=fv.ven_cliente
            // INNER JOIN ciudad ci ON ci.idciudad=inst.ciudad_id
            // WHERE fv.id_empresa=$request->id and (fv.est_ven_codigo=11)
            // GROUP BY inst.idInstitucion
        }
        if($request->op==1){
            $query = DB::SELECT("SELECT inst.idInstitucion, inst.*, ci.nombre AS ciudad, CONCAT(usa.nombres,' ', usa.apellidos) AS cliente
            FROM institucion inst
            INNER JOIN (
                SELECT DISTINCT id_institucion, id_empresa
                FROM f_venta
                WHERE id_empresa = $request->id AND (est_ven_codigo = 11)
            ) fv_filtered ON fv_filtered.id_institucion = inst.idInstitucion
            INNER JOIN usuario usa ON usa.idusuario = (SELECT ven_cliente FROM f_venta WHERE id_institucion = inst.idInstitucion LIMIT 1)
            INNER JOIN ciudad ci ON ci.idciudad = inst.ciudad_id;");
        }

            return $query;
    }
    public function GetVentasP(Request $request){
        if($request->op==0){
            $query = DB::SELECT("SELECT fv.*, COUNT(dv.pro_codigo) AS item, SUM(dv.det_ven_cantidad) AS libros, SUM(dv.det_ven_cantidad_despacho) AS despacho   FROM f_venta  fv
            INNER JOIN f_detalle_venta dv ON fv.ven_codigo=dv.ven_codigo
            WHERE fv.id_institucion=$request->ventaId AND fv.id_empresa=dv.id_empresa
            and (fv.est_ven_codigo=2 or fv.est_ven_codigo=10)
            GROUP BY fv.ven_codigo, fv.id_empresa");
        }
        if($request->op==1){
            $query = DB::SELECT("SELECT fv.*, COUNT(dv.pro_codigo) AS item, SUM(dv.det_ven_cantidad) AS libros, SUM(dv.det_ven_cantidad_despacho) AS despacho   FROM f_venta  fv
            INNER JOIN f_detalle_venta dv ON fv.ven_codigo=dv.ven_codigo
            WHERE fv.id_institucion=$request->ventaId AND fv.id_empresa=dv.id_empresa
            and fv.est_ven_codigo=11 and dv.det_ven_cantidad<>dv.det_ven_cantidad_despacho
            GROUP BY fv.ven_codigo, fv.id_empresa");
        }
                   return $query;
    }
    public function getDventas(Request $request){
        // $query = DB::SELECT("SELECT dv.*,
        //         CASE
        //             WHEN dv.det_ven_cantidad_despacho = 0 THEN dv.det_ven_cantidad
        //             ELSE dv.det_ven_cantidad_despacho
        //         END as despacho,
        //         (dv.det_ven_cantidad - dv.det_ven_cantidad_despacho) as cantidad_pendiente,
        //         pro.*
        //     FROM f_detalle_venta dv
        //     INNER JOIN 1_4_cal_producto pro ON pro.pro_codigo = dv.pro_codigo
        //     WHERE dv.ven_codigo = '$request->id'
        //     AND dv.id_empresa = $request->idemp
        //     ORDER BY dv.pro_codigo
        // ");
        $query = DB::SELECT("SELECT dv.*,
                (dv.det_ven_cantidad - dv.det_ven_cantidad_despacho) as despacho,
                (dv.det_ven_cantidad - dv.det_ven_cantidad_despacho) as cantidad_pendiente,
                pro.*
            FROM f_detalle_venta dv
            INNER JOIN 1_4_cal_producto pro ON pro.pro_codigo = dv.pro_codigo
            WHERE dv.ven_codigo = '$request->id'
            AND dv.id_empresa = $request->idemp
            ORDER BY dv.pro_codigo
        ");
        return $query;

    }

    //agrupados para pedidos
    public function Postventa_factura(Request $request){
        try {
            set_time_limit(6000000);
            ini_set('max_execution_time', 6000000);
            $miarray = json_decode($request->data_detalle);
            $mia = json_decode($request->data_ventas);
            DB::beginTransaction();
            //si el ven_codigo ya existe no creo
            $query = DB::SELECT("SELECT id_factura FROM f_venta_agrupado WHERE id_factura='$request->ven_codigo'");
            if(!empty($query)){
                return response()->json(["status" => "2", "message" => "El ven_codigo ya existe"],0);
            }else{
                $venta                      = new VentasF;
                $venta->id_factura          = $request->ven_codigo;
                $venta->id_empresa          = $request->id_empresa;
                $venta->ven_desc_por        = $request->ven_desc_por;
                $venta->ven_iva_por         = $request->ven_iva_por;
                $venta->ven_descuento       = $request->ven_descuento;
                $venta->ven_iva             = $request->ven_iva;
                $venta->ven_transporte      = $request->ven_transporte;
                $venta->ven_valor           = $request->ven_valor;
                $venta->ven_subtotal        = $request->ven_subtotal;
                $venta->ven_devolucion      = $request->ven_devolucion;
                $venta->ven_pagado          = $request->ven_pagado;
                $venta->institucion_id      = $request->id_ins_depacho;
                $venta->periodo_id          = $request->periodo_id;
                $venta->idtipodoc           = 11;
                $venta->ven_cliente         = $request->ven_cliente;
                $venta->clientesidPerseo    = $request->clientesidPerseo;
                $venta->ven_fecha           = now();
                $venta->user_created        = $request->user_created;
                $venta->updated_at          = now();
                $venta->save();
                if($request->id_empresa==1){
                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from  f_tipo_documento where tdo_id=11");
                }else if ($request->id_empresa==3){
                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from  f_tipo_documento where tdo_id=11");
                }
                $id=$query1[0]->id;
                $codi=$query1[0]->cod;
                $co=(int)$codi+1;
                $tipo_doc = f_tipo_documento::findOrFail($id);
                if($request->id_empresa==1){
                    $tipo_doc->tdo_secuencial_Prolipa = $co;
                }else if ($request->id_empresa==3){
                    $tipo_doc->tdo_secuencial_calmed = $co;
                }
                $tipo_doc->save();
                foreach($miarray as $key => $item){
                    if($item->cantidad > 0){
                        $venta1=new DetalleVentasF;
                        $venta1->id_factura         = $request->ven_codigo;
                        $venta1->id_empresa         = $request->id_empresa;
                        $venta1->pro_codigo         = $item->pro_codigo;
                        $venta1->det_ven_cantidad   = $item->cantidad;
                        $venta1->det_ven_valor_u    = $item->det_ven_valor_u;
                        $venta1->save();
                    }
                }
                foreach($mia as $key => $it){
                    if($it->ven_codigo){
                        $ventas = Ventas::where('ven_codigo', $it->ven_codigo)
                         ->where('id_empresa', $request->id_empresa)->firstOrFail();
                        $ventas->id_factura  = $request->ven_codigo;
                        $ventas->save();
                    }
                }
            }
            //finalizar documento
           DB::commit();
            return response()->json(["status" => "200",'message' => 'Documento creado con éxito'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["error" => "500", "message" => "No se pudo guardar", "exception" => $e->getMessage()],500);
        }
    }

    public function PostFacturarReal(Request $request)
    {
        // Validar que tip_ven_codigo sea requerido
        if (!$request->has('tip_ven_codigo') || $request->tip_ven_codigo === null || $request->tip_ven_codigo === '') {
            return response()->json([
                "status" => "0",
                "message" => "El tipo de venta es requerido. Por favor seleccione el tipo de venta."
            ], 400);
        }

        try {
            $miarray = json_decode($request->data_detalle);
            $maxIntentos = 5; // Máximo número de intentos
            $intento = 0;
            $codigoOriginal = $request->ven_codigo;

            while ($intento < $maxIntentos) {
                DB::beginTransaction();

                try {
                    // Verificar si el ven_codigo ya existe
                    $query = DB::SELECT("SELECT id_factura FROM f_venta_agrupado WHERE id_factura = ?", [$request->ven_codigo]);

                    if (!empty($query)) {
                        // Si existe, generar nuevo código y reintentar
                        $request->ven_codigo = $this->generarNuevoCodigo($request);
                        $intento++;
                        DB::rollback();
                        continue; // Reintentar con el nuevo código
                    }

                    // Crear la venta con el código verificado
                    $venta = new VentasF;
                    $venta->id_factura = $request->ven_codigo;
                    $venta->id_empresa = $request->id_empresa;
                    $venta->ven_desc_por = $request->ven_desc_por;
                    $venta->ven_iva_por = $request->ven_iva_por;
                    $venta->ven_descuento = $request->ven_descuento;
                    $venta->ven_iva = $request->ven_iva;
                    $venta->ven_transporte = $request->ven_transporte;
                    $venta->ven_valor = $request->ven_valor;
                    $venta->ven_subtotal = $request->ven_subtotal;
                    $venta->ven_devolucion = 0;
                    $venta->ven_pagado = 0;
                    $venta->institucion_id = $request->id_ins_depacho;
                    $venta->periodo_id = $request->periodo_id;
                    $venta->idtipodoc = 11;
                    $venta->ven_cliente = $request->ven_cliente;
                    $venta->clientesidPerseo = $request->clientesidPerseo;
                    $venta->ven_fecha = $request->ven_fecha;
                    $venta->user_created = $request->user_created;
                    $venta->tip_ven_codigo = $request->tip_ven_codigo;
                    $venta->updated_at = now();
                    // Agregar campos de facturación cruzada
                    $venta->factura_cruzada = $request->factura_cruzada ?? 0;
                    $venta->empresa_cruzada = $request->empresa_cruzada;
                    $venta->save();

                    // Actualizar secuencial solo si la venta se guardó exitosamente
                    if($request->id_empresa==1){
                        $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_id=11");
                    }else if ($request->id_empresa==3){
                        $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_id=11");
                    }
                    $id=$query1[0]->id;
                    $codi=$query1[0]->cod;
                    $co=(int)$codi+1;
                    $tipo_doc = f_tipo_documento::findOrFail($id);
                    if($request->id_empresa==1){
                        $tipo_doc->tdo_secuencial_Prolipa = $co;
                    }else if ($request->id_empresa==3){
                        $tipo_doc->tdo_secuencial_calmed = $co;
                    }
                    $tipo_doc->save();

                    // Guardar los detalles de la venta
                    foreach ($miarray as $item) {
                        if ($item->cantidad_real_facturar > 0) {
                            $venta1 = new DetalleVentasF;
                            $venta1->id_factura = $request->ven_codigo;
                            $venta1->id_empresa = $request->id_empresa;
                            $venta1->pro_codigo = $item->pro_codigo;
                            $venta1->det_ven_cantidad = $item->cantidad_real_facturar;
                            $venta1->det_ven_valor_u = $item->det_ven_valor_u;
                            $venta1->save();
                        }
                    }

                    // Si llegamos aquí, todo salió bien
                    DB::commit();
                    return response()->json([
                        "status" => "200",
                        'message' => 'Documento creado con éxito',
                        'codigo_creado' => $request->ven_codigo
                    ], 200);

                } catch (\Exception $e) {
                    DB::rollback();
                    throw $e; // Re-lanzar para el catch exterior
                }
            }

            // Si llegamos aquí, se agotaron los intentos
            return response()->json([
                "status" => "500",
                "message" => "No se pudo generar un código único después de $maxIntentos intentos"
            ], 500);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "error" => "500",
                "message" => "No se pudo guardar",
                "exception" => $e->getMessage(),
                "line" => $e->getLine()
            ], 500);
        }
    }

    private function generarNuevoCodigo($request)
    {
        // Obtener el siguiente secuencial real de la base de datos
        if($request->id_empresa == 1){
            $query = DB::SELECT("SELECT tdo_secuencial_Prolipa as secuencial FROM f_tipo_documento WHERE tdo_id = 11");
            $siguienteSecuencial = $query[0]->secuencial + 1;
        } else if($request->id_empresa == 3){
            $query = DB::SELECT("SELECT tdo_secuencial_calmed as secuencial FROM f_tipo_documento WHERE tdo_id = 11");
            $siguienteSecuencial = $query[0]->secuencial + 1;
        }

        // Parsear el código para reemplazar solo la parte del secuencial
        $partescodigo = explode('-', $request->ven_codigo);

        if (count($partescodigo) >= 5) {
            // La última parte es el secuencial (0000085)
            $secuencialFormateado = str_pad($siguienteSecuencial, 7, '0', STR_PAD_LEFT);
            $partescodigo[4] = $secuencialFormateado; // Reemplazar la última parte

            return implode('-', $partescodigo);
        }

        // Fallback si no se puede parsear
        return $request->ven_codigo . '-' . time();
    }



    public function prefacturasCliente(Request $request){
        $query = DB::SELECT("SELECT DISTINCT i.nombreInstitucion, i.idInstitucion FROM institucion i
        INNER JOIN f_venta fv ON i.idInstitucion = fv.institucion_id
        WHERE i.nombreInstitucion  LIKE '%$request->busqueda%'");

        // $query = DB::SELECT("SELECT fv.*, CONCAT(usu.nombres, '', usu.apellidos) AS clienteUsuario, i.nombreInstitucion AS clienteInstitucion FROM f_venta fv
        // INNER JOIN usuario usu ON usu.cedula  = fv.ruc_cliente
        // INNER JOIN institucion i ON i.idInstitucion = fv.institucion_id
        // WHERE i.nombreInstitucion  LIKE '%$request->busqueda%'");
        return $query;
    }
    public function getAllPrefacturas(Request $request) {
        $ventas = DB::table('f_venta as fv')
            ->join('empresas as e', 'fv.id_empresa', '=', 'e.id')
            ->join('periodoescolar as p', 'fv.periodo_id', '=', 'p.idperiodoescolar')
            ->select(
                'fv.*',
                'e.descripcion_corta',
                'p.periodoescolar'
            )
            ->where('institucion_id', $request->busqueda)
            ->where('periodo_id', $request->periodo)
            ->where('fv.idtipodoc', 1)
            ->where('fv.est_ven_codigo', '<>', 3)
            // ->whereNull('fv.id_factura')
            ->get();

        // Recopila los ruc_cliente para hacer la segunda consulta
        $rucClientes = $ventas->pluck('ruc_cliente')->unique();

        // Obtiene los usuarios y las instituciones en una sola consulta
        $usuarios = DB::table('usuario')
            ->whereIn('cedula', $rucClientes)
            ->get()
            ->keyBy('cedula');

        $instituciones = DB::table('institucion')
            ->whereIn('idInstitucion', $ventas->pluck('institucion_id')->unique())
            ->get()
            ->keyBy('idInstitucion');

        // Combina los resultados en un solo array
        foreach ($ventas as $key => $venta) {
            $usuario = $usuarios->get($venta->ruc_cliente);
            $institucion = $instituciones->get($venta->institucion_id);

            // Aquí puedes agregar los datos de cliente e institución directamente a la venta
            $ventas[$key]->clienteUsuario = $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : null;
            $ventas[$key]->clienteInstitucion = $institucion ? $institucion->nombreInstitucion : null;
        }

        // Devuelve las ventas con los datos combinados
        return response()->json($ventas);
    }


    public function getAllPrefacturasXCliente(Request $request) {
        $ventas = DB::table('f_venta as fv')
            ->join('empresas as e', 'fv.id_empresa', '=', 'e.id')
            ->join('periodoescolar as p', 'fv.periodo_id', '=', 'p.idperiodoescolar')
            ->join('usuario as u', 'u.cedula', '=', 'fv.ruc_cliente')
            ->join('institucion as i', 'fv.institucion_id', '=','i.idInstitucion')
            ->select(
                'fv.*',
                DB::raw("concat(u.nombres, ' ', u.apellidos) as clienteUsuario"),
                'i.nombreInstitucion as clienteInstitucion',
                'e.descripcion_corta',
                'p.periodoescolar'
            )
            ->where('fv.ruc_cliente', $request->busqueda)
            ->where('fv.periodo_id', $request->periodo)
            ->where('fv.idtipodoc', 1)
            ->where('fv.est_ven_codigo', '<>', 3)
            ->get();

        return response()->json($ventas);
    }

    public function getAllPrefacturasNotas(Request $request) {
        $ventas = DB::table('f_venta as fv')
            ->join('empresas as e', 'fv.id_empresa', '=', 'e.id')
            ->join('periodoescolar as p', 'fv.periodo_id', '=', 'p.idperiodoescolar')
            ->select(
                'fv.*',
                'e.descripcion_corta',
                'p.periodoescolar'
            )
            ->where('institucion_id', $request->busqueda)
            ->where('periodo_id', $request->periodo)
            ->where('fv.est_ven_codigo', '<>', 3)
            ->whereIn('fv.idtipodoc', [1,3,4])
            ->get();

        // Recopila los ruc_cliente para hacer la segunda consulta
        $rucClientes = $ventas->pluck('ruc_cliente')->unique();

        // Obtiene los usuarios y las instituciones en una sola consulta
        $usuarios = DB::table('usuario')
            ->whereIn('cedula', $rucClientes)
            ->get()
            ->keyBy('cedula');

        $instituciones = DB::table('institucion')
            ->whereIn('idInstitucion', $ventas->pluck('institucion_id')->unique())
            ->get()
            ->keyBy('idInstitucion');

        // Combina los resultados en un solo array
        foreach ($ventas as $key => $venta) {
            $usuario = $usuarios->get($venta->ruc_cliente);
            $institucion = $instituciones->get($venta->institucion_id);

            // Aquí puedes agregar los datos de cliente e institución directamente a la venta
            $ventas[$key]->clienteUsuario = $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : null;
            $ventas[$key]->clienteInstitucion = $institucion ? $institucion->nombreInstitucion : null;
        }

        // Devuelve las ventas con los datos combinados
        return response()->json($ventas);
    }
    public function getCantidadFacturada(Request $request)
    {
        // Validar los parámetros de entrada
        $request->validate([
            'institucion' => 'required|integer',
            'empresa' => 'required|integer',
            'periodo' => 'required|integer',
        ]);

        // Ejecutar la consulta
        $resultados = DB::table('f_venta_agrupado as fva')
            ->join('f_detalle_venta_agrupado as fdva', 'fva.id_factura', '=', 'fdva.id_factura')
            ->select('fdva.pro_codigo', DB::raw('SUM(fdva.det_ven_cantidad) as det_ven_cantidad'))
            ->where('fva.institucion_id', $request->institucion)
            ->where('fva.id_empresa', $request->empresa)
            ->where('fva.periodo_id', $request->periodo)
            ->where('fva.est_ven_codigo',0)
            ->groupBy('fdva.pro_codigo')
            ->get();

        // Devolver los resultados
        return response()->json($resultados);
    }

    public function getCantidadFacturadaXcliente(Request $request)
    {
        // Validar los parámetros de entrada
        $request->validate([
            'cedula' => 'required',
            'empresa' => 'required|integer',
            'periodo' => 'required|integer',
        ]);

        $empresa = $request->empresa;

        $resultados = DB::table('f_venta_agrupado as fva')
            ->join('f_detalle_venta_agrupado as fdva', 'fva.id_factura', '=', 'fdva.id_factura')
            ->join('usuario as u', 'u.idusuario', '=', 'fva.ven_cliente')
            ->select('fdva.pro_codigo', DB::raw('SUM(fdva.det_ven_cantidad) as det_ven_cantidad'))
            ->where('u.cedula', $request->cedula)
            ->where('fva.periodo_id', $request->periodo)
            ->where('fva.est_ven_codigo', 0)
            ->where(function ($q) use ($empresa) {
                $q->where(function ($sub) use ($empresa) {
                    // Facturas de la empresa actual que NO son cruzadas
                    $sub->where('fva.id_empresa', $empresa)
                        ->where(function ($s) {
                            $s->whereNull('fva.factura_cruzada')
                            ->orWhere('fva.factura_cruzada', 0);
                        });
                })
                ->orWhere(function ($sub) use ($empresa) {
                    // Facturas de otras empresas cruzadas hacia la actual
                    $sub->where('fva.factura_cruzada', 1)
                        ->where('fva.empresa_cruzada', $empresa);
                });
            })
            ->groupBy('fdva.pro_codigo')
            ->get();

        // Devolver los resultados
        return response()->json($resultados);
    }

    public function Get_PREFactura(Request $request){
        $query = DB::SELECT("SELECT dv.det_ven_codigo, dv.pro_codigo, dv.det_ven_dev, dv.det_ven_cantidad , dv.det_ven_valor_u,
        l.descripcionlibro, ls.nombre, s.nombre_serie, ls.id_serie, a.area_idarea, ls.year, fv.periodo_id, l.idlibro,fv.ven_desc_por FROM f_detalle_venta as dv
        INNER JOIN f_venta as fv ON dv.ven_codigo=fv.ven_codigo
        INNER JOIN libros_series as ls ON dv.pro_codigo=ls.codigo_liquidacion
        INNER JOIN series as s ON ls.id_serie=s.id_serie
        INNER JOIN libro l ON ls.idLibro = l.idlibro
        LEFT JOIN asignatura a ON a.idasignatura = l.asignatura_idasignatura
        WHERE dv.ven_codigo='$request->ven_codigo' AND dv.id_empresa=fv.id_empresa
        AND fv.id_empresa=$request->idemp  ORDER BY dv.pro_codigo");

        foreach ($query as $key => $item) {

            //Precio por cada item
            $precio = $this->pedidosRepository->getPrecioXLibro($item->id_serie, $item->idlibro, $item->area_idarea, $item->periodo_id, $item->year);

            //Añadir el precio
            $query[$key]->precio = $precio ?? 0;
        }

        return $query;
    }

    public function devolucionDetalle(Request $request)
    {
        $query = DB::SELECT("SELECT ch.*, i.nombreInstitucion AS cliente, ls.id_serie, ls.nombre, a.area_idarea, ls.year
            FROM codigoslibros_devolucion_son AS ch
            LEFT JOIN codigoslibros_devolucion_header AS cl ON ch.codigoslibros_devolucion_id = cl.id
            LEFT JOIN institucion AS i ON i.idInstitucion = ch.id_cliente
            LEFT JOIN libros_series ls ON ls.idLibro = ch.id_libro
            LEFT JOIN libro l ON l.idlibro = ch.id_libro
            LEFT JOIN asignatura a ON a.idasignatura = l.asignatura_idasignatura
            WHERE cl.codigo_devolucion = '$request->busqueda'
            AND ch.prueba_diagnostico = 0");
        foreach ($query as $key => $item) {

            //Precio por cada item
            $precio = $this->pedidosRepository->getPrecioXLibro($item->id_serie, $item->id_libro, $item->area_idarea, $item->id_periodo, $item->year);

            //Añadir el precio
            $query[$key]->precio = $precio ?? 0;
        }
        return $query;
    }

    public function updateDocument(Request $request)
    {
        $request->validate([
            'documentoVenta' => 'required|string',
            'id_institucion' => 'required|integer',
            'documentoProforma' => 'required|string',
        ], [
            'documentoVenta.required' => 'El documento de venta es obligatorio.',
            'id_institucion.required' => 'El ID de la institución es obligatorio.',
            'documentoProforma.required' => 'El documento de proforma es obligatorio.',
        ]);

        try {
            DB::beginTransaction();

            // Actualizar f_venta
            $venta = Ventas::where('ven_codigo', $request->documentoVenta)->where('id_empresa', $request->id_empresa)->first();
            if(!$venta){
                DB::rollBack();
                return "El ven_codigo no existe en la base de datos";
            }
            if ($venta->institucion_id !== $request->id_institucion) {
                DB::table('f_venta')
                ->where('ven_codigo', $request->documentoVenta)
                ->where('id_empresa', $request->id_empresa)
                ->update([
                    'institucion_id'       => $request->id_institucion,
                ]);
            }

            // Actualizar f_proforma
            $proforma = Proforma::where('prof_id', $venta->ven_idproforma)->firstOrFail();

            if ($proforma->id_ins_depacho !== $request->id_institucion) {
                $proforma->id_ins_depacho = $request->id_institucion;
                $proforma->save();
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Información actualizada correctamente.'], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Documento no encontrado.'], 404);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    //api:>>/GetNotas?notas=1&activas=1&periodo=25&empresa=1
    public function GetNotas(Request $request){
        $notas      = $request->input('notas');
        $prefactura = $request->input('prefactura');
        $activas    = $request->input('activas');
        $periodo    = $request->input('periodo');
        $empresa    = $request->input('empresa');
        $query = Ventas::query()
        ->where('periodo_id', $periodo)
        ->where('id_empresa', $empresa)
        ->when($notas, function ($query) {
            $query->where(function ($query) {
                $query->where('idtipodoc', 4)
                    ->orWhere('idtipodoc', 3);
            });
        })
        ->when($prefactura, function ($query) {
            $query->where(function ($query) {
                $query->where('idtipodoc', 1);
            });
        })
        ->when($activas, function ($query) {
            $query->where(function ($query) {
                $query->where('est_ven_codigo', '<>', 3);
            });
        })
        ->get();
        return $query;
    }

    //api:get/metodosGetVentas
    public function metodosGetVentas(Request $request){
        if($request->getMovimientosNotas)               { return $this->getMovimientosNotas($request); }
        if($request->getProductoDetalleVenta)           { return $this->getProductoDetalleVenta($request); }
        if($request->reporteDocumentosVentaGeneral)     { return $this->reporteDocumentosVentaGeneral($request); }
        if($request->reporteGeneralAnual)               { return $this->reporteGeneralAnual($request); }
        if($request->getReporteContratosPagos)          { return $this->getReporteContratosPagos($request); }
        if($request->GetReportePedidosPagos)            { return $this->GetReportePedidosPagos($request); }
        if($request->GetReportePorLibro)                { return $this->GetReportePorLibro($request); }
        if($request->getReportePedidoPorFechas)         { return $this->getReportePedidoPorFechas($request); }
        if($request->getDespachosDocumentosVentas)      { return $this->getDespachosDocumentosVentas($request); }
    }

    //api:get/metodosGetVentas?GetReportePedidosPagos=1&periodo=25
    public function GetReportePedidosPagos(Request $request){
        $periodo = $request->input('periodo');
        $tipoVenta = $request->input('tipoVenta','3'); //1 => directa; 2 => lista; 3 => todas
        if (!$periodo) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }
        $condicion = "";
        if($tipoVenta == 3){

        }else{
            // si trae valores a incluir
            $condicion = "AND (
                p.tipo_venta = $tipoVenta
            )";
        }

       $query = DB::SELECT("SELECT

            p.id_institucion,
            p.tipo_venta_descr,
            p.id_pedido,
            CONCAT(u.nombres,' ', u.apellidos) AS asesor,
            i.nombreInstitucion,
            i.ruc,
            CASE
                WHEN i.punto_venta = 1 THEN 'SI'
                ELSE 'NO'
            END AS punto_venta,
            p.contrato_generado,
            p.total_venta AS ventaPedido,
            IFNULL((
                SELECT SUM(a.venta_bruta)
                FROM pedidos_alcance a
                WHERE a.estado_alcance = 1
                AND a.id_pedido = p.id_pedido
            ), 0) AS ventaAlcance,
            (p.total_venta + IFNULL((
                SELECT SUM(a.venta_bruta)
                FROM pedidos_alcance a
                WHERE a.estado_alcance = 1
                AND a.id_pedido = p.id_pedido
            ), 0)) AS ventaBruta,
            p.descuento AS comision,
            ROUND(
                IFNULL((
                    SELECT SUM(l.doc_valor)
                    FROM `1_4_documento_liq` l
                    WHERE l.periodo_id = '$periodo'
                    AND l.estado = '1'
                    AND l.tipo_pago_id IN (1,2,7)
                    AND l.id_pedido = p.id_pedido
                ),0), 2
            ) AS totalPagos,
            (
                SELECT IFNULL(SUM(pcd.valor), 0)
                FROM pedidos_pagos_detalles pcd
                LEFT JOIN 1_4_documento_liq l ON l.doc_codigo = pcd.id_pago
                LEFT JOIN pedidos_formas_pago fp ON fp.tip_pag_codigo = l.forma_pago_id
                LEFT JOIN pedidos p2 ON p2.id_pedido = l.id_pedido
                LEFT JOIN pedidos_convenios pc ON pc.id_pedido = p2.id_pedido
                WHERE l.tipo_pago_id = 4
                AND l.periodo_id = '$periodo'
                AND l.estado = '1'
                AND l.id_pedido = p.id_pedido
                AND p2.estado = '1'
                AND p2.tipo = '0'
                AND p2.id_periodo = '$periodo'
                AND pc.periodo_id = '$periodo'
                AND pc.estado <> '2'
            ) AS totalConvenioAprobado
            FROM pedidos p
            LEFT JOIN institucion i ON i.idInstitucion = p.id_institucion
            LEFT JOIN usuario u ON u.idusuario = p.id_asesor
            WHERE p.id_periodo = '$periodo'
            AND p.estado = '1'
            AND p.tipo = '0'
            AND p.contrato_generado IS NOT NULL
            $condicion
            ORDER BY i.nombreInstitucion ASC
        ");

        foreach ($query as $q) {
            // Calcular porcentaje de comisión
            $q->valorComision = round(($q->ventaBruta * $q->comision) / 100, 2);
            // Calcular venta neta (ventaBruta - porcentaje descuento)
            $q->ven_neta = round($q->ventaBruta - (($q->ventaBruta * $q->comision) / 100), 2);

            // Crear propiedad totalPagosConvenio
            $q->totalPagosConvenio = $q->totalPagos + $q->totalConvenioAprobado;

            // Agregamos la propiedad calculada sin modificar la consulta SQL
            $q->xPagar = $q->valorComision - $q->totalPagosConvenio;

            // validar si es distruidor  // 0no es distribuidor 1 si es distribuidor
            $if_distribuidor = 0;
            $getDistribuidor = DB::SELECT("SELECT * FROM f_formulario_proforma fcp
            WHERE fcp.idInstitucion = '$q->id_institucion'
            AND fcp.idperiodoescolar = '$periodo'
            AND fcp.ffp_estado = '1'
            ");
            if($getDistribuidor){
                $if_distribuidor = $getDistribuidor[0]->if_distribuidor;
            }
            $q->if_distribuidor = $if_distribuidor; // Asignar el valor a la propiedad
            $q->EsDistribuidor = $if_distribuidor == 1 ? 'SI' : 'NO';
            // traer los beneficiarios
            $getBeneficiarios = DB::SELECT("SELECT CONCAT(u.nombres,' ',u.apellidos) AS beneficiario, u.cedula
            FROM pedidos_beneficiarios b
            LEFT JOIN usuario u ON u.idusuario = b.id_usuario
            WHERE b.id_pedido ='$q->id_pedido'
            ");
            $q->beneficiarios = $getBeneficiarios;
        }

        return $query;
    }
    //api:get/metodosGetVentas?getMovimientosNotas=1
    public function getMovimientosNotas($request){
        $f_venta_historico_notas_cambiadas = DB::table('f_venta_historico_notas_cambiadas as h')
            ->where('id_periodo', $request->get('periodo_id'))
            ->leftJoin('usuario as u', 'h.user_created', '=', 'u.idusuario')
            //left join con la empresa
            ->leftJoin('empresas as e', 'h.id_empresa', '=', 'e.id')
            //left join con el periodo
            ->leftJoin('periodoescolar as p', 'h.id_periodo', '=', 'p.idperiodoescolar')
            ->select(
                'h.*',
                'e.descripcion_corta',
                'p.periodoescolar',
                DB::raw("COALESCE(CONCAT(COALESCE(u.nombres, ''), ' ', COALESCE(u.apellidos, '')), 'sin nombre') as editor")
            )
            ->get();
        return $f_venta_historico_notas_cambiadas;
    }

//api:get/metodosGetVentas?getReporteContratosPagos=1&periodo=25&tipoInstitucionIncluir=6,5,4,3,2,1
    public function getReporteContratosPagos(Request $request){

        $periodo = $request->input('periodo');
        $tipoInstitucionIncluir = $request->tipoInstitucionIncluir;
        if (!$periodo) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }
        if (!empty($tipoInstitucionIncluir)) {
            // si trae valores a incluir
            $condicionTipoInstitucion = "AND i.tipo_institucion IN ($tipoInstitucionIncluir)";
        }
        $query = DB::SELECT("SELECT
                p.id_institucion,
                p.id_pedido,
                p.tipo_venta_descr,
                CONCAT(u.nombres,' ', u.apellidos) AS asesor,
                i.nombreInstitucion,
                CASE
                    WHEN i.punto_venta = 1 THEN 'SI'
                    ELSE 'NO'
                END AS punto_venta,
                p.contrato_generado,
                p.TotalVentaReal AS ventaBruta,
                p.descuento AS comision,
                ROUND(p.TotalVentaReal - ((p.TotalVentaReal * p.descuento)/100), 2) AS ven_neta,
                ROUND(
                    IFNULL((
                        SELECT SUM(l.doc_valor)
                        FROM `1_4_documento_liq` l
                        WHERE l.periodo_id = '$periodo'
                        AND l.estado = '1'
                        AND l.tipo_pago_id IN (1,2,7)
                        AND l.id_pedido = p.id_pedido
                    ),0), 2
                ) AS totalPagos,
                (
                    SELECT IFNULL(SUM(pcd.valor), 0)
                    FROM pedidos_pagos_detalles pcd
                    LEFT JOIN 1_4_documento_liq l ON l.doc_codigo = pcd.id_pago
                    LEFT JOIN pedidos_formas_pago fp ON fp.tip_pag_codigo = l.forma_pago_id
                    LEFT JOIN pedidos p2 ON p2.id_pedido = l.id_pedido
                    LEFT JOIN pedidos_convenios pc ON pc.id_pedido = p2.id_pedido
                    WHERE l.tipo_pago_id = 4
                    AND l.periodo_id = '$periodo'
                    AND l.estado = '1'
                    AND l.id_pedido = p.id_pedido
                    AND p2.estado = '1'
                    AND p2.tipo = '0'
                    AND p2.id_periodo = '$periodo'
                    AND pc.periodo_id = '$periodo'
                    AND pc.estado <> '2'
                ) AS totalConvenioAprobado
            FROM pedidos p
            LEFT JOIN institucion i ON i.idInstitucion = p.id_institucion
            LEFT JOIN usuario u ON u.idusuario = p.id_asesor
            WHERE p.id_periodo = '$periodo'
            AND p.estado = '1'
            AND p.tipo = '0'
            $condicionTipoInstitucion
            AND p.contrato_generado IS NOT NULL
            ORDER BY i.nombreInstitucion ASC
        ");
        foreach ($query as $q) {
            // Calcular porcentaje de comisión
            $q->valorComision = round(($q->ventaBruta * $q->comision) / 100, 2);
            // Calcular venta neta (ventaBr
            // Crear propiedad totalPagosConvenio
            $q->totalPagosConvenio = $q->totalPagos + $q->totalConvenioAprobado;
            // Agregamos la propiedad calculada sin modificar la consulta SQL
            $q->xPagar = $q->valorComision - $q->totalPagosConvenio;
            // validar si es distruidor  // 0no es distribuidor 1 si es distribuidor
            $if_distribuidor = 0;
            $getDistribuidor = DB::SELECT("SELECT * FROM f_formulario_proforma fcp
            WHERE fcp.idInstitucion = '$q->id_institucion'
            AND fcp.idperiodoescolar = '$periodo'
            AND fcp.ffp_estado = '1'
            ");
            if($getDistribuidor){
                $if_distribuidor = $getDistribuidor[0]->if_distribuidor;

            }
            $q->if_distribuidor = $if_distribuidor; // Asignar el valor a la propiedad
            $q->EsDistribuidor = $if_distribuidor == 1 ? 'SI' : 'NO';
        }
        return $query;
    }
    /*

        Pedidos + Alcances:

        Se suman cantidades y valores por codigo_liquidacion | nombrelibro | nombre_serie.

        Resultado almacenado en $mergedPedidos.

        Facturado:

        Se fusiona con $mergedPedidos → si existe la clave, se agregan cantidad_facturada y documentoVenta.

        Si no existe, se agrega un nuevo registro con pedidos en 0.

        Despacho Bodega:

        Se obtiene según tipoVenta.

        Se fusiona con el array final → si existe la clave, se agregan cantidad_despachada y despachado.

        Si solo existe en despachado → todos los valores de pedidos y facturado se ponen en 0.

        Ordenamiento final por nombrelibro.
     *
    */
    //api:get/metodosGetVentas?GetReportePorLibro=1&periodo=27&tipoVenta=3&tipoInstitucionIncluir=6,5,4,3,2,1
    public function GetReportePorLibro(Request $request){
        $periodo = $request->input('periodo');
        $tipoInstitucionIncluir = $request->tipoInstitucionIncluir;
        $tipoVenta = $request->tipoVenta;

        if (!$periodo) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }

        // Si el periodo es menor o igual al configurado -> usa pedidos viejos
        $nuevo = ($periodo <= $this->tr_periodoPedido) ? 0 : 1;
        if($nuevo == 0){
            $getPedidos = $this->pedidosRepository->pedidosAnteriorXLibroSinAlcances($periodo);
            $getAlcances = $this->pedidosRepository->alcancesAnteriorXlibros($periodo);
        }else{
            $getPedidos = $this->pedidosRepository->pedidosNuevoXLibroSinAlcances($periodo);
            $getAlcances = $this->pedidosRepository->alcancesNuevoXlibros($periodo);
        }
        // --- fusionar pedidos + alcances ---
        $mergedPedidos = [];
        foreach (array_merge($getPedidos, $getAlcances) as $row) {
            $key = $row->codigo_liquidacion . '|' . $row->nombrelibro . '|' . $row->nombre_serie;
            if (!isset($mergedPedidos[$key])) {
                $mergedPedidos[$key] = [
                    "codigo_liquidacion" => $row->codigo_liquidacion,
                    "nombrelibro" => $row->nombrelibro,
                    "nombre_serie" => $row->nombre_serie,
                    "precio" => number_format((float)$row->precio, 2, '.', ''),
                    "total_cantidadPedido" => (int)$row->total_cantidad,
                    "pedido" => number_format((float)$row->pedido, 2, '.', ''),
                ];
            } else {
                $mergedPedidos[$key]["total_cantidadPedido"] += (int)$row->total_cantidad;
                $mergedPedidos[$key]["pedido"] = number_format(
                    (float)$mergedPedidos[$key]["pedido"] + (float)$row->pedido,
                    2, '.', ''
                );
            }
        }

        // --- facturado ---
        $getFacturado = DB::SELECT("SELECT

                l.idlibro,
                COALESCE(ls.codigo_liquidacion, fdv.pro_codigo) AS codigo_liquidacion,
                COALESCE(l.nombrelibro, 'SIN_NOMBRELIBRO') AS nombrelibro,
                COALESCE(s.nombre_serie, 'SIN_NOMBRE_SERIE') AS nombre_serie,
                fdv.det_ven_valor_u AS precio,
                SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) AS cantidad_facturada,
                SUM((fdv.det_ven_cantidad - fdv.det_ven_dev) * fdv.det_ven_valor_u) AS documentoVenta
            FROM f_detalle_venta fdv
            LEFT JOIN f_venta fv
                ON fv.ven_codigo = fdv.ven_codigo
                AND fv.id_empresa = fdv.id_empresa
            LEFT JOIN libros_series ls
                ON ls.codigo_liquidacion = fdv.pro_codigo
            LEFT JOIN libro l
                ON l.idlibro = ls.idLibro
            LEFT JOIN series s
                ON s.id_serie = ls.id_serie
            WHERE fv.periodo_id = '$periodo'
            AND fv.idtipodoc IN (1, 3, 4)
            AND fv.est_ven_codigo <> 3
            GROUP BY
                l.idlibro,
                COALESCE(ls.codigo_liquidacion, fdv.pro_codigo),
                COALESCE(l.nombrelibro, 'SIN_NOMBRELIBRO'),
                COALESCE(s.nombre_serie, 'SIN_NOMBRE_SERIE'),
                fdv.det_ven_valor_u
            ORDER BY
                nombrelibro;

        ");
        // --- fusionar pedidos+alcances con facturado ---
        $final = [];

        foreach ($mergedPedidos as $row) {
            $key = $row["codigo_liquidacion"] . '|' . $row["nombrelibro"] . '|' . $row["nombre_serie"];
            $final[$key] = [
                "codigo_liquidacion" => $row["codigo_liquidacion"],
                "nombrelibro" => $row["nombrelibro"],
                "nombre_serie" => $row["nombre_serie"],
                "precio" => $row["precio"],
                "total_cantidadPedido" => $row["total_cantidadPedido"],
                "pedido" => $row["pedido"],
                "cantidad_facturada" => 0,
                "documentoVenta" => "0.00",
                "cantidad_despachada" => 0,
                "despachoBodega" => "0.00"
            ];
        }

        foreach ($getFacturado as $row) {
            $key = $row->codigo_liquidacion . '|' . $row->nombrelibro . '|' . $row->nombre_serie;
            if (isset($final[$key])) {
                $final[$key]["cantidad_facturada"] = (int)$row->cantidad_facturada;
                $final[$key]["documentoVenta"] = number_format((float)$row->documentoVenta, 2, '.', '');
            } else {
                $final[$key] = [
                    "codigo_liquidacion" => $row->codigo_liquidacion,
                    "nombrelibro" => $row->nombrelibro,
                    "nombre_serie" => $row->nombre_serie,
                    "precio" => number_format((float)$row->precio, 2, '.', ''),
                    "total_cantidadPedido" => 0,
                    "pedido" => "0.00",
                    "cantidad_facturada" => (int)$row->cantidad_facturada,
                    "documentoVenta" => number_format((float)$row->documentoVenta, 2, '.', ''),
                    "cantidad_despachada" => 0,
                    "despachoBodega" => "0.00"
                ];
            }
        }

        // --- despacho bodega ---
        $arrayDespachoBodega = [];
        if($tipoVenta == 1){
            $arrayDespachoBodega = $this->ventasRepository->getDespachoBodegaDirecta($periodo, $tipoInstitucionIncluir);
        }
        if($tipoVenta == 2){
            $arrayDespachoBodega = $this->ventasRepository->getDespachoBodegaLista($periodo, $tipoInstitucionIncluir);
        }
        if($tipoVenta == 3){
            $arrayDespachoBodega = $this->ventasRepository->getDespachoBodegaTodo($periodo, $tipoInstitucionIncluir);
        }
        foreach ($arrayDespachoBodega as $row) {
            // Asegúrate de tratar objetos
            $codigo = is_object($row) ? $row->codigo_liquidacion : $row['codigo_liquidacion'];
            $nombre = is_object($row) ? $row->nombrelibro : $row['nombrelibro'];
            $serie  = is_object($row) ? $row->nombre_serie : $row['nombre_serie'];
            $cantidadDespachada = is_object($row) ? $row->cantidad_despachada : $row['cantidad_despachada'];
            $despachoBodega = is_object($row) ? $row->despachoBodega : $row['despachoBodega'];
            $precio = is_object($row) ? $row->precio : $row['precio'];

            $key = $codigo . '|' . $nombre . '|' . $serie;
            if (isset($final[$key])) {
                $final[$key]["cantidad_despachada"] = (int)$cantidadDespachada;
                $final[$key]["despachoBodega"] = number_format((float)$despachoBodega, 2, '.', '');
            } else {
                $final[$key] = [
                    "codigo_liquidacion" => $codigo,
                    "nombrelibro" => $nombre,
                    "nombre_serie" => $serie,
                    "precio" => number_format((float)$precio, 2, '.', ''),
                    "total_cantidadPedido" => 0,
                    "pedido" => "0.00",
                    "cantidad_facturada" => 0,
                    "documentoVenta" => "0.00",
                    "cantidad_despachada" => (int)$cantidadDespachada,
                    "despachoBodega" => number_format((float)$despachoBodega, 2, '.', '')
                ];
            }
        }

        // --- ordenar por nombrelibro ---
        $final = array_values($final);
        usort($final, function ($a, $b) {
            return strcmp($a["nombrelibro"], $b["nombrelibro"]);
        });

        return $final;
    }

    //api:get/metodosGetVentas?getReportePedidoPorFechas=1&fechaInicio=2022-01-01&fechaFin=2025-10-31&periodo=26
    public function getReportePedidoPorFechas(Request $request)
    {
        $id_periodo = $request->input('periodo');
        $fechaInicio = $request->input('fechaInicio');
        $fechaFin = $request->input('fechaFin');

        if (!$id_periodo || !$fechaInicio || !$fechaFin) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }

        $nuevo = ($id_periodo <= $this->tr_periodoPedido) ? 0 : 1;
        $getPedidos = [];

        if ($nuevo == 0) {
            $getPedidos = $this->pedidosRepository->pedidosPorFechasAnterior($fechaInicio, $fechaFin, $id_periodo);
        } else {
            $getPedidos = $this->pedidosRepository->pedidosPorFechasNuevo($fechaInicio, $fechaFin, $id_periodo);
        }

        // 🔹 Convertimos a colección y agrupamos por pro_codigo
        $pedidos = collect($getPedidos)
            ->groupBy('pro_codigo')
            ->map(function ($items) {
                $primero = $items->first();
                $totalCantidad = $items->sum('cantidad');

                return (object) [
                    'pro_codigo' => $primero->pro_codigo,
                    'nombre_libro' => $primero->nombre_libro,
                    'precio' => $primero->precio,
                    'cantidad' => $totalCantidad,
                    'idlibro' => $primero->idlibro,
                ];
            })
            ->values()
            ->toArray();
        // multiplicamos precio * cantidad y formateamos
        foreach ($pedidos as $key => $item) {
            $total = $item->precio * $item->cantidad;
            $pedidos[$key]->total = number_format((float)$total, 2, '.', '');
        }
        return response()->json($pedidos, 200);
    }

    //api:get/metodosGetVentas?getDespachosDocumentosVentas=1&periodo=27
    public function getDespachosDocumentosVentas(Request $request){
        $periodo = $request->input('periodo');
        if (!$periodo) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }
        $query = DB::SELECT("SELECT
                SUM(precio) AS total_precio,
                SUM(valor_neto) AS total_valor_neto
            FROM (
                SELECT
                    CASE
                        WHEN c.estado_liquidacion = 2 THEN 0
                        WHEN c.plus = 1 THEN 29.90
                        ELSE fp.pfn_pvp
                    END AS precio,
                    CASE
                        WHEN
                            (CASE
                                WHEN c.estado_liquidacion = 2 THEN 0
                                WHEN c.plus = 1 THEN 29.90
                                ELSE fp.pfn_pvp
                            END) = 0
                        THEN 0
                        ELSE
                            (CASE
                                WHEN c.estado_liquidacion = 2 THEN 0
                                WHEN c.plus = 1 THEN 29.90
                                ELSE fp.pfn_pvp
                            END) - (
                                (CASE
                                    WHEN c.estado_liquidacion = 2 THEN 0
                                    WHEN c.plus = 1 THEN 29.90
                                    ELSE fp.pfn_pvp
                                END) * (v.ven_desc_por / 100)
                            )
                    END AS valor_neto
                FROM codigoslibros c
                LEFT JOIN pedidos_formato_new fp
                    ON fp.idlibro = c.libro_idlibro
                AND fp.idperiodoescolar = '$periodo'
                LEFT JOIN f_venta v
                    ON v.ven_codigo = c.codigo_proforma
                AND v.id_empresa = c.proforma_empresa
                WHERE c.bc_periodo = '$periodo'
                AND c.estado_liquidacion IN (0,1,2)
                AND c.codigo_proforma IS NOT NULL
                AND (c.combo IS NULL OR  c.codigo_combo IS null)
                AND c.prueba_diagnostica = '0'
            ) AS sub;
        ");
        return $query;
    }

    //api:get/metodosGetVentas?getComboDetalleVenta=1&id_empresa=1&ven_codigo=PF-S24-FR-0000192
    public function getProductoDetalleVenta(Request $request) {
        // Recibir los parámetros
        $id_empresa = $request->input('id_empresa');
        $ven_codigo = $request->input('ven_codigo');

        // Validación básica
        if (!$id_empresa || !$ven_codigo) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }

        // Realizar la consulta
        $query = DetalleVentas::where('ven_codigo', $ven_codigo)
            ->leftjoin('1_4_cal_producto as p','p.pro_codigo','=','f_detalle_venta.pro_codigo')
            ->where('id_empresa', $id_empresa)
            ->select('f_detalle_venta.*','p.ifcombo','p.codigos_combos','p.pro_codigo as codigo_liquidacion','p.pro_nombre as nombre',DB::raw('CONCAT(p.pro_codigo, " - ", p.pro_nombre )as combolibro'))
            ->get(); // Usar 'get()' si esperas varios resultados

        // Retornar la respuesta
        return response()->json($query); // Para devolver una respuesta JSON
    }

    //api:get/metodosGetVentas?reporteDocumentosVentaGeneral=1&periodo=25
    public function reporteDocumentosVentaGeneral(Request $request){
        $periodo = $request->input('periodo');
        if (!$periodo) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }
        $query = DB::SELECT("SELECT
            -- Totales por cabecera
            (SELECT SUM(v.ven_subtotal)
            FROM f_venta v
            WHERE v.periodo_id = '$periodo'
            AND v.idtipodoc IN (1, 3, 4)
            AND v.est_ven_codigo <> 3
            ) AS venta_bruta,

            (SELECT SUM(v.ven_valor)
            FROM f_venta v
            WHERE v.periodo_id = '$periodo'
            AND v.idtipodoc IN (1, 3, 4)
            AND v.est_ven_codigo <> 3
            ) AS venta_neta,

            -- Totales por detalle (bruto y devoluciones)
            (SELECT SUM(fdv.det_ven_cantidad - fdv.det_ven_dev)
            FROM f_detalle_venta fdv
            INNER JOIN f_venta fv
                ON fv.ven_codigo = fdv.ven_codigo
            AND fv.id_empresa = fdv.id_empresa
            WHERE fv.periodo_id = '$periodo'
            AND fv.idtipodoc IN (1, 3, 4)
            AND fv.est_ven_codigo <> 3
            ) AS cantidad_bruta_devoluciones,

            (SELECT SUM((fdv.det_ven_cantidad - fdv.det_ven_dev) * fdv.det_ven_valor_u)
            FROM f_detalle_venta fdv
            INNER JOIN f_venta fv
                ON fv.ven_codigo = fdv.ven_codigo
            AND fv.id_empresa = fdv.id_empresa
            WHERE fv.periodo_id = '$periodo'
            AND fv.idtipodoc IN (1, 3, 4)
            AND fv.est_ven_codigo <> 3
            ) AS total_general_bruto_devoluciones,


            (SELECT SUM((fdv.det_ven_cantidad - fdv.det_ven_dev) * (fdv.det_ven_valor_u * (1 - fv.ven_desc_por / 100.0)))
            FROM f_detalle_venta fdv
            INNER JOIN f_venta fv
                ON fv.ven_codigo = fdv.ven_codigo
            AND fv.id_empresa = fdv.id_empresa
            WHERE fv.periodo_id = '$periodo'
            AND fv.idtipodoc IN (1, 3, 4)
            AND fv.est_ven_codigo <> 3
            ) AS total_con_descuento_neta_con_devoluciones;

        ", [$periodo]);
        return $query;
    }

  public function reporteGeneralAnual(Request $request)
    {
        $periodo = $request->input('periodo');
        if (!$periodo) {
            return response()->json(['status' => '0', 'message' => 'Faltan parámetros'], 200);
        }

        $periodos = DB::table('periodoescolar')
            ->where('idperiodoescolar', '=', $periodo)
            ->select('idperiodoescolar', 'periodoescolar')
            ->first();

        if ($periodos) {
            // Extraer solo números (ej: Sierra2024 -> 2024)
            preg_match_all('/\d+/', $periodos->periodoescolar, $matches);
            $anio = implode('', $matches[0]);

            $manejoPeriodos = DB::table('periodoescolar')
                ->where('periodoescolar', 'like', '%' . $anio . '%')
                ->select('idperiodoescolar', 'periodoescolar')
                ->get();

            if ($manejoPeriodos->isEmpty()) {
                return response()->json(['status' => '0', 'message' => 'No se encontró el período'], 200);
            }

            $detalles = [];
            // inicializar acumuladores globales
            $totales = [
                'venta_bruta'                                 => 0,
                'venta_neta'                                  => 0,
                'cantidad_bruta_devoluciones'                 => 0,
                'total_general_bruto_devoluciones'            => 0,
                'total_con_descuento_neta_con_devoluciones'   => 0,
                'total_dev_neta'                              => 0,
                'total_dev_bruta'                             => 0,
                'totalcantidad_devoluciones'                  => 0,
                'totalcantidad_ventasBrutas'                  => 0,
                'totalCobrado'                                => 0,
                'totalPagado'                                 => 0,
                'totalPerseo'                                 => 0,
                'totalCantidadPerseo'                         => 0,
            ];

            foreach ($manejoPeriodos as $item) {
                $query = DB::selectOne("
                    SELECT
                        (SELECT SUM(v.ven_subtotal)
                        FROM f_venta v
                        WHERE v.periodo_id = '$item->idperiodoescolar'
                        AND v.idtipodoc IN (1, 3, 4)
                        AND v.est_ven_codigo <> 3) AS venta_bruta,

                        (SELECT SUM(v.ven_valor)
                        FROM f_venta v
                        WHERE v.periodo_id = '$item->idperiodoescolar'
                        AND v.idtipodoc IN (1, 3, 4)
                        AND v.est_ven_codigo <> 3) AS venta_neta,

                        (SELECT SUM(fdv.det_ven_cantidad - fdv.det_ven_dev)
                        FROM f_detalle_venta fdv
                        INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                        WHERE fv.periodo_id = '$item->idperiodoescolar'
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3) AS cantidad_bruta_devoluciones,

                        (SELECT SUM((fdv.det_ven_cantidad - fdv.det_ven_dev) * fdv.det_ven_valor_u)
                        FROM f_detalle_venta fdv
                        INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                        WHERE fv.periodo_id = '$item->idperiodoescolar'
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3) AS total_general_bruto_devoluciones,

                        (SELECT SUM((fdv.det_ven_cantidad - fdv.det_ven_dev) * fdv.det_ven_valor_u) * (1 - fv.ven_desc_por / 100)
                        FROM f_detalle_venta fdv
                        INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                        WHERE fv.periodo_id = '$item->idperiodoescolar'
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3) AS total_con_descuento_neta_con_devoluciones,

                        (SELECT SUM(fdv.det_ven_dev * fdv.det_ven_valor_u) * (1 - fv.ven_desc_por / 100)
                        FROM f_detalle_venta fdv
                        INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                        WHERE fv.periodo_id = '$item->idperiodoescolar'
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3) AS total_dev_neta,

                        (SELECT SUM(fdv.det_ven_dev * fdv.det_ven_valor_u)
                        FROM f_detalle_venta fdv
                        INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                        WHERE fv.periodo_id = '$item->idperiodoescolar'
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3) AS total_dev_bruta,

                        (SELECT SUM(fdv.det_ven_dev)
                        FROM f_detalle_venta fdv
                        INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                        WHERE fv.periodo_id = '$item->idperiodoescolar'
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3) AS totalcantidad_devoluciones,

                        (SELECT SUM(fdv.det_ven_cantidad)
                        FROM f_detalle_venta fdv
                        INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                        WHERE fv.periodo_id = '$item->idperiodoescolar'
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3) AS totalcantidad_ventasBrutas,

                        (SELECT SUM(a.abono_facturas + a.abono_notas) FROM abono a
                        WHERE a.abono_estado = 0
                        AND a.abono_periodo = '$item->idperiodoescolar'
                        ) AS totalCobrado,
                         
                        (SELECT SUM(a.abono_facturas + a.abono_notas)
                        FROM abono a
                        WHERE a.abono_estado = 0
                        AND a.abono_empresa=1
                        AND a.abono_periodo = '$item->idperiodoescolar') AS totalCobradoProlipa,
                        (SELECT SUM(a.abono_facturas + a.abono_notas)
                        FROM abono a
                        WHERE a.abono_estado = 0
                        AND a.abono_empresa=3
                        AND a.abono_periodo = '$item->idperiodoescolar') AS totalCobradoCalmed,

                        (SELECT
                            (
                            ROUND(SUM(CASE WHEN l.tipo_pago_id = 1 AND l.ifAntAprobado = '0' AND l.estado = '1'
                                            THEN l.doc_valor ELSE 0 END), 2)
                            + ROUND(SUM(CASE WHEN l.tipo_pago_id = 1 AND l.ifAntAprobado = '1' AND l.estado = '1'
                                            THEN l.doc_valor ELSE 0 END), 2)
                            + ROUND(SUM(CASE WHEN l.tipo_pago_id = 2 AND l.estado = '1'
                                            THEN l.doc_valor  ELSE 0 END), 2)
                            + ROUND(SUM(CASE WHEN l.tipo_pago_id = 7 AND l.estado = '1'
                                            THEN l.doc_valor ELSE 0 END), 2)
                            + (
                                SELECT COALESCE(SUM(pcd.valor),0)
                                FROM pedidos_pagos_detalles pcd
                                LEFT JOIN 1_4_documento_liq l2 ON l2.doc_codigo = pcd.id_pago
                                LEFT JOIN pedidos_formas_pago fp ON fp.tip_pag_codigo = l2.forma_pago_id
                                LEFT JOIN pedidos p2 ON p2.id_pedido = l2.id_pedido
                                LEFT JOIN pedidos_convenios pc ON pc.id_pedido = p2.id_pedido
                                WHERE l2.tipo_pago_id = 4
                                AND l2.periodo_id = '$item->idperiodoescolar'
                                AND p2.id_periodo = '$item->idperiodoescolar'
                                AND pc.periodo_id = '$item->idperiodoescolar'
                                AND pc.estado <> '2'
                                AND p2.estado = '1'
                                AND l2.estado = '1'
                                AND p2.tipo = '0'
                                AND p2.contrato_generado IS NOT NULL
                            )
                            ) AS totalGeneral
                        FROM `1_4_documento_liq` l
                        LEFT JOIN pedidos p ON p.id_pedido = l.id_pedido
                        WHERE l.periodo_id = '$item->idperiodoescolar'
                        AND p.estado = '1'
                        AND p.tipo = '0'
                        AND p.contrato_generado IS NOT NULL) as totalPagado,

                        (SELECT SUM(a.ven_valor) FROM  f_venta_agrupado a
                            WHERE a.periodo_id = '$item->idperiodoescolar'
                            AND a.est_ven_codigo = 0
                            and a.estadoPerseo = 1
                        ) AS totalPerseo,

                        (SELECT SUM(d.det_ven_cantidad) FROM  f_venta_agrupado a
                            JOIN f_detalle_venta_agrupado d ON d.id_factura = a.id_factura
                            WHERE a.periodo_id = '$item->idperiodoescolar'
                            AND a.est_ven_codigo = 0
                            and a.estadoPerseo = 1
                        ) AS totalCantidadPerseo;
                ");

                // Guardar detalle del periodo
                $detalles[] = [
                    'periodo' => $item->periodoescolar,
                    'totales' => $query
                ];

                // Acumular en totales globales
                foreach ($totales as $campo => $valor) {
                    $totales[$campo] += $query->$campo ?? 0;
                }

                // ✅ formatear totales a 2 decimales
                foreach ($totales as $campo => $valor) {
                    $totales[$campo] = round($valor, 2);
                }
            }

            return response()->json([
                'detalle' => $detalles,   // lista de periodos con sus valores
                'total'   => $totales,     // suma global de todos
                'anio'    => $anio
            ]);
        }

        return response()->json(['status' => '0', 'message' => 'No se encontró el período'], 200);
    }



    public function anularPedido(Request $request)
    {
        $ven_codigo = $request->input('ven_codigo');
        $id_empresa = $request->input('id_empresa');

        DB::beginTransaction();

        try {
            $venta = DB::table('f_venta_agrupado')
                ->where('id_factura', $ven_codigo)
                ->where('id_empresa', $id_empresa)
                ->first();

            if (!$venta) {
                throw new \Exception('Pedido no encontrado o no se pudo anular.');
            }

            if ($venta->est_ven_codigo == 1) {
                return response()->json(['success' => false, 'message' => 'Este pedido ya está anulado.'], 400);
            }

            $updated = DB::table('f_venta_agrupado')
                ->where('id_factura', $ven_codigo)
                ->where('id_empresa', $id_empresa)
                ->update(['est_ven_codigo' => 1]);

            if (!$updated) {
                throw new \Exception('No se pudo actualizar el estado del pedido.');
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Pedido anulado correctamente.']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }
    //api:post/metodosPostVentas
    public function metodosPostVentas(Request $request){
        if($request->importPrefacturas)         { return $this->importPrefacturas($request); }
        if($request->importPrefacturasTablaSon) { return $this->importPrefacturasTablaSon($request); }
    }
    //api:post/metodosPostVentas?importPrefacturas=1
    public function importPrefacturas($request)
    {
        try {
            // Validación del request
            $validatedData              = $this->validate($request, [
                'data_codigos'          => ['required', 'json'],
                'id_usuario'            => ['required', 'integer'],
                'ifPermitirReemplazar'  => ['required', 'boolean'],
            ]);

            DB::beginTransaction();

            $data                   = json_decode($validatedData['data_codigos']);
            $id_usuario             = $validatedData['id_usuario'];
            $ifPermitirReemplazar   = (bool) $validatedData['ifPermitirReemplazar'];
            $cambiados              = 0;
            $codigosNoCambiados     = [];

            foreach ($data as $item) {
                $codigo = CodigosLibros::where('codigo', $item->codigo)->first();

                if (!$codigo) {
                    $codigosNoCambiados[] = [
                        "codigo" => $item->codigo,
                        "mensaje" => "Código no existe",
                    ];
                    continue;
                }

                $validate = $ifPermitirReemplazar || is_null($codigo->codigo_proforma) || $codigo->codigo_proforma == $item->documento;

                if ($validate) {
                    $comentario = "Se agregó el documento {$item->documento}";
                    $this->actualizarCodigo($codigo, $item, $id_usuario, $comentario);
                    $cambiados++;
                } else {
                    $codigosNoCambiados[] = [
                        "codigo" => $item->codigo,
                        "mensaje" => "Ya existe una pre factura {$codigo->codigo_proforma} en la pre factura {$item->documento}",
                    ];
                }
            }

            DB::commit();

            return response()->json([
                "error" => false,
                "cambiados" => $cambiados,
                "codigosNoCambiados" => $codigosNoCambiados,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "error" => true,
                "message" => $e->getMessage(),
            ], 500);
        }
    }

    private function actualizarCodigo($codigo, $item, $id_usuario, $comentario)
    {
        $venta_estado       = $codigo->venta_estado;
        if($venta_estado == 2){
            $bc_institucion = $codigo->venta_lista_institucion;
        }else{
            $bc_institucion = $codigo->bc_institucion;
        }
        $bc_periodo     = $codigo->bc_periodo;

        // Si existe codigo_union, buscar y registrar en histórico
        if ($codigo->codigo_union) {
            //actualizar codigo de union
            CodigosLibros::where('codigo', $codigo->codigo_union)->update([
                'codigo_proforma'  => $item->documento,
                'proforma_empresa' => $item->id_empresa,
            ]);
            $codigoUnion = CodigosLibros::where('codigo', $codigo->codigo_union)->first();
            if ($codigoUnion) {
                $this->GuardarEnHistorico(
                    0,
                    $bc_institucion,
                    $bc_periodo,
                    $codigo->codigo_union,
                    $id_usuario,
                    $comentario,
                    $codigoUnion,
                    json_encode($codigoUnion->getAttributes()), // Guardar todos los atributos del modelo
                    null,
                    null
                );
            }
        }

        // Actualizar el modelo actual
        CodigosLibros::where('codigo', $item->codigo)->update([
            'codigo_proforma'  => $item->documento,
            'proforma_empresa' => $item->id_empresa,
        ]);

        // Registrar en histórico
        $this->GuardarEnHistorico(
            0,
            $bc_institucion,
            $bc_periodo,
            $item->codigo,
            $id_usuario,
            $comentario,
            $codigo,
            json_encode($codigo->getAttributes()), // Guardar todos los atributos del modelo
            null,
            null
        );
    }

    //api:post/metodosPostVentas?importPrefacturasTablaSon=1
    public function importPrefacturasTablaSon(Request $request)
    {
        try {
            // Validación del request
            $validatedData = $this->validate($request, [
                'data_codigos'   => 'required|json',
                'id_usuario'     => 'required|integer',
            ]);
            DB::beginTransaction();

            $data               = json_decode($validatedData['data_codigos']);
            $id_usuario         = $validatedData['id_usuario'];
            $cambiados          = 0;
            $codigosNoCambiados = [];

            foreach ($data as $item) {
                $codigo = CodigosLibrosDevolucionSon::where('codigo', $item->codigo)->where('estado','0')->first();
                if (!$codigo) {
                    $codigosNoCambiados[] = [
                        "codigo"  => $item->codigo,
                        "mensaje" => "Código no existe o no se encuentra en estado creado",
                    ];
                    continue;
                }
                $estado            = $codigo->estado;
                //codigo no esta creado
                if($estado != '0'){
                    $codigosNoCambiados[] = [
                        "codigo"  => $item->codigo,
                        "mensaje" => "El código ya está no creado",
                    ];
                    continue;
                }
                //si el documento anterior es nulo o es al documento actual guardo el codigo
                if (is_null($codigo->documento) || $codigo->documento == $item->documento) {
                    $comentario ="Se agrego la documento $item->documento";
                    $this->actualizarCodigoTablaSon($codigo, $item, $id_usuario, $comentario);
                    $cambiados++;
                } else {
                    $codigosNoCambiados[] = [
                        "codigo"  => $item->codigo,
                        "mensaje" => "Ya existe una pre factura {$codigo->documento} en la pre factura {$item->documento}",
                    ];
                }
            }

            DB::commit();

            return [
                "cambiados"          => $cambiados,
                "codigosNoCambiados" => $codigosNoCambiados,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["error" => true, "message" => $e->getMessage()], 500);
        }
    }

    private function actualizarCodigoTablaSon($codigo, $item, $id_usuario, $comentario)
    {
        $bc_institucion = $codigo->id_cliente;
        $bc_periodo     = $codigo->id_periodo;

        // Actualizar el modelo actual
        CodigosLibrosDevolucionSon::where('codigo', $item->codigo)->update([
            'documento'       => $item->documento,
            'id_empresa'      => $item->id_empresa,
        ]);

        // Registrar en histórico
        $this->GuardarEnHistoricoTablaSon(
            0,
            $bc_institucion,
            $bc_periodo,
            $item->codigo,
            $id_usuario,
            $comentario,
            $codigo,
            json_encode($codigo->getAttributes()), // Guardar todos los atributos del modelo
            null,
            null
        );
    }

    public function verificarStock(Request $request)
    {
        $datos = $request->all(); // Los productos enviados desde el frontend
        $productosNoDisponibles = []; // Array para almacenar los productos sin stock suficiente

        // Procesamos cada producto recibido
        foreach ($datos as $producto) {
            // Verificamos según la empresa
            if ($producto['empresa'] == 1) {
                // Verificamos el stock de la empresa 1
                $stockDisponible = DB::table('1_4_cal_producto')
                    ->where('pro_codigo', $producto['codigo'])
                    ->value('pro_stock');
            } elseif ($producto['empresa'] == 3) {
                // Verificamos el stock de la empresa 3
                $stockDisponible = DB::table('1_4_cal_producto')
                    ->where('pro_codigo', $producto['codigo'])
                    ->value('pro_stockCalmed');
            } else {
                // Si la empresa no es válida, lo marcamos como no disponible
                continue;
            }

            // Verificamos si hay suficiente stock para restar
            if ($stockDisponible < $producto['cantidad']) {
                // Si no hay suficiente stock, lo añadimos al array de productos no disponibles
                $productosNoDisponibles[] = [
                    'codigo' => $producto['codigo'],
                    'cantidad_solicitada' => $producto['cantidad'],
                    'stock_disponible' => $stockDisponible,
                ];
            }
        }

        if (count($productosNoDisponibles) > 0) {
            // Si hay productos sin stock suficiente, devolvemos la lista de productos no disponibles
            return response()->json([
                'status' => 0,
                'message' => 'No hay suficiente stock para algunos productos.',
                'productos_no_disponibles' => $productosNoDisponibles
            ]);
        }

        // Si todos los productos tienen stock suficiente
        return response()->json([
            'status' => 1,
            'message' => 'Todos los productos tienen stock suficiente.'
        ]);
    }

    public function cambioPrefacturasANota(Request $request)
    {
        // Validar que tip_ven_codigo sea requerido
        if (!$request->has('tip_ven_codigo') || $request->tip_ven_codigo === null || $request->tip_ven_codigo === '') {
            return response()->json([
                "status" => "0",
                "message" => "El tipo de venta es requerido. Por favor seleccione el tipo de venta."
            ], 400);
        }

        // Recibimos los datos desde el frontend
        $id_ins_depacho = $request->id_ins_depacho;
        $empresa = $request->id_empresa;
        $data_detalle = $request->data_detalle;
        $tipoVenta = $request->tip_ven_codigo; // Tipo de venta: 1 o 2
        $nuevoVenCodigo = $request->ven_codigo; // Nuevo código de venta
        $data_documentos = $request->documentos;
        $codigos_detalles = $request->codigosVentas;

        // Si los detalles vienen como cadena JSON, decodificarlo
        if (is_string($data_detalle)) {
            $data_detalle = json_decode($data_detalle, true);
        }

        // Si los detalles vienen como cadena JSON, decodificarlo
        if (is_string($data_documentos)) {
            $data_documentos = json_decode($data_documentos, true);
        }

        if (is_string($codigos_detalles)) {
            $codigos_detalles = json_decode($codigos_detalles, true);
        }

        // Validación básica de los datos
        if (!$id_ins_depacho || empty($data_detalle)) {
            return response()->json(['message' => 'items inválidos.']);
        }

        $resultados = [];

        // Inicializamos dos arrays para los códigos
        $codigos_estado_1 = [];
        $codigos_estado_0 = [];

        // Recorremos el array y separamos los elementos
        foreach ($codigos_detalles as $codigo) {
            if ($codigo['estado_liquidacion'] == '1') {
                $codigos_estado_1[] = $codigo; // Código con estado_liquidacion igual a 1
            } else {
                $codigos_estado_0[] = $codigo; // Código con estado_liquidacion diferente de 1
            }
        }

        // Iniciar la transacción
        DB::beginTransaction();

        try {
            // Crear un nuevo documento de venta con los valores que envía el frontend
            $nuevoDocumento = [
                'ven_codigo' => $nuevoVenCodigo, // Nuevo código de venta
                'id_empresa' => $empresa,
                'ven_desc_por' => $request->ven_desc_por, // Descuento porcentaje recibido
                'ven_iva_por' => $request->ven_iva_por, // IVA porcentaje recibido
                'ven_descuento' => $request->ven_descuento, // Descuento recibido
                'ven_iva' => $request->ven_iva, // IVA recibido
                'ven_transporte' => $request->ven_transporte, // Transporte recibido
                'ven_valor' => $request->ven_valor, // Valor total recibido
                'ven_subtotal' => $request->ven_subtotal, // Subtotal recibido
                'institucion_id' => $id_ins_depacho,
                'periodo_id' => $request->periodo_id, // Periodo recibido
                'ven_cliente' => $request->ven_cliente, // Cliente recibido
                'clientesidPerseo' => $request->clientesidPerseo, // Cliente de Perseo recibido
                'ven_fecha' => now(), // Fecha actual
                'user_created' => $request->user_created, // Usuario que crea
                'tip_ven_codigo' => $tipoVenta, // Tipo de venta (1 o 2)
                'ven_tipo_inst' => $tipoVenta == 1 ? 'V' : 'L', // Tipo de venta según el valor recibido
                'est_ven_codigo' => 13, // Tipo de venta según el valor recibido
                'idtipodoc'=> $tipoVenta == 1 ? 4 : 3,
                'ruc_cliente'=> $request->ruc_cliente,
                'ven_observacion' => 'NOTA DE INTERCAMBIO DE LOS SALDOS PENDIENTES EN PRE-FACTURAS DEL CLIENTE',
            ];

            // Insertar el nuevo documento en f_venta
            DB::table('f_venta')->insert($nuevoDocumento);

            // Iteramos sobre los detalles de la venta
            foreach ($data_detalle as $item) {
                $codigo = $item['pro_codigo'];
                $cantidadSolicitada = $item['cantidad_real_facturar'];
                $cantidadRestante = $cantidadSolicitada;
                $cantidadConvertir = 0;
                $devolucionesPorDocumento = [];

                // Obtener los documentos asociados a la institución y empresa
                $documentos = DB::table('f_venta as fv')
                    ->where('fv.institucion_id', $id_ins_depacho)
                    ->where('fv.id_empresa', $empresa)
                    ->where('fv.idtipodoc', 1) // solo afectar a pre-facturas
                    ->whereNotIn('fv.est_ven_codigo', [3, 13]) // Excluir documentos anulados y intercambio (estado 3 y 13)
                    ->whereNull('fv.doc_intercambio') // Excluir documentos con intercambio
                    ->select('fv.ven_codigo', 'fv.id_empresa')
                    ->get();

                foreach ($documentos as $documento) {
                    // Obtener los detalles de los documentos
                    $detallesDocumentos = DB::table('f_detalle_venta as fdv')
                        ->join('f_venta as fv', function ($join) {
                            $join->on('fv.ven_codigo', '=', 'fdv.ven_codigo')
                                ->on('fv.id_empresa', '=', 'fdv.id_empresa');
                        })
                        ->where('fv.est_ven_codigo', '<>', 3)
                        ->where('fdv.ven_codigo', $documento->ven_codigo)
                        ->where('fdv.id_empresa', $empresa)
                        ->select('fdv.det_ven_codigo', 'fdv.pro_codigo', 'fdv.det_ven_cantidad', 'fdv.det_ven_dev', 'fdv.det_ven_valor_u')
                        ->orderBy('fdv.det_ven_cantidad', 'desc')
                        ->get();

                    foreach ($detallesDocumentos as $detalle) {
                        if ($detalle->pro_codigo == $codigo) {
                            $cantidadDisponible = $detalle->det_ven_cantidad - $detalle->det_ven_dev;
                            $idDetalle = $detalle->det_ven_codigo;

                            if ($cantidadDisponible > 0) {
                                $cantidadDevolucion = min($cantidadRestante, $cantidadDisponible);
                                $cantidadRestante -= $cantidadDevolucion;
                                $cantidadConvertir += $cantidadDevolucion;

                                $devolucionesPorDocumento[] = [
                                    'ven_codigo' => $documento->ven_codigo,
                                    'empresa' => $documento->id_empresa,
                                    'DetalleId' => $idDetalle,
                                    'cantidadConvertir' => $cantidadDevolucion,
                                    'cantidadDisponible' => $cantidadDisponible,
                                ];

                                // Si ya se cubrió la cantidad solicitada, salimos del ciclo
                                if ($cantidadRestante <= 0) {
                                    break 2;
                                }
                            }
                        }
                    }

                }

                $resultados[] = [
                    'codigo' => $codigo,
                    'cantidadSolicitada' => $cantidadSolicitada,
                    'cantidadConvertir' => $cantidadConvertir,
                    'cantidadPendiente' => $cantidadRestante,
                    'detallesConvertir' => $devolucionesPorDocumento,
                ];



                // Actualizar los detalles de cada documento convertido a nota
                foreach ($devolucionesPorDocumento as $devolucion) {
                    // Crear un nuevo detalle de venta para el nuevo documento
                    DB::table('f_detalle_venta')->insert([
                        'ven_codigo' => $nuevoVenCodigo,
                        'id_empresa' => $devolucion['empresa'],
                        'pro_codigo' => $codigo,
                        'det_ven_cantidad' => $devolucion['cantidadConvertir'], // Cantidad convertida
                        'det_ven_valor_u' => $detalle->det_ven_valor_u, // Valor unitario
                        'det_cant_intercambio' => $devolucion['cantidadConvertir'],
                        'doc_intercambio' => $devolucion['ven_codigo'],
                    ]);

                    // Actualizar la cantidad convertida en los detalles del documento original
                    DB::table('f_detalle_venta')
                        ->where('det_ven_codigo', $devolucion['DetalleId'])
                        ->update([
                            'det_cant_intercambio' => DB::raw('det_ven_cantidad'),
                            'det_ven_cantidad' => DB::raw('det_ven_cantidad - ' . $devolucion['cantidadConvertir']),
                            'doc_intercambio' => $nuevoVenCodigo, // Asignar el nuevo ven_codigo
                        ]);

                    // Calcular el nuevo subtotal de la venta
                    $nuevoSubtotal = DB::table('f_detalle_venta')
                        ->where('ven_codigo', $devolucion['ven_codigo'])
                        ->where('id_empresa', $devolucion['empresa'])
                        ->sum(DB::raw('det_ven_cantidad * det_ven_valor_u'));

                    // Obtener el porcentaje de descuento
                    $descuentoPorcentaje = DB::table('f_venta')
                        ->where('ven_codigo', $devolucion['ven_codigo'])
                        ->where('id_empresa', $devolucion['empresa'])
                        ->value('ven_desc_por'); // Porcentaje de descuento

                    // Calcular el valor del descuento
                    $valorDescuento = ($descuentoPorcentaje / 100) * $nuevoSubtotal;

                    // Calcular el nuevo valor total
                    $nuevoTotal = $nuevoSubtotal - $valorDescuento;

                    // Actualizar f_venta con el nuevo subtotal, descuento y total
                    DB::table('f_venta')
                        ->where('ven_codigo', $devolucion['ven_codigo'])
                        ->where('id_empresa', $devolucion['empresa'])
                        ->update([
                            'ven_subtotal' => $nuevoSubtotal,
                            'ven_descuento' => $valorDescuento,
                            'ven_valor' => $nuevoTotal,
                            'user_intercambio' => $request->user_created,
                            'fecha_intercambio' => now(),
                            'doc_intercambio' => $nuevoVenCodigo,
                        ]);

                     // Guardar en el histórico de intercambio de documentos
                    DB::table('historico_intercambio_documentos')->insert([
                        'ven_codigo_original' => $devolucion['ven_codigo'],
                        'ven_codigo_intercambio' => $nuevoVenCodigo,
                        'pro_codigo' => $codigo,
                        'cantidad_original' => $cantidadSolicitada,
                        'cantidad_intercambio' => $devolucion['cantidadConvertir'],
                        'usuario' => $request->user_created,
                        'periodo_id' =>$request->periodo_id,
                        'empresa_id' => $empresa,
                    ]);

                    // Obtener el código del producto y la empresa
                    $empresaProducto = $request->id_empresa;
                    $cantProductoCambio = $devolucion['cantidadConvertir'];

                    // // Verifica qué empresa es y actualiza los campos correspondientes
                    // if ($empresaProducto == 1) {
                    //     // Para la empresa 1, actualizamos 'pro_stock' y 'pro_deposito'
                    //     DB::table('1_4_cal_producto')
                    //         ->where('pro_codigo', $codigo)
                    //         ->update([
                    //             'pro_stock' => DB::raw('pro_stock - ' . $devolucion['cantidadConvertir']),
                    //             'pro_deposito' => DB::raw('pro_deposito + ' . $devolucion['cantidadConvertir']),
                    //         ]);
                    // } elseif ($empresaProducto == 3) {
                    //     // Para la empresa 3, actualizamos 'pro_stockCalmed' y 'pro_depositoCalmed'
                    //     DB::table('1_4_cal_producto')
                    //         ->where('pro_codigo', $codigo)
                    //         ->update([
                    //             'pro_stockCalmed' => DB::raw('pro_stockCalmed - ' . $devolucion['cantidadConvertir']),
                    //             'pro_depositoCalmed' => DB::raw('pro_depositoCalmed + ' . $devolucion['cantidadConvertir']),
                    //         ]);
                    // }
                    // SECCION PARA ACTUALIZAR STOCK
                    $productoController = new _14ProductoController();
                    // Llamar al método Mover_Stock_SoloTxt_Todo_A_DepositoCALMED
                    $productoController->Mover_Stock_SoloTxt_Todo_A_DepositoCALMED();

                }


            }

           // Preparar las consultas de actualización en lugar de hacerlo dentro del ciclo
            // $updates = [];
            // foreach ($documentos as $documento) {
            //     $updates[] = [
            //         'codigo_proforma' => $documento->ven_codigo,
            //         'proforma_empresa' => $documento->id_empresa,
            //         'nuevo_codigo_proforma' => $nuevoVenCodigo
            //     ];
            // }

            // // Realizar una sola consulta de actualización en 'codigoslibros' utilizando el modelo Eloquent
            // if (count($updates) > 0) {
            //     $codigoProformas = array_column($updates, 'codigo_proforma');
            //     $proformaEmpresas = array_column($updates, 'proforma_empresa');

            //     // Obtener todos los códigos que necesitas actualizar
            //     $codigos = CodigosLibros::whereIn('codigo_proforma', $codigoProformas)
            //         ->where('estado_liquidacion', '<>', 3)
            //         ->whereIn('proforma_empresa', $proformaEmpresas)
            //         ->pluck('codigo')
            //         ->toArray();

            //     // Si hay códigos que coinciden, actualizar en la tabla 'codigoslibros_devolucion_son' de forma masiva
            //     if (!empty($codigos)) {
            //         foreach ($codigos as $itemsCod) {
            //             $codigos_son = DB::table('codigoslibros_devolucion_son')
            //             ->where('codigo',$itemsCod)
            //             ->where('estado',0)
            //             ->update(['documento' => $nuevoVenCodigo]);
            //         }
            //     }

            //     // Realizar la actualización masiva en 'codigoslibros'
            //     CodigosLibros::whereIn('codigo_proforma', $codigoProformas)
            //         ->where('estado_liquidacion', '<>', 3)
            //         ->whereIn('proforma_empresa', $proformaEmpresas)
            //         ->update(['codigo_proforma' => $nuevoVenCodigo]);
            // }

            // foreach ($codigos_estado_1 as $codigo_detalle) {
            //     $codigos_son = DB::table('codigoslibros_devolucion_son')
            //         ->where('codigo', $codigo_detalle['codigo'])
            //         ->where('estado', 0)
            //         ->update(['documento' => $nuevoVenCodigo]);
            // }
            // foreach ($codigos_detalles as $codigo_detalle) {
            //     $codigos_son = CodigosLibros::whereIn('codigo_proforma', $codigo_detalle['codigo_proforma'])
            //     ->where('estado_liquidacion', '<>', 3)
            //     ->whereIn('proforma_empresa', $empresa)
            //     ->update(['codigo_proforma' => $nuevoVenCodigo]);
            // }

            // Arrays para almacenar los códigos que se actualizaron y no se actualizaron
            $actualizados = [];
            $no_actualizados = [];

            // Verificamos si hay detalles de códigos
            if (!empty($codigos_detalles)) {
                // Recorremos cada código detalle
                foreach ($codigos_detalles as $codigo_detalle) {
                    if (isset($codigo_detalle['codigo']) && isset($codigo_detalle['codigo_proforma'])) {
                        // Intentamos hacer la actualización individualmente
                        $updated = DB::table('codigoslibros')
                            ->where('codigo', $codigo_detalle['codigo'])
                            ->where('codigo_proforma', $codigo_detalle['codigo_proforma'])
                            ->where('estado_liquidacion', '<>', 3)
                            ->where('proforma_empresa', $empresa)
                            ->update(['codigo_proforma' => $nuevoVenCodigo]);

                        // Verificamos si la actualización fue exitosa
                        if ($updated) {
                            // Si se actualizó, agregamos al array de actualizados
                            $actualizados[] = $codigo_detalle['codigo'];

                            // Llamamos al método para guardar en el histórico (si lo necesitas)
                            $mensajeHistorico = 'Se movió de la prefactura ' . $codigo_detalle['codigo_proforma'] . ' a la nota ' . $nuevoVenCodigo;
                            $this->GuardarEnHistorico( 0, $id_ins_depacho, $request->periodo_id, $codigo_detalle['codigo'], $request->user_created, $mensajeHistorico, null, null, null, null);
                        } else {
                            // Si no se actualizó, agregamos al array de no actualizados
                            $no_actualizados[] = $codigo_detalle['codigo'];
                        }
                    }
                }

                // Retornamos los resultados
                $Datos =[
                    'actualizados' => $actualizados,
                    'no_actualizados' => $no_actualizados
                ];
            }

            // ACTUALIZAR SECUENCIAL
            if($empresa==1){
                if($tipoVenta==1){

                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from  f_tipo_documento where tdo_id=4");
                }else if ($tipoVenta==2){
                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from  f_tipo_documento where tdo_id=3");
                }
            }else if ($empresa==3){
                if($tipoVenta==1){

                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from  f_tipo_documento where tdo_id=4");
                }else if ($tipoVenta==2){
                    $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from  f_tipo_documento where tdo_id=3");
                }
            }

            $id=$query1[0]->id;
            $codi=$query1[0]->cod;
            $co=(int)$codi+1;
            $tipo_doc = f_tipo_documento::findOrFail($id);
            if($empresa==1){
                $tipo_doc->tdo_secuencial_Prolipa = $co;
            }else if ($empresa==3){
                $tipo_doc->tdo_secuencial_calmed = $co;
            }
            $tipo_doc->save();

            // Si todo ha ido bien, hacemos commit
            DB::commit();

            return response()->json(['message' => 'Prefacturas convertidas a notas correctamente.','status'=>'0', 'codigos' =>$Datos]);

        } catch (\Exception $e) {
            // Si ocurre un error, hacemos rollback
            DB::rollBack();
            return response()->json(['message' => 'Hubo un error al procesar las prefacturas: ' . $e->getMessage(),'status'=>'1','line'=>$e->getLine()]);
        }
    }



    public function cambioDesfasANotaCredito(Request $request)
    {
        // Validar que tip_ven_codigo sea requerido
        if (!$request->has('tip_ven_codigo') || $request->tip_ven_codigo === null || $request->tip_ven_codigo === '') {
            return response()->json([
                "status" => "0",
                "message" => "El tipo de venta es requerido. Por favor seleccione el tipo de venta."
            ], 400);
        }

        // Recibimos los datos desde el frontend (ahora por cliente, no por institución)
        $empresa = $request->id_empresa;
        $data_detalle = $request->data_detalle;
        $tipoVenta = $request->tip_ven_codigo; // Tipo de venta: 1 o 2
        $nuevoVenCodigo = $request->ven_codigo; // Nuevo código de venta

        // Si los detalles vienen como cadena JSON, decodificarlo
        if (is_string($data_detalle)) {
            $data_detalle = json_decode($data_detalle, true);
        }

        // Validación básica de los datos (ahora validamos cliente en lugar de institución)
        if (!$request->ven_cliente || empty($data_detalle)) {
            return response()->json(['message' => 'Cliente o items inválidos.']);
        }

        // Iniciar la transacción
        DB::beginTransaction();

        try {
            // Crear un nuevo documento de venta con los valores que envía el frontend
            $nuevoDocumento = [
                'ven_codigo' => $nuevoVenCodigo, // Nuevo código de venta
                'id_empresa' => $empresa,
                'ven_desc_por' => $request->ven_desc_por, // Descuento porcentaje recibido
                'ven_iva_por' => $request->ven_iva_por, // IVA porcentaje recibido
                'ven_descuento' => $request->ven_descuento, // Descuento recibido
                'ven_iva' => $request->ven_iva, // IVA recibido
                'ven_transporte' => $request->ven_transporte, // Transporte recibido
                'ven_valor' => $request->ven_valor, // Valor total recibido
                'ven_subtotal' => $request->ven_subtotal, // Subtotal recibido
                // Ya no se usa institucion_id porque ahora es por cliente
                'periodo_id' => $request->periodo_id, // Periodo recibido
                'ven_cliente' => $request->ven_cliente, // Cliente recibido
                'clientesidPerseo' => $request->clientesidPerseo, // Cliente de Perseo recibido
                'ven_fecha' => now(), // Fecha actual
                'user_created' => $request->user_created, // Usuario que crea
                'tip_ven_codigo' => $tipoVenta, // Tipo de venta (1 o 2)
                'ven_tipo_inst' => $tipoVenta == 1 ? 'V' : 'L', // Tipo de venta según el valor recibido
                'est_ven_codigo' => 14, // Estado: Pendiente de asociar SRI
                'idtipodoc' => 16, // Tipo de documento: Nota de Crédito
                'ruc_cliente' => $request->ruc_cliente, // RUC del cliente
                'fecha_notaCredito' => $request->ven_fecha,
            ];

            // Insertar el nuevo documento en f_venta
            DB::table('f_venta')->insert($nuevoDocumento);

            // Iteramos sobre los detalles de la venta
            foreach ($data_detalle as $item) {
                $codigo = $item['pro_codigo'];
                $cantidad = $item['cantidad_real_facturar'];
                $precio = $item['precio'];

                DB::table('f_detalle_venta')->insert([
                    'ven_codigo' => $nuevoVenCodigo,
                    'id_empresa' => $empresa,
                    'pro_codigo' => $codigo,
                    'det_ven_cantidad' => $cantidad,
                    'det_ven_valor_u' => $precio,
                ]);
            }

            // ACTUALIZAR SECUENCIAL
            if ($empresa == 1) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from  f_tipo_documento where tdo_id=16");
            } else if ($empresa == 3) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from  f_tipo_documento where tdo_id=16");
            }

            $id = $query1[0]->id;
            $codi = $query1[0]->cod;
            $co = (int)$codi + 1;
            $tipo_doc = f_tipo_documento::findOrFail($id);
            if ($empresa == 1) {
                $tipo_doc->tdo_secuencial_Prolipa = $co;
            } else if ($empresa == 3) {
                $tipo_doc->tdo_secuencial_calmed = $co;
            }
            $tipo_doc->save();

            // Si todo ha ido bien, hacemos commit
            DB::commit();

            return response()->json(['message' => 'Nota de crédito creada correctamente.', 'status' => '0']);

        } catch (\Exception $e) {
            // Si ocurre un error, hacemos rollback
            DB::rollBack();
            return response()->json(['message' => 'Hubo un error al crear la nota de crédito: ' . $e->getMessage(), 'status' => '1', 'line' => $e->getLine()]);
        }
    }

    public function getCantidadNotaCredito(Request $request)
    {
        // Validar los parámetros de entrada
        $request->validate([
            'institucion' => 'required|integer',
            'empresa' => 'required|integer',
            'periodo' => 'required|integer',
        ]);

        // Ejecutar la consulta
        $resultados = DB::table('f_venta as fva')
            ->join('f_detalle_venta as fdva', function ($join) {
                $join->on('fdva.ven_codigo', '=', 'fva.ven_codigo')
                    ->on('fdva.id_empresa', '=', 'fva.id_empresa');
            })
            ->select('fdva.pro_codigo', DB::raw('SUM(fdva.det_ven_cantidad) as det_ven_cantidad'))
            ->where('fva.institucion_id', $request->institucion)
            ->where('fva.id_empresa', $request->empresa)
            ->where('fva.periodo_id', $request->periodo)
            ->where('fva.est_ven_codigo', '<>', 3)
            ->where('fva.idtipodoc',16)
            ->groupBy('fdva.pro_codigo')
            ->get();

        // Devolver los resultados
        return response()->json($resultados);
    }

    public function getCantidadNotaCreditoXcliente(Request $request)
    {
        // Validar los parámetros de entrada
        $request->validate([
            'cedula' => 'required',
            'empresa' => 'required|integer',
            'periodo' => 'required|integer',
        ]);

        // Ejecutar la consulta
        $resultados = DB::table('f_venta as fva')
            ->join('f_detalle_venta as fdva', function ($join) {
                $join->on('fdva.ven_codigo', '=', 'fva.ven_codigo')
                    ->on('fdva.id_empresa', '=', 'fva.id_empresa');
            })
            ->select('fdva.pro_codigo', DB::raw('SUM(fdva.det_ven_cantidad) as det_ven_cantidad'))
            ->where('fva.ruc_cliente', $request->cedula)
            ->where('fva.id_empresa', $request->empresa)
            ->where('fva.periodo_id', $request->periodo)
            ->where('fva.est_ven_codigo', '<>', 3)
            ->where('fva.idtipodoc',16)
            ->groupBy('fdva.pro_codigo')
            ->get();

        // Devolver los resultados
        return response()->json($resultados);
    }

    public function getNotasIntercambio(Request $request)
    {
        $institucion = $request->input('institucion');
        $periodo = $request->input('periodo');

        // Ejecutar la primera consulta y obtener las notas
        $notas = DB::select("SELECT
                CASE
                    WHEN COUNT(DISTINCT f.doc_intercambio) = 1 THEN
                        MAX(f.doc_intercambio)
                    ELSE
                        GROUP_CONCAT(DISTINCT
                            CASE WHEN f.doc_intercambio <> '' THEN f.doc_intercambio END
                            ORDER BY f.doc_intercambio SEPARATOR ', ')
                END AS intercambio_doc,
                COUNT(f.doc_intercambio) AS total_intercambio
            FROM f_detalle_venta f
            LEFT JOIN f_venta fv ON fv.ven_codigo = f.ven_codigo AND fv.id_empresa = f.id_empresa
            WHERE fv.institucion_id = ?
            AND fv.periodo_id = ?
            AND fv.idtipodoc = 1
            AND f.doc_intercambio IS NOT NULL
            GROUP BY fv.institucion_id, fv.periodo_id, fv.idtipodoc", [$institucion, $periodo]);

        if (count($notas) === 0) {
            $notas[] = (object)[
                'intercambio_doc' => [],
                'total_intercambio' => 0
            ];
        }

        // Transformar el campo intercambio_doc en un arreglo de documentos
        foreach ($notas as $key => $item) {
            // Si intercambio_doc no está vacío
            if (!empty($item->intercambio_doc)) {
                // Convertir la cadena de documentos en un arreglo
                $item->intercambio_doc = explode(',', $item->intercambio_doc);
            }
        }

        // Devolver los datos con las notas y detalles integrados
        return response()->json([
            'notas' => $notas,
        ]);
    }

    public function getNotasIntercambioXcliente(Request $request)
    {
        $cedula = $request->input('cedula');
        $periodo = $request->input('periodo');

        // Ejecutar la primera consulta y obtener las notas
        $notas = DB::select("SELECT
                CASE
                    WHEN COUNT(DISTINCT f.doc_intercambio) = 1 THEN
                        MAX(f.doc_intercambio)
                    ELSE
                        GROUP_CONCAT(DISTINCT
                            CASE WHEN f.doc_intercambio <> '' THEN f.doc_intercambio END
                            ORDER BY f.doc_intercambio SEPARATOR ', ')
                END AS intercambio_doc,
                COUNT(f.doc_intercambio) AS total_intercambio
            FROM f_detalle_venta f
            LEFT JOIN f_venta fv ON fv.ven_codigo = f.ven_codigo AND fv.id_empresa = f.id_empresa
            WHERE fv.ruc_cliente = ?
            AND fv.periodo_id = ?
            AND fv.idtipodoc = 1
            AND f.doc_intercambio IS NOT NULL
            GROUP BY fv.institucion_id, fv.periodo_id, fv.idtipodoc", [$cedula, $periodo]);

        if (count($notas) === 0) {
            $notas[] = (object)[
                'intercambio_doc' => [],
                'total_intercambio' => 0
            ];
        }

        // Transformar el campo intercambio_doc en un arreglo de documentos
        foreach ($notas as $key => $item) {
            // Si intercambio_doc no está vacío
            if (!empty($item->intercambio_doc)) {
                // Convertir la cadena de documentos en un arreglo
                $item->intercambio_doc = explode(',', $item->intercambio_doc);
            }
        }

        // Devolver los datos con las notas y detalles integrados
        return response()->json([
            'notas' => $notas,
        ]);
    }


    public function getNotasCredito(Request $request)
    {
        $institucion = $request->input('institucion');
        $periodo = $request->input('periodo');

        // Ejecutar la primera consulta y obtener las notas
        $notas = DB::select("SELECT DISTINCT fv.*
            FROM f_detalle_venta f
            LEFT JOIN f_venta fv ON fv.ven_codigo = f.ven_codigo AND fv.id_empresa = f.id_empresa
            WHERE fv.institucion_id = ?
            AND fv.periodo_id = ?
            AND fv.idtipodoc = 16", [$institucion, $periodo]);

        // Devolver los datos con las notas y detalles integrados
        return $notas;
    }

    public function getNotasCreditoXcliente(Request $request)
    {
        $cedula = $request->input('cedula');
        $periodo = $request->input('periodo');

        // Ejecutar la primera consulta y obtener las notas
        $notas = DB::select("SELECT DISTINCT fv.*
            FROM f_detalle_venta f
            LEFT JOIN f_venta fv ON fv.ven_codigo = f.ven_codigo AND fv.id_empresa = f.id_empresa
            WHERE fv.ruc_cliente = ?
            AND fv.periodo_id = ?
            AND fv.idtipodoc = 16", [$cedula, $periodo]);

        // Devolver los datos con las notas y detalles integrados
        return $notas;
    }
    public function getNotasCLiente(Request $request)
    {
        $institucion = $request->input('institucion');
        $periodo = $request->input('periodo');

        // Ejecutar la primera consulta y obtener las notas
        $notas = DB::select("SELECT fv.*, i.nombreInstitucion
            FROM f_venta fv
            LEFT JOIN institucion i ON i.idInstitucion = fv.institucion_id
            WHERE fv.institucion_id = $institucion
            AND fv.periodo_id = $periodo
            AND (fv.est_ven_codigo = 13
            OR fv.idtipodoc = 16)");

        // Devolver los datos con las notas y detalles integrados
        return $notas;
    }


    public function getNotasCLienteXcliente(Request $request)
    {
        $cedula = $request->input('cedula');
        $periodo = $request->input('periodo');

        // Ejecutar la primera consulta y obtener las notas
        $notas = DB::select("SELECT fv.*, i.nombreInstitucion
            FROM f_venta fv
            LEFT JOIN institucion i ON i.idInstitucion = fv.institucion_id
            WHERE fv.ruc_cliente = $cedula
            AND fv.periodo_id = $periodo
            AND (fv.est_ven_codigo = 13
            OR fv.idtipodoc = 16)");

        // Devolver los datos con las notas y detalles integrados
        return $notas;
    }


    public function Get_PREFacturaCodigos(Request $request)
    {
        // Asegurarnos de que ven_codigo y idemp sean arreglos (en caso de que vengan como cadenas)
        $venCodigo = is_array($request->ven_codigo) ? $request->ven_codigo : explode(',', $request->ven_codigo);
        $idemp = is_array($request->idemp) ? $request->idemp : explode(',', $request->idemp);

        // Consulta con whereIn
        $query = CodigosLibros::whereIn('codigo_proforma', $venCodigo)
                            ->where('estado_liquidacion', '<>', 3)
                            ->whereIn('proforma_empresa', $idemp)
                            ->select('codigo', 'codigo_union','codigo_proforma', 'proforma_empresa', 'estado_liquidacion')
                            ->get();

        return $query;
    }
    public function despachos(Request $request)
    {
        if($request->busquedaLibro){
            $query = DB::select("SELECT DISTINCT p.pro_codigo, p.gru_pro_codigo, p.pro_nombre, p.pro_descripcion FROM 1_4_cal_producto p
            INNER JOIN f_detalle_venta d ON d.pro_codigo = p.pro_codigo
            INNER JOIN f_venta f ON f.ven_codigo = d.ven_codigo AND f.id_empresa = d.id_empresa
            WHERE f.periodo_id = ?
            AND f.est_ven_codigo <> 3
            ",[$request->periodo]);
        }
        if($request->Xlibro){
            //Despachado
            $query = DB::select("SELECT
                    fdv.pro_codigo,
                    p.pro_nombre,
                    SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) AS cantidadReal,
                     SUM( fdv.det_ven_dev) AS cantidadDevuelta,
                    fdv.det_ven_valor_u,
                    fdv.id_empresa
                FROM f_detalle_venta fdv
                INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo AND fv.id_empresa = fdv.id_empresa
                INNER JOIN 1_4_cal_producto p ON fdv.pro_codigo = p.pro_codigo
                 WHERE fv.periodo_id = $request->periodo
                AND fv.tip_ven_codigo =  $request->tipoVenta
                AND fv.idtipodoc IN (1, 3, 4)
                AND fv.est_ven_codigo <> 3
                AND fv.id_empresa = $request->empresa
                GROUP BY fdv.pro_codigo, p.pro_nombre, fdv.det_ven_valor_u, fdv.id_empresa;
            ");
        }
        if($request->XlibroTodos){
            // Obtener parámetros del request
            $periodo_id     = $request->periodo;
            //tipoVenta = 1 ; directa; 2 = lista; 3 todo
            $tipoVenta      = $request->tipoVenta;
            $nuevo          = $request->nuevo;
            $tipoInstitucionIncluir = $request->tipoInstitucionIncluir;
            $arrayPedidos   = [];
            $arrayDespachoBodega = [];
            // tipoInstitucion: 0 => institución venta directa; 1 => venta lista
            $tipoInstitucion = $request->tipoVenta == 1 ? 0 : 1;
            $setTipoVenta = [];
            if($tipoVenta == 1){
                $setTipoVenta = [1];
            }
            if($tipoVenta == 2){
                $setTipoVenta = [2];
            }
            // directa y lista
            if($tipoVenta == 3){
                $setTipoVenta = [1,2];
            }

            // Consulta despachados bodega directa
            if($tipoVenta == 1){
                $arrayDespachoBodega = $this->ventasRepository->getDespachoBodegaDirecta($request->periodo, $tipoInstitucionIncluir);
            }
            // Consulta despachados bodega lista
            if($tipoVenta == 2){
                $arrayDespachoBodega = $this->ventasRepository->getDespachoBodegaLista($request->periodo, $tipoInstitucionIncluir);
            }
            // Consulta despachos Todos
            if($tipoVenta == 3){
                $arrayDespachoBodega = $this->ventasRepository->getDespachoBodegaTodo($request->periodo, $tipoInstitucionIncluir);
            }
            // Formato para colección
            $arrayDespachoBodega = collect($arrayDespachoBodega)->map(function ($item) {
                $item = is_object($item) ? (array) $item : $item;
                $item['valor'] = (float) ($item['valor'] ?? 0);
                return $item;
            });

            // Consulta de Documentos Venta
            $arrayDocumentosVenta = $this->ventasRepository->getVentasTipoVentaSinCombos($request->periodo, $setTipoVenta);
            // Convertir a colección y asegurar formato
            $arrayDocumentosVenta = collect($arrayDocumentosVenta)->map(function ($item) {
                // Convertir a array asociativo si es objeto
                $item = is_object($item) ? (array) $item : $item;
                // Asegurar que valor sea float
                $item['valor'] = (float) ($item['valor'] ?? 0);
                return $item;
            });
            if($nuevo == 0){
                // Obtener pedidos con contratos
                $arrayPedidos = $this->ventasRepository->getProductosPedidos($periodo_id, $setTipoVenta);
            }
            if($nuevo == 1){
                $arrayPedidos = $this->ventasRepository->getProdutosPedidosNuevo($periodo_id, $setTipoVenta);
            }

            // Convertir a colección y asegurar formato
            $arrayPedidos = collect($arrayPedidos)->map(function ($item) {
                $item = is_object($item) ? (array) $item : $item;
                $item['valor'] = (float) ($item['valor'] ?? 0);
                return $item;
            });
            // Productos enviados a Perseo
            $arrayPerseo = $this->ventasRepository->getProductosPerseo($periodo_id, null, $tipoInstitucion);
            // Convertir a colección y asegurar formato
            $arrayPerseo = collect($arrayPerseo)->map(function ($item) {
                $item = is_object($item) ? (array) $item : $item;
                $item['valor'] = (float) ($item['valor'] ?? 0);
                return $item;
            });
            // Unir los tres arrays en uno solo
            $allBooks = [];

            // Función auxiliar para agregar libros al array
            $addToBooks = function ($collection, $type) use (&$allBooks) {
                $collection->each(function ($item) use ($type, &$allBooks) {
                    $key = $item['nombrelibro'] . '|' . $item['codigo_liquidacion'];
                    if (!isset($allBooks[$key])) {
                        $allBooks[$key] = [
                            'nombrelibro' => $item['nombrelibro'],
                            'codigo_liquidacion' => $item['codigo_liquidacion'],
                            'pedido' => 0,
                            'documentoVenta' => 0,
                            'perseo' => 0,
                            'despachoBodega' => 0
                        ];
                    }
                    $allBooks[$key][$type] = (float) $item['valor'];
                });
            };

            // Agregar valores de cada colección
            $addToBooks($arrayPedidos, 'pedido');
            $addToBooks($arrayDocumentosVenta, 'documentoVenta');
            $addToBooks($arrayPerseo, 'perseo');
            $addToBooks($arrayDespachoBodega, 'despachoBodega');

            // Convertir a array y devolver
            $result = array_values($allBooks);

            // quitar combos
            foreach($result as $key => $item6){
                $buscarProducto = _14Producto::where('pro_codigo', $item6['codigo_liquidacion'])
                ->leftjoin('libros_series', 'libros_series.codigo_liquidacion', '=', '1_4_cal_producto.pro_codigo')
                ->select('1_4_cal_producto.ifcombo','libros_series.idLibro')
                ->first();
                if($buscarProducto){
                    // Si $item6 es un array, asignar como array
                    if(is_array($item6)){
                        $result[$key]['ifcombo'] = $buscarProducto->ifcombo;
                        $result[$key]['idLibro'] = $buscarProducto->idLibro;
                    }
                    // Si $item6 es un objeto, asignar como objeto
                    else if(is_object($item6)){
                        $result[$key]->ifcombo = $buscarProducto->ifcombo;
                        $result[$key]->idLibro = $buscarProducto->idLibro;
                    }
                }else{
                    return ["status" => "0", "message" => "Producto {$item6['codigo_liquidacion']} no encontrado"];
                }
            }
            // filtrar solo los que tienen ifcombo = 0
            $result = array_values(array_filter($result, function ($item) {
                return ($item['ifcombo'] ?? 0) == 0;
            }));
            // sacar precio
            foreach($result as $key => $item3){
                $idlibro= $item3['idLibro'];
                $precio = 0;
                $series = null;

                $getPrecio = DB::table('pedidos_formato_new')
                ->leftJoin('libros_series', 'libros_series.idLibro', '=', 'pedidos_formato_new.idlibro')
                ->leftJoin('series', 'series.id_serie', '=', 'libros_series.id_serie')
                ->where('pedidos_formato_new.idperiodoescolar', $periodo_id)
                ->where('pedidos_formato_new.idlibro', $idlibro)
                ->select('pedidos_formato_new.*', 'series.nombre_serie')
                ->first();
                if($getPrecio){
                    $precio = $getPrecio->pfn_pvp;
                    $series = $getPrecio->nombre_serie;
                }

                $result[$key]['precio'] = (float)$precio; // aquí sí modifica $result
                $result[$key]['nombre_serie'] = $series; // aquí se agrega el nombre de la serie
            }

            return $result;
        }
        //api:get/despachos?codigosDespachadosXLibro=1&periodo=26&codigo_liquidacion=SMCLL3
        if ($request->codigosDespachadosXLibro) {
            $periodo = $request->periodo;
            $codigo_liquidacion = $request->codigo_liquidacion;
            $tipoInstitucionIncluir = $request->tipoInstitucionIncluir; // Ej: "5,4,3,2"

            $getLibro = LibroSerie::where('codigo_liquidacion', $codigo_liquidacion)->first();

            if (!$getLibro) {
                return [
                    "status" => "0",
                    "message" => "El libro con código de liquidación {$codigo_liquidacion} no existe."
                ];
            }

            $idLibro = $getLibro->idLibro;

            // Consulta SQL con filtro dinámico de tipoInstitucion
            $query = DB::SELECT("
                SELECT
                    c.codigo_proforma,
                    c.contrato,
                    l.nombrelibro,
                    -- Codigos sin combo
                    GROUP_CONCAT(
                        CASE
                            WHEN c.codigo_combo IS NULL OR c.codigo_combo = ''
                            THEN c.codigo
                            ELSE NULL
                        END
                        SEPARATOR ', '
                    ) AS codigos,
                    -- Codigos con combo
                    GROUP_CONCAT(
                        CASE
                            WHEN c.codigo_combo IS NOT NULL AND c.codigo_combo <> ''
                            THEN CONCAT(c.codigo, ' - ', c.codigo_combo)
                            ELSE NULL
                        END
                        SEPARATOR ', '
                    ) AS codigos_con_combo,
                    COUNT(*) AS total_libros,
                    i2.nombreInstitucion AS institucion_despacho,
                    i.nombreInstitucion AS institucion_liquidacion
                FROM codigoslibros c
                LEFT JOIN institucion i ON i.idInstitucion = c.bc_institucion
                LEFT JOIN institucion i2 ON i2.idInstitucion = c.venta_lista_institucion
                LEFT JOIN libro l ON l.idlibro = c.libro_idlibro
                WHERE c.bc_periodo = '$periodo'
                AND c.prueba_diagnostica = '0'
                AND c.libro_idlibro = '$idLibro'
                AND c.estado_liquidacion <> '3'
                AND (
                        (c.venta_estado = 2 AND i2.tipo_institucion IN ($tipoInstitucionIncluir))
                        OR
                        (c.venta_estado = 1 AND i.tipo_institucion IN ($tipoInstitucionIncluir))
                    )
                GROUP BY
                    c.codigo_proforma,
                    c.contrato,
                    l.nombrelibro,
                    i.nombreInstitucion,
                    i2.nombreInstitucion
            ");
            return $query;

        }

        // Buscar asesores por periodo y empresa
        if ($request->busquedaAsesorPeriodo) {
            $query = DB::select("SELECT DISTINCT u.idusuario AS id_asesor,
                CONCAT(u.nombres, ' ', u.apellidos) AS asesor
                FROM f_venta fv
                INNER JOIN institucion i ON i.idInstitucion = fv.institucion_id
                INNER JOIN pedidos p ON p.id_institucion = i.idInstitucion
                INNER JOIN usuario u ON p.id_asesor = u.idusuario
                WHERE  p.id_periodo = ?
                AND fv.periodo_id = ?
                AND fv.id_empresa = ?
                AND fv.est_ven_codigo <> 3
                AND fv.institucion_id IS NOT NULL
                AND fv.idtipodoc IN (1, 3, 4)",
                [$request->periodo, $request->periodo, $request->empresa]);
        }

        // Buscar ventas por asesor
        if($request->Xasesor){

            $institucionesPedidos = DB::table('pedidos')
                ->where('id_asesor', $request->asesor)
                ->where('id_periodo', $request->periodo)
                ->where('tipo_venta', 1)
                ->pluck('id_institucion')
                ->unique();

            $institucionesObsequios = DB::table('pedidos')
                ->join('p_libros_obsequios', 'p_libros_obsequios.id_pedido', '=', 'pedidos.id_pedido')
                ->where('pedidos.id_asesor', $request->asesor)
                ->where('p_libros_obsequios.id_periodo', $request->periodo)
                ->where('pedidos.tipo_venta', 1)
                ->pluck('pedidos.id_institucion');

            $instituciones = $institucionesPedidos
                ->merge($institucionesObsequios)
                ->unique()
                ->values();

            // return $instituciones;

            $results = collect();

            if ($instituciones->isNotEmpty()) {
                foreach ($instituciones as $institucion_id) {
                    $query = DB::select("
                        SELECT fv.institucion_id, fdv.*, p.pro_nombre,
                            SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) AS cantidadReal,
                            fv.tip_ven_codigo,
                            ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad), 2) AS precio,
                            ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad - fdv.det_ven_dev), 2) AS precioDevolucion,
                            SUM(fdv.det_ven_dev) AS cantidadDevuelta,
                            COALESCE( ROUND(
                            (SUM(fdv.det_ven_valor_u * fdv.det_ven_cantidad)-
                            ((SUM(fdv.det_ven_valor_u * fdv.det_ven_cantidad)*fv.ven_desc_por)/100)), 2),0) AS valorNeto,
                            COALESCE(ROUND(
                                fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) -
                                fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) * fv.ven_desc_por / 100,
                                2),0) AS precioDevolucionNeto,
                            fv.ven_desc_por
                        FROM f_venta fv
                        INNER JOIN f_detalle_venta fdv ON fv.ven_codigo = fdv.ven_codigo AND fv.id_empresa = fdv.id_empresa
                        INNER JOIN 1_4_cal_producto p ON fdv.pro_codigo = p.pro_codigo
                        WHERE fv.institucion_id = ?
                        AND fv.id_empresa = ?
                        AND fv.idtipodoc IN (1, 3, 4)
                        AND fv.est_ven_codigo <> 3
                        AND fv.periodo_id = ?
                        GROUP BY fv.ven_codigo, p.pro_nombre, fv.institucion_id, fv.tip_ven_codigo, fv.ven_desc_por
                    ", [$institucion_id, $request->empresa, $request->periodo]);

                    // Puedes hacer merge o push de resultados
                    $results = $results->merge($query);
                }
            }

            // Ahora $results contiene todos los resultados para cada institución individualmente consultada

            return $results;

        }




        // Consulta para buscar instituciones y periodo
        if ($request->busquedaInstitucionPeriodo) {
            $query = DB::select("SELECT DISTINCT i.idInstitucion, i.nombreInstitucion
                FROM f_venta fv
                INNER JOIN institucion i ON fv.institucion_id = i.idInstitucion
                WHERE fv.est_ven_codigo <> 3
                AND fv.idtipodoc IN (1, 3, 4)
                AND fv.periodo_id = ?
                AND fv.id_empresa = ?",
                [$request->periodo, $request->empresa]);
        }
        //api:get/despachos?busquedaInstitucionPeriodoTodas=yes&periodo=25
        if ($request->busquedaInstitucionPeriodoTodas) {
            $query = DB::select("SELECT DISTINCT i.idInstitucion, i.nombreInstitucion
                FROM f_venta fv
                INNER JOIN institucion i ON fv.institucion_id = i.idInstitucion
                WHERE fv.est_ven_codigo <> 3
                AND fv.idtipodoc IN (1, 3, 4)
                AND fv.periodo_id = ?
                ORDER BY i.nombreInstitucion ASC
                ",
                [$request->periodo]);
                foreach($query as $key => $item){
                    $cantidadDespachada = DB::SELECT("SELECT fdv.*, p.pro_nombre, SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) AS cantidadReal,
                     fv.tip_ven_codigo,
                    ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad), 2) AS precio,
                    ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad - fdv.det_ven_dev), 2) AS precioDevolucion,
                    ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_dev), 2) AS precioSoloDevuelto,
                    SUM( fdv.det_ven_dev) AS cantidadDevuelta
                    FROM f_detalle_venta fdv
                    INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                        AND fv.id_empresa = fdv.id_empresa
                    INNER JOIN 1_4_cal_producto p ON fdv.pro_codigo = p.pro_codigo
                    WHERE fv.periodo_id = ?
                    AND fv.institucion_id = ?
                    AND fv.est_ven_codigo <> 3
                    AND fv.idtipodoc IN (1, 3, 4)
                    GROUP BY fdv.det_ven_codigo, p.pro_nombre, fv.tip_ven_codigo, fv.id_empresa",
                    [$request->periodo, $item->idInstitucion]);
                    //sumar det_ven_cantidad x institucion
                    $det_ven_cantidad   = 0;
                    $det_ven_dev        = 0;
                    $cantidadReal       = 0;
                    $totalFacturado     = 0;
                    $totalFacturadoMenosDevuelto = 0;
                    $totalSoloDevuelto   = 0;
                    foreach($cantidadDespachada as $item2){
                        $det_ven_cantidad   += $item2->det_ven_cantidad;
                        $det_ven_dev        += $item2->det_ven_dev;
                        $cantidadReal       += $item2->cantidadReal;
                        $totalFacturado     += $item2->precio;
                        $totalFacturadoMenosDevuelto += $item2->precioDevolucion;
                        $totalSoloDevuelto   += $item2->precioSoloDevuelto;
                    }
                    $item->det_ven_cantidad = $det_ven_cantidad;
                    $item->det_ven_dev      = $det_ven_dev;
                    $item->cantidadReal     = $cantidadReal;
                    $item->totalFacturado   = $totalFacturado;
                    $item->totalSoloDevuelto =  $totalSoloDevuelto;
                    $item->totalFacturadoMenosDevuelto = $totalFacturadoMenosDevuelto;
                }
        }
        if ($request->Xinstitucion) {
            $instituciones = explode(',', $request->institucion); // Soporte para múltiples instituciones
            $placeholders = implode(',', array_fill(0, count($instituciones), '?'));
            $query = DB::select("SELECT fdv.*, p.pro_nombre, SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) AS cantidadReal, fv.tip_ven_codigo,
                ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad), 2) AS precio,
                ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad - fdv.det_ven_dev), 2) AS precioDevolucion,
                SUM( fdv.det_ven_dev) AS cantidadDevuelta
                FROM f_detalle_venta fdv
                INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                    AND fv.id_empresa = fdv.id_empresa
                INNER JOIN 1_4_cal_producto p ON fdv.pro_codigo = p.pro_codigo
                WHERE fv.periodo_id = ?
                AND fv.id_empresa = ?
                AND fv.institucion_id IN ($placeholders)
                AND fv.est_ven_codigo <> 3
                AND fv.idtipodoc IN (1, 3, 4)
                GROUP BY fdv.det_ven_codigo, p.pro_nombre, fv.tip_ven_codigo, fv.id_empresa",
                array_merge([$request->periodo, $request->empresa], $instituciones));
        }
        if ($request->XinstitucionTodo) {
            $query = DB::select("SELECT fdv.*, p.pro_nombre, SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) AS cantidadReal, fv.tip_ven_codigo,
                ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad), 2) AS precio,
                ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad - fdv.det_ven_dev), 2) AS precioDevolucion,
                SUM( fdv.det_ven_dev) AS cantidadDevuelta
                FROM f_detalle_venta fdv
                INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                    AND fv.id_empresa = fdv.id_empresa
                INNER JOIN 1_4_cal_producto p ON fdv.pro_codigo = p.pro_codigo
                WHERE fv.periodo_id = ?
                AND fv.institucion_id = ?
                AND fv.est_ven_codigo <> 3
                AND fv.idtipodoc IN (1, 3, 4)
                GROUP BY fdv.det_ven_codigo, p.pro_nombre, fv.tip_ven_codigo, fv.id_empresa",
                [$request->periodo, $request->institucion]);
        }
        //buscar cliente y periodo
        if($request->busquedaClientePeriodo){
            $query = DB::select("SELECT DISTINCT v.ruc_cliente, CONCAT(u.nombres, '', u.apellidos) AS cliente FROM f_venta v
                JOIN usuario u ON u.cedula = v.ruc_cliente
                WHERE v.est_ven_codigo <> 3
                AND v.idtipodoc IN (1, 3, 4)
                AND v.periodo_id = ?
                AND v.id_empresa = ?",
                [$request->periodo, $request->empresa]);
        }
         if($request->XCliente){
            $query = DB::select("SELECT fdv.*, p.pro_nombre, SUM(fdv.det_ven_cantidad - fdv.det_ven_dev) AS cantidadReal, fv.tip_ven_codigo,
            ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad ), 2) AS precio,
            ROUND(fdv.det_ven_valor_u * SUM(fdv.det_ven_cantidad - fdv.det_ven_dev), 2) AS precioDevolucion,
            SUM( fdv.det_ven_dev) AS cantidadDevuelta
            FROM f_detalle_venta fdv
            INNER JOIN f_venta fv ON fv.ven_codigo = fdv.ven_codigo
                AND fv.id_empresa = fdv.id_empresa
            INNER JOIN 1_4_cal_producto p ON fdv.pro_codigo = p.pro_codigo
            WHERE fv.periodo_id = $request->periodo
            and fv.id_empresa = $request->empresa
            AND fv.ruc_cliente = $request->cliente
            AND fv.idtipodoc IN (1, 3, 4)
            AND fv.est_ven_codigo <> 3

            GROUP BY fdv.det_ven_codigo, p.pro_nombre;

        ");
        }


        return $query;
    }

    // METODOS JEYSON INICIO
    public function GetFacturasxAgrupa_SoloEnviados(Request $request){
        $identificacion = $request->input('identificacion');
        $id_empresa = $request->input('id_empresa');

            $query = DB::SELECT("SELECT
            fv.id_factura,
            fv.ven_cliente,
            fv.ven_fecha,
            fv.ven_valor,
            fv.id_empresa,
            fv.estadoPerseo,
            fv.*,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono,
            em.descripcion_corta as nombre,
            CONCAT(us.nombres,' ',us.apellidos) AS responsable,
            COUNT(DISTINCT dfv.pro_codigo) AS item,
            SUM(dfv.det_ven_cantidad) AS libros
        FROM
            f_venta_agrupado fv
            INNER JOIN institucion ins ON fv.institucion_id = ins.idInstitucion
            INNER JOIN usuario usa ON fv.ven_cliente = usa.idusuario
            INNER JOIN f_detalle_venta_agrupado dfv ON fv.id_factura = dfv.id_factura
            INNER JOIN empresas em ON em.id = fv.id_empresa
            INNER JOIN usuario us ON us.idusuario=fv.user_created
        WHERE dfv.id_empresa = fv.id_empresa
            AND fv.idtipodoc = 11
            AND fv.estadoPerseo = 1
            AND usa.cedula = $identificacion
            AND fv.id_empresa = $id_empresa
        GROUP BY
            fv.id_factura,
            fv.ven_cliente,
            fv.ven_fecha,
            fv.ven_valor,
            fv.ven_valor,
            fv.id_empresa,
            fv.estadoPerseo,
            ins.nombreInstitucion,
            ins.direccionInstitucion,
            ins.telefonoInstitucion,
            usa.nombres,
            usa.apellidos,
            usa.cedula,
            usa.email,
            usa.telefono
        ORDER BY
            fv.ven_fecha DESC");
        return $query;
    }

    public function Regresar_A_Pendiente_Documento(Request $request)
    {
        // Iniciar la transacción antes del try
        DB::beginTransaction();
        try {
            // Validar los datos recibidos en el request
            $request->validate([
                'ven_codigo' => 'required|string',
                'id_empresa' => 'required|integer',
                'observacionRegresarAPendiente' => 'required|string'
            ]);
            // Buscar la venta en la tabla f_venta
            $venta = DB::table('f_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->id_empresa)
                ->first();

            if (!$venta) {
                // Si no se encuentra, hacer rollback y devolver error
                DB::rollBack();
                return response()->json([
                    'error' => 'No se encontró la venta con los datos proporcionados'
                ], 404);
            }
            // Validar si ya el documento ha sido devuelto no poder volver a pendiente
            $validateDevuelto = DB::SELECT("SELECT * FROM f_detalle_venta v
            WHERE v.ven_codigo = '$request->ven_codigo'
            AND v.det_ven_dev > 0
            AND v.id_empresa =  '$request->id_empresa'
            ");
            if(count($validateDevuelto) > 0){
                // Si ya se ha devuelto, devolver error
                DB::rollBack();
                return response()->json([
                    'error' => "El documento $request->ven_codigo tiene devoluciones registradas, no se puede volver a pendiente"
                ], 404);
            }
            // Actualizar los campos requeridos
            DB::table('f_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->id_empresa)
                ->update([
                    'observacionRegresarAPendiente' => $request->observacionRegresarAPendiente . ' Fecha: ' . now(),
                    'est_ven_codigo' => 2,
                    'updated_at' => now() // Registrar la fecha de actualización
                ]);
            // Confirmar la transacción
            DB::commit();
            return response()->json([
                'message' => 'Venta actualizada correctamente'
            ], 200);
        } catch (\Exception $e) {
            // Si hay un error, hacer rollback
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // METODOS JEYSON FIN


    //Metodo para quitar el pendiente de la proforma y igual ala venta
    public function ActualizarVenta(Request $request)
    {
        // Iniciar una transacción para garantizar la integridad de los datos
        DB::beginTransaction();

        try {
            // Obtener los valores de la solicitud
            $codigo_empresa = $request->id_empresa;
            $ven_codigo = $request->ven_codigo;
            $user_created = $request->user_created; // Asumiendo que pasas el usuario en la solicitud

            // Buscar la venta para obtener el prof_id
            $venta = DB::table('f_venta')
                ->where('id_empresa', $codigo_empresa)
                ->where('ven_codigo', $ven_codigo)
                ->first();

            if (!$venta) {
                throw new \Exception('Venta no encontrada');
            }

            $prof_id = $venta->ven_idproforma;

            // Buscar la proforma asociada
            $proforma = DB::table('f_proforma')
                ->where('emp_id', $codigo_empresa)
                ->where('prof_id', $prof_id)
                ->where('prof_estado', '<>', 0)
                ->first();

            if (!$proforma) {
                throw new \Exception('Proforma no encontrada');
            }

            // Obtener todos los documentos de venta asociados a la proforma
            $ventas = DB::table('f_venta')
                ->where('id_empresa', $codigo_empresa)
                ->where('ven_idproforma', $prof_id)
                ->where('est_ven_codigo', '<>', 3)
                ->get();

            // Obtener los detalles de la proforma
            $detalles_proforma = DB::table('f_detalle_proforma')
                ->where('prof_id', $proforma->id)
                ->get()
                ->keyBy('pro_codigo');

            // Calcular la suma de cantidades por producto en los detalles de venta
            $detalles_venta = DB::table('f_detalle_venta')
                ->whereIn('ven_codigo', $ventas->pluck('ven_codigo'))
                ->where('id_empresa', $codigo_empresa)
                ->select('pro_codigo', DB::raw('SUM(det_ven_cantidad) as total_cantidad'))
                ->groupBy('pro_codigo')
                ->get()
                ->keyBy('pro_codigo');

            // Array para almacenar los cambios en el historial de stock
            $HistoricoStock = [];

            // Actualizar las cantidades en los detalles de la proforma y el stock
            foreach ($detalles_proforma as $pro_codigo => $detalle) {
                $nueva_cantidad = isset($detalles_venta[$pro_codigo]) ? $detalles_venta[$pro_codigo]->total_cantidad : 0;

                // Actualizar la cantidad en f_detalle_proforma
                DB::table('f_detalle_proforma')
                    ->where('prof_id', $proforma->id)
                    ->where('pro_codigo', $pro_codigo)
                    ->update(['det_prof_cantidad' => $nueva_cantidad]);

                // Calcular la diferencia de cantidad (cuánto se "quitó" de la proforma)
                $cantidad_anterior = $detalle->det_prof_cantidad;
                $diferencia = $cantidad_anterior - $nueva_cantidad;

                if ($diferencia != 0) {
                    // Buscar el producto
                    $producto = _14Producto::findOrFail($pro_codigo);

                    // Guardar valores antes de actualizar (old_values)
                    $old_values = [
                        'pro_codigo' => $producto->pro_codigo,
                        'pro_reservar' => $producto->pro_reservar,
                        'pro_stock' => $producto->pro_stock,
                        'pro_stockCalmed' => $producto->pro_stockCalmed,
                        'pro_deposito' => $producto->pro_deposito,
                        'pro_depositoCalmed' => $producto->pro_depositoCalmed,
                    ];

                    // Sumar la diferencia al pro_reservar
                    $producto->pro_reservar += $diferencia;
                    $producto->save();

                    // Guardar valores después de actualizar (new_values)
                    $new_values = [
                        'pro_codigo' => $producto->pro_codigo,
                        'pro_reservar' => $producto->pro_reservar,
                        'pro_stock' => $producto->pro_stock,
                        'pro_stockCalmed' => $producto->pro_stockCalmed,
                        'pro_deposito' => $producto->pro_deposito,
                        'pro_depositoCalmed' => $producto->pro_depositoCalmed,
                    ];

                    // Verificar si hubo cambios
                    $cambios = false;
                    foreach (['pro_reservar', 'pro_stock', 'pro_stockCalmed', 'pro_deposito', 'pro_depositoCalmed'] as $campo) {
                        if ($old_values[$campo] != $new_values[$campo]) {
                            $cambios = true;
                            break;
                        }
                    }

                    // Solo agregar al historial si hubo cambios
                    if ($cambios) {
                        $HistoricoStock[] = [
                            'pro_codigo' => $pro_codigo,
                            'psh_old_values' => json_encode($old_values),
                            'psh_new_values' => json_encode($new_values),
                        ];
                    }
                }
            }

            // Guardar el historial de stock si hay cambios
            if (!empty($HistoricoStock)) {
                $registroHistorial = [
                    'psh_old_values' => json_encode(array_column($HistoricoStock, 'psh_old_values', 'pro_codigo')),
                    'psh_new_values' => json_encode(array_column($HistoricoStock, 'psh_new_values', 'pro_codigo')),
                    'psh_tipo' => 10, // Tipo de histórico, ajusta según tu lógica
                    'psh_proforma' => $proforma->prof_id,
                    'user_created' => $user_created,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                _14ProductoStockHistorico::insert($registroHistorial);
            }

            // Actualizar el estado de la proforma a Finalizada (2)
            DB::table('f_proforma')
                ->where('id', $proforma->id)
                ->update(['prof_estado' => 2]);

            // Llamar al repositorio para actualizar los valores financieros
            $this->devolucionRepository->updateValoresDocumentoF_venta($ven_codigo, $codigo_empresa);
            $this->devolucionRepository->updateValoresDocumentoF_proforma($ven_codigo, $codigo_empresa);

            // Confirmar la transacción
            DB::commit();

            return response()->json(["status" => "1", 'message' => 'Venta, proforma y stock actualizados correctamente']);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            return response()->json(["status" => "0", 'message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function ObtenerDetallesProformaVenta(Request $request)
    {
        try {

            // Validar parámetros de entrada
            $validated = $request->validate([
                'id_empresa' => 'required|integer',
                'ven_idproforma' => 'required',
            ]);

            $codigo_empresa = $validated['id_empresa'];
            $ven_idproforma = $validated['ven_idproforma'];

            // Obtener la proforma
            $proforma = DB::table('f_proforma', 'fp')
                ->where('fp.emp_id', $codigo_empresa)
                ->where('fp.prof_id', $ven_idproforma)
                ->first();

            if (!$proforma) {
                return response()->json(["status" => "0", 'message' => 'Proforma no encontrada'], 404);
            }

            // Obtener los detalles de la proforma
            $detalles_proforma = DB::table('f_detalle_proforma')
                ->where('prof_id', $proforma->id)
                ->join('1_4_cal_producto', 'f_detalle_proforma.pro_codigo', '=', '1_4_cal_producto.pro_codigo')
                ->select(
                    'f_detalle_proforma.pro_codigo',
                    '1_4_cal_producto.pro_nombre',
                    'f_detalle_proforma.det_prof_cantidad as cantidad_proforma'
                )
                ->get()
                ->keyBy('pro_codigo');

            // Obtener detalles de ventas asociadas a la proforma con ven_codigo
            $detalles_venta = DB::table('f_venta', 'fv')
                ->where('fv.id_empresa', $codigo_empresa)
                ->where('fv.ven_idproforma', $ven_idproforma)
                ->where('fv.est_ven_codigo', '<>', 3)
                ->whereNull('fv.doc_intercambio')
                ->join('f_detalle_venta', function ($join) {
                    $join->on('fv.ven_codigo', '=', 'f_detalle_venta.ven_codigo')
                        ->on('fv.id_empresa', '=', 'f_detalle_venta.id_empresa');
                })
                ->join('1_4_cal_producto', 'f_detalle_venta.pro_codigo', '=', '1_4_cal_producto.pro_codigo')
                ->select(
                    'f_detalle_venta.pro_codigo',
                    '1_4_cal_producto.pro_nombre',
                    'f_detalle_venta.ven_codigo',
                    'f_detalle_venta.det_ven_cantidad as cantidad_venta'
                )
                ->get();

            // Agrupar detalles de venta por pro_codigo y mantener ven_codigo
            $ventas_por_producto = [];
            // Obtener un ven_codigo cualquiera (por ejemplo, el primero que aparezca)
            $venta_ejemplo = null;
            foreach ($detalles_venta as $detalle) {
                $pro_codigo = $detalle->pro_codigo;
                if (!isset($ventas_por_producto[$pro_codigo])) {
                    $ventas_por_producto[$pro_codigo] = [
                        'pro_nombre' => $detalle->pro_nombre,
                        'cantidad_venta' => 0,
                        'ventas_origen' => []
                    ];
                }
                $ventas_por_producto[$pro_codigo]['cantidad_venta'] += (int)$detalle->cantidad_venta;
                $ventas_por_producto[$pro_codigo]['ventas_origen'][] = [
                    'ven_codigo' => $detalle->ven_codigo,
                    'cantidad' => (int)$detalle->cantidad_venta
                ];
            }

            // Combinar detalles y calcular diferencias
            $detalles_combinados = [];

            // Procesar productos de la proforma
            foreach ($detalles_proforma as $detalle_prof) {
                $pro_codigo = $detalle_prof->pro_codigo;
                $cantidad_proforma = (int)$detalle_prof->cantidad_proforma;
                $venta_info = isset($ventas_por_producto[$pro_codigo]) ? $ventas_por_producto[$pro_codigo] : null;
                $cantidad_venta = $venta_info ? $venta_info['cantidad_venta'] : 0;
                $ventas_origen = $venta_info ? $venta_info['ventas_origen'] : [];
                $diferencia = $cantidad_proforma - $cantidad_venta;

                // Solo incluir si hay una diferencia
                if ($diferencia != 0) {
                    $detalles_combinados[] = [
                        'pro_codigo' => $pro_codigo,
                        'pro_nombre' => $detalle_prof->pro_nombre,
                        'cantidad_proforma' => $cantidad_proforma,
                        'cantidad_venta' => $cantidad_venta,
                        'diferencia' => $diferencia,
                        'prof_id' => $ven_idproforma,
                        'ventas_origen' => $ventas_origen
                    ];
                }
            }

            // Procesar productos que están en las ventas pero no en la proforma
            foreach ($ventas_por_producto as $pro_codigo => $venta_info) {
                if (!$detalles_proforma->has($pro_codigo)) {
                    $detalles_combinados[] = [
                        'pro_codigo' => $pro_codigo,
                        'pro_nombre' => $venta_info['pro_nombre'],
                        'cantidad_proforma' => 0,
                        'cantidad_venta' => $venta_info['cantidad_venta'],
                        'diferencia' => 0 - $venta_info['cantidad_venta'],
                        'prof_id' => $ven_idproforma,
                        'ventas_origen' => $venta_info['ventas_origen']
                    ];
                }
            }

            // Respuesta
            if (empty($detalles_combinados)) {
                return response()->json([
                    "status" => "1",
                    "data" => [],
                    "message" => "No hay diferencias entre los detalles de la proforma y las ventas"
                ]);
            }
            $venta_asociada = DB::table('f_venta')
            ->where('id_empresa', $codigo_empresa)
            ->where('ven_idproforma', $ven_idproforma)
            ->where('est_ven_codigo', '<>', 3)
            ->whereNull('doc_intercambio')
            ->select('ven_codigo')
            ->first();

            return response()->json([
                "status" => "1",
                "data" => $detalles_combinados,
                "message" => "Detalles con diferencias obtenidos correctamente",
                "venta" => $venta_asociada ? $venta_asociada->ven_codigo : null
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => '0',
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '0',
                'message' => 'Error al obtener detalles: ' . $e->getMessage()
            ], 500);
        }
    }

    public function GetVentaOfProforma(Request $request)
    {
        try {
            $codigo_empresa = $request->empresa;
            $prof_id = $request->prof_id;

            // Obtener la venta para obtener el prof_id
            $venta = DB::table('f_venta')
                ->where('id_empresa', $codigo_empresa)
                ->where('ven_idproforma', $prof_id)
                ->where('est_ven_codigo', '<>', 3)
                ->get();

            if (!$venta) {
                return response()->json(["status" => "0", 'message' => 'Venta no encontrada'], 404);
            }else{
                return response()->json($venta);
            }
        } catch (\Exception $e) {
            return response()->json(["status" => "0", 'message' => 'Error al obtener detalles: ' . $e->getMessage()], 500);
        }
    }

public function getVentasContratosDetallado(Request $request)
{
    $periodo = $request->periodo_id;

    // 1. Ventas del periodo 25
    $ventas = \DB::table('f_venta')
        ->where('periodo_id', $periodo)
        ->wherein('idtipodoc', [1, 3, 4])
        ->where('est_ven_codigo', '<>', 3)
        ->get();

    // Sacar los ven_codigo de esas ventas para buscar proformas
    $venCodigosProformas = $ventas->pluck('ven_idproforma')->toArray();

    // 2. Proformas relacionadas a esas ventas (prof_id = ven_codigo)
    $proformas = \DB::table('f_proforma')
        ->whereIn('prof_id', $venCodigosProformas)
        ->get();

    // Sacar idPuntoventa de esas proformas para contratos agrupados
    $idPuntoventas = $proformas->pluck('idPuntoventa')->toArray();

    // 3. Contratos agrupados relacionados a esas proformas (ca_codigo_agrupado = idPuntoventa)
    $contratosAgrupados = \DB::table('f_contratos_agrupados')
        ->whereIn('ca_codigo_agrupado', $idPuntoventas)
        ->get();

    // Sacar los códigos agrupados para buscar pedidos
    $codigosAgrupados = $contratosAgrupados->pluck('ca_codigo_agrupado')->toArray();

    // 4. Pedidos relacionados a esos contratos agrupados (ca_codigo_agrupado)
    $pedidos = \DB::table('pedidos')
        ->whereIn('ca_codigo_agrupado', $codigosAgrupados)
        ->get();

    $pedidos = \DB::table('pedidos')
    ->whereIn('ca_codigo_agrupado', $codigosAgrupados)
    ->get();

    // 5. Sacar id_asesor y otros datos que quieras mostrar
    $asesoresIds = $pedidos->pluck('id_asesor')->unique()->toArray();



    $venCodigosPedidosObsequios = $ventas->pluck('ven_p_libros_obsequios')
        ->filter(function($value) {
            return !is_null($value) && $value !== '';
        })
        ->values()
        ->toArray();

    $pedidosObsequios = \DB::table('p_libros_obsequios')
    ->whereIn('id', $venCodigosPedidosObsequios)
    ->get();


    $obsequiosUnicos = $pedidosObsequios->pluck('id_pedido')->unique()->toArray();


    $pedidosObsquiosContratos = \DB::table('pedidos')
        ->whereIn('id_pedido', $obsequiosUnicos)
        ->get();

    $asesoresObsequios = $pedidosObsquiosContratos->pluck('id_asesor')->unique()->toArray();

    $asesoresIdsObsequiosContratos = array_merge($asesoresIds, $asesoresObsequios);

     // Por ejemplo, sacar datos de asesores (si tienes tabla usuarios o asesores)
    $asesores = \DB::table('usuario') // Cambia 'usuario' por tabla real de asesores/usuarios
        ->whereIn('idusuario', $asesoresIdsObsequiosContratos)
        ->get();


    // Finalmente, puedes devolver un array con toda la info
    return response()->json([
        'ventas' => $ventas,
        'proformas' => $proformas,
        'contratosAgrupados' => $contratosAgrupados,
        'pedidos' => $pedidos,
        'asesores' => $asesores,
    ]);
}
// public function getDetallePorAsesor()
// {
//     $periodo = 26;

//     // 1. Obtener ventas filtradas igual que antes
//     $ventas = \DB::table('f_venta')
//         ->where('periodo_id', $periodo)
//         ->whereIn('idtipodoc', [1, 3, 4])
//         ->where('est_ven_codigo', '<>', 3)
//         ->get();

//     // Ven códigos para proformas
//     $venCodigosProformas = $ventas->pluck('ven_idproforma')->filter()->values()->toArray();

//     // Obtener proformas
//     $proformas = \DB::table('f_proforma')
//         ->whereIn('prof_id', $venCodigosProformas)
//         ->get();

//     // Sacar idPuntoventa para contratos agrupados
//     $idPuntoventas = $proformas->pluck('idPuntoventa')->unique()->toArray();

//     // Contratos agrupados
//     $contratosAgrupados = \DB::table('f_contratos_agrupados')
//         ->whereIn('ca_codigo_agrupado', $idPuntoventas)
//         ->get();

//     // Codigos agrupados para pedidos
//     $codigosAgrupados = $contratosAgrupados->pluck('ca_codigo_agrupado')->unique()->toArray();

//     // Pedidos
//     $pedidos = \DB::table('pedidos')
//         ->whereIn('ca_codigo_agrupado', $codigosAgrupados)
//         ->get();

//     // Obtener asesores únicos de pedidos
//     $asesoresIds = $pedidos->pluck('id_asesor')->unique()->toArray();

//     // Armar estructura: por cada asesor, sus pedidos, contratos, proformas y ventas

//     $resultado = [];

//     // Traer usuarios (asesores)
//     $usuarios = \DB::table('usuarios')
//         ->select('idusuario', 'name', 'apellido')
//         ->get()
//         ->keyBy('idusuario');

//     // Traer instituciones
//     $instituciones = \DB::table('institucion')
//         ->select('idInstitucion', 'nombreInstitucion')
//         ->get()
//         ->keyBy('idInstitucion');

//     foreach ($asesoresIds as $asesorId) {
//         // Pedidos del asesor
//         $pedidosAsesor = $pedidos->where('id_asesor', $asesorId);

//         // Obtener códigos agrupados de esos pedidos
//         $codigosAgrupadosAsesor = $pedidosAsesor->pluck('ca_codigo_agrupado')->unique()->toArray();

//         // Contratos agrupados relacionados a esos códigos
//         $contratosAsesor = $contratosAgrupados->whereIn('ca_codigo_agrupado', $codigosAgrupadosAsesor);

//         // Obtener idPuntoventa de contratos para buscar proformas
//         $idPuntoventasAsesor = $contratosAsesor->pluck('ca_codigo_agrupado')->unique()->toArray();

//         // Proformas relacionados a esos contratos
//         $proformasAsesor = $proformas->whereIn('idPuntoventa', $idPuntoventasAsesor);

//         // Ven códigos de esas proformas para ventas
//         $venCodigosProformasAsesor = $proformasAsesor->pluck('prof_id')->unique()->toArray();

//         // Ventas relacionadas a esas proformas
//         $ventasAsesor = $ventas->whereIn('ven_idproforma', $venCodigosProformasAsesor);

//         // Guardar todo en el resultado
//         $resultado[$asesorId] = [
//             'pedidos' => $pedidosAsesor->values(),
//             'contratosAgrupados' => $contratosAsesor->values(),
//             'proformas' => $proformasAsesor->values(),
//             'ventas' => $ventasAsesor->values(),
//         ];
//     }

//     return response()->json($resultado);
// }

public function getDetallePorAsesor(Request $request)
{
    $periodo = $request->periodo_id;

    // 1. Obtener ventas válidas
    $ventas = \DB::table('f_venta')
        ->where('periodo_id', $periodo)
        ->whereIn('idtipodoc', [1, 3, 4])
        ->where('est_ven_codigo', '<>', 3)
        ->get();

    $venCodigosProformas = $ventas->pluck('ven_idproforma')->filter()->values()->toArray();
    $venObsequioIds = $ventas->pluck('ven_p_libros_obsequios')->filter()->unique()->toArray();

    // 2. Obtener obsequios con su id_pedido
    $obsequios = \DB::table('p_libros_obsequios')
        ->whereIn('id', $venObsequioIds)
        ->get()
        ->keyBy('id'); // clave por ID del obsequio

    $proformas = \DB::table('f_proforma')
        ->whereIn('prof_id', $venCodigosProformas)
        ->get();

    $idPuntoventas = $proformas->pluck('idPuntoventa')->unique()->toArray();

    $contratosAgrupados = \DB::table('f_contratos_agrupados')
        ->whereIn('ca_codigo_agrupado', $idPuntoventas)
        ->get();

    $codigosAgrupados = $contratosAgrupados->pluck('ca_codigo_agrupado')->unique()->toArray();

    $pedidos = \DB::table('pedidos')
        ->join('f_contratos_agrupados as fc', 'pedidos.ca_codigo_agrupado', '=', 'fc.ca_codigo_agrupado')
        ->select('pedidos.*', 'fc.ca_descripcion','fc.ca_tipo_pedido')
        ->whereIn('pedidos.ca_codigo_agrupado', $codigosAgrupados)
        ->get();

    $asesoresIds = $pedidos->pluck('id_asesor')->unique()->toArray();
    $institucionesIds = $pedidos->pluck('id_institucion')->unique()->toArray();

    $usuarios = \DB::table('usuario')
        ->select('idusuario', 'nombres', 'apellidos')
        ->whereIn('idusuario', $asesoresIds)
        ->get()
        ->keyBy('idusuario');

    $instituciones = \DB::table('institucion')
        ->select('idInstitucion', 'nombreInstitucion')
        ->whereIn('idInstitucion', $institucionesIds)
        ->get()
        ->keyBy('idInstitucion');

    $resultado = [];

    foreach ($asesoresIds as $asesorId) {
        $pedidosAsesor = $pedidos->where('id_asesor', $asesorId)->map(function ($pedido) use ($instituciones) {
            $institucion = $instituciones[$pedido->id_institucion] ?? null;
            $pedido->nombre_institucion = $institucion ? $institucion->nombreInstitucion : 'Desconocida';
            return $pedido;
        });

        $codigosAgrupadosAsesor = $pedidosAsesor->pluck('ca_codigo_agrupado')->unique()->toArray();
        $contratosAsesor = $contratosAgrupados->whereIn('ca_codigo_agrupado', $codigosAgrupadosAsesor);
        $idPuntoventasAsesor = $contratosAsesor->pluck('ca_codigo_agrupado')->unique()->toArray();
        $proformasAsesor = $proformas->whereIn('idPuntoventa', $idPuntoventasAsesor);
        $venCodigosProformasAsesor = $proformasAsesor->pluck('prof_id')->unique()->toArray();

        // ➕ Ventas con proforma (agrupadas)
        $ventasAgrupadas = $ventas->whereIn('ven_idproforma', $venCodigosProformasAsesor)->map(function ($v) {
            $v->tipo_fuente = 'proforma';
            return $v;
        });

        // ➕ Ventas de obsequios: solo las que no están asociadas a proformas
        $ventasObsequioIndependientes = $ventas->filter(function ($venta) use ($obsequios, $pedidosAsesor) {
            if ($venta->ven_idproforma || !$venta->ven_p_libros_obsequios) return false;
            $obsequio = $obsequios[$venta->ven_p_libros_obsequios] ?? null;
            return $obsequio && $pedidosAsesor->pluck('id_pedido')->contains($obsequio->id_pedido);
        })->map(function ($venta) use ($obsequios) {
            $venta->tipo_fuente = 'obsequio';

            // ✅ Agregar manualmente el id_pedido desde el obsequio relacionado
            $obsequio = $obsequios[$venta->ven_p_libros_obsequios] ?? null;
            $venta->id_pedido = $obsequio ? $obsequio->id_pedido : null;

            return $venta;
        });


        $usuario = $usuarios[$asesorId] ?? null;
        $nombreAsesor = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : "Asesor $asesorId";

        $resultado[] = [
            'id_asesor' => $asesorId,
            'nombre_asesor' => $nombreAsesor,
            'pedidos' => $pedidosAsesor->values(),
            'contratosAgrupados' => $contratosAsesor->values(),
            'proformas' => $proformasAsesor->values(),
            'ventasAgrupadas' => $ventasAgrupadas->values(),
            'ventasObsequioIndependientes' => $ventasObsequioIndependientes->values(),
        ];
    }

    return response()->json($resultado);
}


//INICIO METODO JEYSON
// REPOIRTES DE NOTAS Y FACTURAS DE LA DB DE MILTON
public function ReporteContratos21_22()
{
    // 1️⃣ Traer ven_codigo únicos
    $notas = DB::select("SELECT DISTINCT nv.ven_codigo
        FROM notas_venta nv
        WHERE nv.est_ven_codigo <> 3
        -- SOLO ACTAS
        -- AND (nv.ven_codigo LIKE 'A-S23%' OR nv.ven_codigo LIKE 'A-C23%')
        AND (nv.ven_codigo LIKE 'A-C24%')
        -- SECCION SIERRA
        -- AND (nv.ven_codigo LIKE 'N-Z23%' OR nv.ven_codigo LIKE 'N-S23%')
        -- SECCION COSTA
        -- AND (nv.ven_codigo LIKE 'N-K24%' OR nv.ven_codigo LIKE 'N-C24%')
    ");

    $facturas = DB::select("SELECT DISTINCT fv.ven_codigo
        FROM facturas_venta fv
        WHERE fv.est_ven_codigo <> 3
        -- SOLO ACTAS
        -- AND (fv.ven_codigo LIKE 'A-S23%' OR fv.ven_codigo LIKE 'A-C23%')
        AND (FV.ven_codigo LIKE 'A-C24%')
        -- SECCION SIERRA
        -- AND (fv.ven_codigo LIKE 'F-Z23%' OR fv.ven_codigo LIKE 'F-S23%')
        -- SECCION COSTA
        -- AND (fv.ven_codigo LIKE 'F-K24%' OR fv.ven_codigo LIKE 'F-C24%')
    ");

    // 2️⃣ Añadir detalles con total para cada nota
    foreach ($notas as &$nota) {
        $detalles = DB::select("
            SELECT ndv.pro_codigo, ndv.det_ven_cantidad_real, ndv.det_ven_valor_u
            FROM notas_detalle_venta ndv
            WHERE ndv.ven_codigo = ?
        ", [$nota->ven_codigo]);

        foreach ($detalles as &$det) {
            $det->total = $det->det_ven_cantidad_real * $det->det_ven_valor_u;
        }

        $nota->detalles = $detalles;
    }

    // 3️⃣ Añadir detalles con total para cada factura
    foreach ($facturas as &$factura) {
        $detalles = DB::select("
            SELECT fdv.pro_codigo, fdv.det_ven_cantidad_real, fdv.det_ven_valor_u
            FROM facturas_detalle_venta fdv
            WHERE fdv.ven_codigo = ?
        ", [$factura->ven_codigo]);

        foreach ($detalles as &$det) {
            $det->total = $det->det_ven_cantidad_real * $det->det_ven_valor_u;
        }

        $factura->detalles = $detalles;
    }

    // 4️⃣ Agrupar todos los detalles por pro_codigo (mayúsculas y trim)
    $todosDetalles = [];

    foreach ($notas as $nota) {
        foreach ($nota->detalles as $det) {
            $codigo = strtoupper(trim($det->pro_codigo)); // <-- trim + strtoupper
            if (!isset($todosDetalles[$codigo])) {
                $todosDetalles[$codigo] = [
                    'pro_codigo' => $codigo,
                    'det_ven_cantidad_real' => 0,
                    'total' => 0,
                    'valores_u' => []
                ];
            }

            $todosDetalles[$codigo]['det_ven_cantidad_real'] += $det->det_ven_cantidad_real;
            $todosDetalles[$codigo]['total'] += $det->total;
            if (!in_array($det->det_ven_valor_u, $todosDetalles[$codigo]['valores_u'])) {
                $todosDetalles[$codigo]['valores_u'][] = $det->det_ven_valor_u;
            }
        }
    }

    foreach ($facturas as $factura) {
        foreach ($factura->detalles as $det) {
            $codigo = strtoupper(trim($det->pro_codigo)); // <-- trim + strtoupper
            if (!isset($todosDetalles[$codigo])) {
                $todosDetalles[$codigo] = [
                    'pro_codigo' => $codigo,
                    'det_ven_cantidad_real' => 0,
                    'total' => 0,
                    'valores_u' => []
                ];
            }

            $todosDetalles[$codigo]['det_ven_cantidad_real'] += $det->det_ven_cantidad_real;
            $todosDetalles[$codigo]['total'] += $det->total;
            if (!in_array($det->det_ven_valor_u, $todosDetalles[$codigo]['valores_u'])) {
                $todosDetalles[$codigo]['valores_u'][] = $det->det_ven_valor_u;
            }
        }
    }

    // 5️⃣ Convertir valores_u a string separado por comas
    $agrupado = array_map(function($item) {
        $item['valores_u'] = implode(', ', $item['valores_u']);
        return $item;
    }, array_values($todosDetalles));

    // 6️⃣ Traer todos los nombres de productos de notas_producto
    $pro_codigos = array_column($agrupado, 'pro_codigo');
    if (!empty($pro_codigos)) {
        $placeholders = implode(',', array_fill(0, count($pro_codigos), '?'));
        $productos = DB::select("
            SELECT pro_codigo, pro_nombre
            FROM notas_producto
            WHERE UPPER(TRIM(pro_codigo)) IN ($placeholders)
        ", $pro_codigos);

        // Mapear los nombres por código (normalizando mayúsculas + trim)
        $mapNombres = [];
        foreach ($productos as $prod) {
            $mapNombres[strtoupper(trim($prod->pro_codigo))] = $prod->pro_nombre;
        }

        // Añadir pro_nombre a cada item en agrupado
        foreach ($agrupado as &$item) {
            $item['pro_nombre'] = $mapNombres[$item['pro_codigo']] ?? null;
        }
    }

    return response()->json([
        "notas" => $notas,
        "facturas" => $facturas,
        "agrupado" => $agrupado
    ]);
}






public function ReporteContratos23_24(){
    // 1. Obtener contratos
    $contratos = DB::select("SELECT fv.ven_codigo, fv.id_empresa, fv.periodo_id
        FROM f_venta fv
        -- NOTAS Y FACTURAS
        -- WHERE (fv.idtipodoc = 1 OR fv.idtipodoc = 3 OR fv.idtipodoc = 4)
        -- ACTAS
        WHERE (fv.idtipodoc = 2)
        -- PERIODO SIERRA 24
        AND fv.periodo_id = 25
    ");

    // Variable global para todos los detalles agrupados
    $agrupado = [];

    // 2. Recorrer contratos y añadir detalle
    foreach ($contratos as &$contrato) {
        $detalle = DB::select("SELECT fd.pro_codigo, fd.det_ven_cantidad, fd.det_ven_valor_u, fd.det_ven_dev
            FROM f_detalle_venta fd
            WHERE fd.ven_codigo = ? AND fd.id_empresa = ?
        ", [$contrato->ven_codigo, $contrato->id_empresa]);

        // Crear subarray en cada contrato
        $contrato->detalle = $detalle;

        // Procesar para agrupado
        foreach ($detalle as $item) {
            $cantidad_real = $item->det_ven_cantidad - $item->det_ven_dev;
            $total = $cantidad_real * $item->det_ven_valor_u;

            if (!isset($agrupado[$item->pro_codigo])) {
                $agrupado[$item->pro_codigo] = [
                    "pro_codigo" => $item->pro_codigo,
                    "cantidades" => 0,
                    "totales" => 0,
                    "valores_unitarios" => [],
                ];
            }

            // Sumar cantidad real
            $agrupado[$item->pro_codigo]["cantidades"] += $cantidad_real;

            // Sumar total
            $agrupado[$item->pro_codigo]["totales"] += $total;

            // Guardar valor unitario
            if (!in_array($item->det_ven_valor_u, $agrupado[$item->pro_codigo]["valores_unitarios"])) {
                $agrupado[$item->pro_codigo]["valores_unitarios"][] = $item->det_ven_valor_u;
            }
        }
    }

    // 3. Ajustar salida agrupada
    $agrupado_final = [];
    foreach ($agrupado as $data) {
        $agrupado_final[] = [
            "pro_codigo" => $data["pro_codigo"],
            "cantidad_real" => $data["cantidades"],
            "total" => $data["totales"],
            "valor_unitario" => count($data["valores_unitarios"]) == 1
                ? $data["valores_unitarios"][0]
                : implode(", ", $data["valores_unitarios"])
        ];
    }

    // 4. Obtener nombres desde 1_4_cal_producto
    $codigos = array_column($agrupado_final, "pro_codigo");

    if (!empty($codigos)) {
        $productos = DB::table("1_4_cal_producto")
            ->select("pro_codigo", "pro_nombre")
            ->whereIn("pro_codigo", $codigos)
            ->get()
            ->keyBy("pro_codigo"); // indexar por pro_codigo

        // Añadir pro_nombre a cada item
        foreach ($agrupado_final as &$item) {
            $item["pro_nombre"] = $productos[$item["pro_codigo"]]->pro_nombre ?? null;
        }
    }

    // 5. Retornar todo
    return response()->json([
        "detallado" => $contratos,
        "agrupado" => $agrupado_final
    ]);
}

//FIN METODO JEYSON

    public function codigosXcontrato(Request $request)
    {
        $contrato = $request->input('id_contrato');
        $periodo  = $request->input('id_periodo'); // Asegúrate de recibir este valor en el request

        $contratos = DB::table('codigoslibros as c')
            ->leftJoin('pedidos_formato_new as f', function($join) use ($periodo) {
                $join->on('f.idlibro', '=', 'c.libro_idlibro')
                    ->where('f.idperiodoescolar', '=', $periodo);
            })
            ->where('c.contrato', $contrato)
            ->where('c.prueba_diagnostica', 0)
            ->where(function($q) {
                $q->where('c.quitar_de_reporte', 0)
                ->orWhereNull('c.quitar_de_reporte');
            })
            ->select(
                'c.*',         // columnas de codigoslibros
                'f.pfn_pvp'          // columnas de pedidos_formato_new
            )
            ->get();

        return response()->json($contratos);
    }

    /**
     * Consultar stock de libros por códigos de productos
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarStockLibros(Request $request)
    {
        try {
            // Validar que se envíen los códigos
            $codigos = $request->input('codigos', []);
            
            if (empty($codigos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se proporcionaron códigos de productos'
                ], 400);
            }
            
            // Si viene como string separado por comas, convertir a array
            if (is_string($codigos)) {
                $codigos = explode(',', $codigos);
            }
            
            // Limpiar y normalizar los códigos
            $codigos = array_map('trim', $codigos);
            $codigos = array_filter($codigos); // Eliminar valores vacíos
            
            if (empty($codigos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se proporcionaron códigos válidos'
                ], 400);
            }
            
            // Consultar stock de los productos
            $productos = DB::table('1_4_cal_producto')
                ->whereIn('pro_codigo', $codigos)
                ->select('pro_codigo', 'pro_reservar', 'pro_stock', 'pro_nombre', 'pro_estado')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $productos
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar el stock: ' . $e->getMessage()
            ], 500);
        }
    }

}
