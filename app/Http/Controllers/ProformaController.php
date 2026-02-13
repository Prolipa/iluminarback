<?php

namespace App\Http\Controllers;
use App\Models\Proforma;
use App\Models\DetalleProforma;
use App\Models\f_tipo_documento;
use App\Models\_14Producto;
use App\Models\Institucion;
use App\Models\InstitucionSucursales;
use App\Models\Ventas;
use App\Models\Proformahistorico;
use DB;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\Pedidos\TraitPedidosGeneral;
use App\Traits\Pedidos\TraitProforma;
use Illuminate\Http\Request;
use Mockery\Undefined;
use PhpParser\Node\Stmt\ElseIf_;
use App\Models\NotificacionGeneral;
use App\Repositories\pedidos\NotificacionRepository;

class ProformaController extends Controller
{
    use TraitProforma;
    use TraitPedidosGeneral;
    public $NotificacionRepository;
    public function __construct(NotificacionRepository $NotificacionRepository)
    {
        $this->NotificacionRepository   = $NotificacionRepository;
    }
    //consultas
    public function Get_MinStock(Request $request){
        $query = DB::SELECT("SELECT minimo as min, maximo as max  from configuracion_general where id='$request->nombre'");
        if(!empty($query)){
            //reservar
            if($request->nombre=='1'){
                $codi=(int)$query[0]->min;
                return $codi;
                //descuento
            }else if($request->nombre=='6'){
                $codi=(int)$query[0]->min;
                $cod=(int)$query[0]->max;
                $array = array($codi,$cod);
                return $array;
            }else{
                $codi=(int)$query[0]->min;
                return $codi;
            }
        }
    }
    //datos para librerias
    public function Get_DatoSolicitud(Request $request){
        if($request->tipo==0){
            $query = DB::SELECT("SELECT  COUNT(fp.prof_id) as con
            FROM f_proforma as fp
            inner join  pedidos as p on p.ca_codigo_agrupado=fp.idPuntoventa
            INNER JOIN  f_venta as fv ON fv.ven_idproforma= fp.prof_id
            WHERe fp.idPuntoventa='$request->prof_id'
            -- AND fp.idPuntoventa=fv.institucion_id
            AND (fp.prof_tipo_proforma=1 OR fp.prof_tipo_proforma=2)
            AND fv.est_ven_codigo=1");
        }
        if($request->tipo==1){
            $query = DB::SELECT("SELECT  COUNT(fp.prof_id) as con FROM f_proforma as fp
            inner join  pedidos as p on p.id_pedido=fp.pedido_id
            INNER JOIN  f_venta as fv ON fv.ven_idproforma= fp.prof_id
            WHERe fp.pedido_id='$request->prof_id' AND p.id_institucion=fv.institucion_id AND (fp.prof_tipo_proforma=1 OR fp.prof_tipo_proforma=2) AND fv.est_ven_codigo=1");

        }
             return $query;
    }
    //libreria funcion
    public function Get_pedidoSolicitud(Request $request){
        $query = DB::SELECT("SELECT * FROM f_contratos_usuarios_agrupados fcua
        INNER JOIN f_contratos_agrupados fc ON fcua.ca_id=fc.ca_id
        WHERE fcua.idusuario=$request->usuario and fcua.fcu_estado=1 ");
        $datos = [];
        foreach($query as $key => $item){
            $query2 = DB::SELECT("SELECT COUNT(f.id) AS finalizado
                FROM f_proforma f
                WHERE f.idPuntoventa = '$item->ca_codigo_agrupado'
                AND f.prof_estado = 2");
                if(count($query2) > 0){
                    $finalizado = $query2[0]->finalizado;
                }
            $query3=DB::SELECT("SELECT COUNT(f.id) AS anulado
                FROM f_proforma f
                WHERE f.idPuntoventa = '$item->ca_codigo_agrupado'
                AND f.prof_estado =0");
                if(count($query3) > 0){
                    $anulado = $query3[0]->anulado;
                }
           $query4=DB::SELECT("SELECT COUNT(f.id) AS pendiente
                FROM f_proforma f
                WHERE f.idPuntoventa = '$item->ca_codigo_agrupado'
                AND f.prof_estado<>0 and f.prof_estado<>2");
                if(count($query4) > 0){
                    $pendiente = $query4[0]->pendiente;
                }
                $total=(int)$finalizado+(int)$anulado+(int)$pendiente;
                $datos[] = array(
                    'fcu_id' => $item->fcu_id,
                    'ca_id' => $item->ca_id,
                    'idusuario' => $item->idusuario,
                    'ca_descripcion'=>$item->ca_descripcion,
                    'ca_codigo_agrupado' => $item->ca_codigo_agrupado,
                    'ca_tipo_pedido' => $item->ca_tipo_pedido,
                    'ca_cantidad' => $item->ca_cantidad,
                    'id_periodo' => $item->id_periodo,
                    'finalizado' => (int)$finalizado,
                    'anulado'   => (int)$anulado,
                    'pendiente' => (int)$pendiente,
                    'total' => (int)$total,
                );
        }
        return $datos;
    }
    public function Get_proformasCliente(Request $request){
        $datos = DB::SELECT("SELECT 
                fp.id,
                fp.prof_id,
                fp.idPuntoventa,
                fp.prof_estado,
                SUM(dpr.det_prof_cantidad) AS cantidad,
                fcg.ca_codigo_agrupado,
                fcg.id_periodo,
                pe.descripcion
            FROM 
                f_detalle_proforma AS dpr
            INNER JOIN 
                f_proforma AS fp ON dpr.prof_id = fp.id
            INNER JOIN 
                libros_series AS ls ON dpr.pro_codigo = ls.codigo_liquidacion
            INNER JOIN 
                1_4_cal_producto AS p ON dpr.pro_codigo = p.pro_codigo
            INNER JOIN 
                series AS s ON ls.id_serie = s.id_serie
            INNER JOIN 
                libro AS l ON ls.idLibro = l.idlibro
            LEFT JOIN 
                f_contratos_agrupados AS fcg ON fcg.ca_codigo_agrupado = fp.idPuntoventa
            LEFT JOIN 
                periodoescolar AS pe ON pe.idperiodoescolar = fcg.id_periodo
            WHERE 
                fp.ven_cliente = '$request->usuario' OR fp.usuario_solicitud = '$request->usuario'
            GROUP BY 
                fp.id, fp.prof_id, fp.idPuntoventa,fp.prof_estado,fcg.ca_codigo_agrupado,fcg.id_periodo;
        ");
        return $datos;
    }

    public function Get_DatosFactura(Request $request){
        $datos = [];
        if($request->empresa == 1){
            $array1 = DB::SELECT("SELECT fp.prof_id, fp.emp_id, dpr.det_prof_id, dpr.pro_codigo, dpr.det_prof_cantidad, dpr.det_prof_cantidad AS cantidad,dpr.det_prof_valor_u,
                ls.nombre, s.nombre_serie, fp.pro_des_por, fp.prof_iva_por, p.pro_stock as facturas_prolipa, p.pro_stockCalmed as facturas_calmed,
                p.pro_deposito as bodega_prolipa, p.pro_depositoCalmed as bodega_calmed, p.pro_reservar, l.descripcionlibro, ls.id_serie
                FROM f_detalle_proforma as dpr
                INNER JOIN  f_proforma as fp on dpr.prof_id=fp.id
                INNER JOIN libros_series as ls ON dpr.pro_codigo=ls.codigo_liquidacion
                INNER JOIN 1_4_cal_producto as p on dpr.pro_codigo=p.pro_codigo
                INNER JOIN series as s ON ls.id_serie=s.id_serie
                INNER JOIN libro l ON ls.idLibro = l.idlibro
                WHERe dpr.prof_id='$request->prof_id'
            ");
        }else if($request->empresa == 3){
            $array1 = DB::SELECT("SELECT fp.prof_id, fp.emp_id, dpr.det_prof_id, dpr.pro_codigo, dpr.det_prof_cantidad, dpr.det_prof_cantidad AS cantidad,dpr.det_prof_valor_u,
                ls.nombre, s.nombre_serie, fp.pro_des_por, fp.prof_iva_por, p.pro_stock as facturas_prolipa, p.pro_stockCalmed as facturas_calmed,
                p.pro_deposito as bodega_prolipa, p.pro_depositoCalmed as bodega_calmed, p.pro_reservar, l.descripcionlibro, ls.id_serie
                FROM f_detalle_proforma as dpr
                INNER JOIN  f_proforma as fp on dpr.prof_id=fp.id
                INNER JOIN libros_series as ls ON dpr.pro_codigo=ls.codigo_liquidacion
                INNER JOIN 1_4_cal_producto as p on dpr.pro_codigo=p.pro_codigo
                INNER JOIN series as s ON ls.id_serie=s.id_serie
                INNER JOIN libro l ON ls.idLibro = l.idlibro
                WHERe dpr.prof_id='$request->prof_id'
            ");
        }else{
            $array1 = DB::SELECT("SELECT fp.prof_id, fp.emp_id, dpr.det_prof_id, dpr.pro_codigo, dpr.det_prof_cantidad, dpr.det_prof_cantidad AS cantidad,dpr.det_prof_valor_u,
            ls.nombre, s.nombre_serie, fp.pro_des_por, fp.prof_iva_por, p.pro_stock as facturas_prolipa, p.pro_stockCalmed as facturas_calmed,
            p.pro_deposito as bodega_prolipa, p.pro_depositoCalmed as bodega_calmed, p.pro_reservar, l.descripcionlibro, ls.id_serie
            FROM f_detalle_proforma as dpr
            INNER JOIN  f_proforma as fp on dpr.prof_id=fp.id
            INNER JOIN libros_series as ls ON dpr.pro_codigo=ls.codigo_liquidacion
            INNER JOIN 1_4_cal_producto as p on dpr.pro_codigo=p.pro_codigo
            INNER JOIN series as s ON ls.id_serie=s.id_serie
            INNER JOIN libro l ON ls.idLibro = l.idlibro
            WHERe dpr.prof_id='$request->prof_id'
        ");
        }
       
        foreach($array1 as $key => $item){
            $cantidad = 0;
            if($item->prof_id&&$item->emp_id){
                    $array2 = DB::SELECT(" SELECT dfv.pro_codigo, SUM(dfv.det_ven_cantidad) AS cant from f_detalle_venta AS dfv
                    inner join f_venta AS fv ON dfv.ven_codigo = fv.ven_codigo
                    where fv.ven_idproforma='$item->prof_id' AND fv.id_empresa=$item->emp_id AND (fv.est_ven_codigo = 2 OR fv.est_ven_codigo = 1) AND dfv.pro_codigo = '$item->pro_codigo' group BY dfv.pro_codigo
                    ");
                    if(count($array2) > 0){
                        $cantidad = $array2[0]->cant;
                    }
            }

            $datos[] = array(
                'det_prof_id' => $item->det_prof_id,
                'pro_codigo' => $item->pro_codigo,
                'det_prof_cantidad' => $item->det_prof_cantidad,
                'cantidad'=>$item->det_prof_cantidad,
                'det_prof_valor_u' => $item->det_prof_valor_u,
                'nombre' => $item->nombre,
                'nombre_serie' => $item->nombre_serie,
                'facturas_prolipa' => $item->facturas_prolipa,
                'facturas_calmed' => $item->facturas_calmed,
                'bodega_prolipa' => $item->bodega_prolipa,
                'bodega_calmed' => $item->bodega_calmed,
                'cant'   => (int)$cantidad,
                'pro_reservar' => $item->pro_reservar,
                'descripcion' => $item->descripcionlibro,
                'id_serie' => $item->id_serie,
            );

        }
        return $datos;

    }
    public function Get_PuntosV(){
        $query = DB::SELECT("SELECT  i.idInstitucion, i.nombreInstitucion,i.ruc,i.email,i.telefonoInstitucion,
        i.direccionInstitucion, i.idrepresentante, u.cedula,
        CONCAT(u.nombres,' ',u.apellidos) as representante
        FROM institucion i
        LEFT JOIN usuario u ON i.idrepresentante=u.idusuario
        WHERE i.punto_venta=1
        AND i.estado_idEstado=1
        ");
        return $query;
    }
    public function Get_InstituV(Request $request){
        $query = DB::SELECT("SELECT  idInstitucion, nombreInstitucion,ruc,email,telefonoInstitucion, direccionInstitucion, punto_venta FROM institucion WHERE idInstitucion=$request->id_insti AND estado_idEstado=1");
        return $query;
    }
    public function Get_sucursalV(Request $request){
        $query = DB::SELECT("SELECT isuc_id, isuc_nombre, isuc_ruc, isuc_direccion FROM institucion_sucursales
        WHERE isuc_idInstitucion=$request->id_insti and isuc_estado=1");
        return $query;
    }
    public function Get_sucur(Request $request){
        $query = DB::SELECT("SELECT * FROM institucion_sucursales
        WHERE isuc_id=$request->id_insti");
        return $query;
    }

    public function GetAseClieuser(Request $request){
        if($request->tipo==0){
            $query1 = DB::SELECT("SELECT us.* FROM institucion as ins
        inner join usuario as us on ins.asesor_id = us.idusuario
            where ins.idInstitucion='$request->id'");
         return $query1;
        }
        if($request->tipo==1){
            $query1 = DB::SELECT("SELECT usu.* FROM institucion as ins
        inner join usuario as usu on ins.idrepresentante= usu.idusuario
            where ins.idInstitucion='$request->id'");
         return $query1;
        }
        if($request->tipo==2){
            $query1 = DB::SELECT("SELECT us.*FROM pedidos AS pe
			INNER JOIN pedidos_beneficiarios AS pb ON pe.id_pedido=pb.id_pedido
        	INNER JOIN usuario as us on pb.id_usuario = us.idusuario
        WHERE pe.id_pedido='$request->id'");
         return $query1;
        }
        if($request->tipo==3){
            $query1 = DB::SELECT("SELECT *FROM usuario u WHERE u.cedula LIKE '%$request->id%'");
         return $query1;
        }
    }
    public function GetProuser(Request $request){
        $query1 = DB::SELECT("SELECT  us.nombres as nombre, us.apellidos as apellido FROM f_proforma as pro
        inner join usuario as us on pro.usu_codigo = us.idusuario
        where pro.prof_id='$request->pro_id'");
             if(!empty($query1)){
                $cod= $query1[0]->nombre;
                $codi= $query1[0]->apellido;
                $user=$cod." ".$codi;
             }

             return $user;
    }

    public function GetProformaGeneral(Request $request){
        if($request->estadoproforma == 0){
            $query = DB::SELECT("SELECT CONCAT(usa.nombres, ' ', usa.apellidos) AS usercreator, CONCAT(us.nombres, ' ', us.apellidos) AS usuarioeditor,
            fca.ca_descripcion, fca.id_periodo, fpr.*,  em.nombre, us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item,
            SUM(dpr.det_prof_cantidad) AS libros,fca.ca_tipo_pedido,CONCAT(usc.nombres,' ',usc.apellidos) as cliente
            FROM f_proforma fpr
            LEFT JOIN empresas em ON fpr.emp_id =em.id
            INNER JOIN f_detalle_proforma dpr ON dpr.prof_id=fpr.id
            INNER JOIN f_contratos_agrupados fca ON fpr.idPuntoventa = fca.ca_codigo_agrupado
            LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
            LEFT JOIN usuario usa ON fpr.usu_codigo = usa.idusuario
            left join usuario usc on fpr.ven_cliente = usc.idusuario
            WHERE fca.id_periodo = $request->periodorecibido
            AND fpr.prof_estado = 1
            GROUP BY fpr.id, fpr.prof_id, fpr.prof_observacion,em.nombre, em.img_base64, fca.id_periodo
            order by fpr.created_at DESC");
            return $query;
        }else if($request->estadoproforma == 1){
            $query = DB::SELECT("SELECT CONCAT(usa.nombres, ' ', usa.apellidos) AS usercreator, CONCAT(us.nombres, ' ', us.apellidos) AS usuarioeditor,
            fca.ca_descripcion, fca.id_periodo, fpr.*,  em.nombre, us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item,
            SUM(dpr.det_prof_cantidad) AS libros,fca.ca_tipo_pedido,CONCAT(usc.nombres,' ',usc.apellidos) as cliente
            FROM f_proforma fpr
            LEFT JOIN empresas em ON fpr.emp_id =em.id
            INNER JOIN f_detalle_proforma dpr ON dpr.prof_id=fpr.id
            INNER JOIN f_contratos_agrupados fca ON fpr.idPuntoventa = fca.ca_codigo_agrupado
            LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
            LEFT JOIN usuario usa ON fpr.usu_codigo = usa.idusuario
            left join usuario usc on fpr.ven_cliente = usc.idusuario
            WHERE fca.id_periodo = $request->periodorecibido AND fpr.prof_estado = 3
            GROUP BY fpr.id, fpr.prof_id, fpr.prof_observacion,em.nombre, em.img_base64, fca.id_periodo
            order by fpr.created_at DESC");
            return $query;
        }else if($request->estadoproforma == 2){
            $query = DB::SELECT("SELECT CONCAT(usa.nombres, ' ', usa.apellidos) AS usercreator, CONCAT(us.nombres, ' ', us.apellidos) AS usuarioeditor,
            fca.ca_descripcion, fca.id_periodo, fpr.*,  em.nombre, us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item,
            SUM(dpr.det_prof_cantidad) AS libros,fca.ca_tipo_pedido,CONCAT(usc.nombres,' ',usc.apellidos) as cliente
            FROM f_proforma fpr
            LEFT JOIN empresas em ON fpr.emp_id =em.id
            INNER JOIN f_detalle_proforma dpr ON dpr.prof_id=fpr.id
            INNER JOIN f_contratos_agrupados fca ON fpr.idPuntoventa = fca.ca_codigo_agrupado
            LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
            LEFT JOIN usuario usa ON fpr.usu_codigo = usa.idusuario
            left join usuario usc on fpr.ven_cliente = usc.idusuario
            WHERE fca.id_periodo = $request->periodorecibido AND fpr.prof_estado = 2
            GROUP BY fpr.id, fpr.prof_id, fpr.prof_observacion,em.nombre, em.img_base64, fca.id_periodo
            order by fpr.created_at DESC");
            return $query;
        }else if($request->estadoproforma == 3){
            $query = DB::SELECT("SELECT CONCAT(usa.nombres, ' ', usa.apellidos) AS usercreator, CONCAT(us.nombres, ' ', us.apellidos) AS usuarioeditor,
            fca.ca_descripcion, fca.id_periodo, fpr.*,  em.nombre, us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item,
            SUM(dpr.det_prof_cantidad) AS libros,fca.ca_tipo_pedido,CONCAT(usc.nombres,' ',usc.apellidos) as cliente
            FROM f_proforma fpr
            LEFT JOIN empresas em ON fpr.emp_id =em.id
            INNER JOIN f_detalle_proforma dpr ON dpr.prof_id=fpr.id
            INNER JOIN f_contratos_agrupados fca ON fpr.idPuntoventa = fca.ca_codigo_agrupado
            LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
            LEFT JOIN usuario usa ON fpr.usu_codigo = usa.idusuario
            left join usuario usc on fpr.ven_cliente = usc.idusuario
            WHERE fca.id_periodo = $request->periodorecibido AND fpr.prof_estado = 0
            GROUP BY fpr.id, fpr.prof_id, fpr.prof_observacion,em.nombre, em.img_base64, fca.id_periodo
            order by fpr.created_at DESC");
            return $query;
        }else if($request->estadoproforma == 4){
            $query = DB::SELECT("SELECT CONCAT(usa.nombres, ' ', usa.apellidos) AS usercreator, CONCAT(us.nombres, ' ', us.apellidos) AS usuarioeditor,
            fca.id_periodo, fca.ca_descripcion, fpr.*, us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item,
            SUM(dpr.det_prof_cantidad) AS libros,pe.codigo_contrato
            FROM f_proforma fpr
            INNER JOIN f_detalle_proforma dpr ON dpr.prof_id=fpr.id
            INNER JOIN f_contratos_agrupados fca ON fpr.idPuntoventa = fca.ca_codigo_agrupado
            LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
            LEFT JOIN usuario usa ON fpr.usu_codigo = usa.idusuario
            LEFT JOIN periodoescolar pe ON fca.id_periodo = pe.idperiodoescolar
            WHERE fca.id_periodo = $request->periodorecibido AND fpr.prof_id IS NULL
            AND fpr.prof_estado = 1
            GROUP BY fpr.id, fpr.prof_id, fpr.prof_observacion, fca.id_periodo
            order by fpr.created_at DESC");
            return $query;
        }
        else{
            return "Estado no controlado";
        }
    }

//INNER JOIN usuario usa ON fpr.usu_codigo=usa.idusuario
    public function GetProfarmas(Request $request){
        $tipo = $request->tipo;
            if($tipo ==0){
                $query = DB::SELECT("SELECT fpr.*, pe.observacion, em.nombre, em.img_base64, usa.nombres, usa.apellidos, us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item, SUM(dpr.det_prof_cantidad) AS libros
                FROM f_proforma fpr
                INNER JOIN pedidos pe ON fpr.pedido_id = pe.id_pedido
                INNER JOIN pedidos_beneficiarios AS pb ON fpr.pedido_id = pb.id_pedido
                INNER JOIN usuario usa ON pb.id_usuario = usa.idusuario
                LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
                INNER JOIN empresas em ON fpr.emp_id = em.id
                INNER JOIN f_detalle_proforma dpr ON dpr.prof_id = fpr.id
                WHERE fpr.pedido_id= $request->prof_id GROUP BY fpr.id, fpr.prof_id,fpr.prof_observacion, pe.observacion,em.nombre, em.img_base64, usa.nombres, usa.apellidos
                order by fpr.created_at desc");
                    return $query;
            }
            if($tipo ==1){
                $query = DB::SELECT("SELECT fpr.*,  em.nombre, em.img_base64,
                us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item, SUM(dpr.det_prof_cantidad) AS libros,
                CONCAT(COALESCE(usa.nombres, ''), ' ', COALESCE(usa.apellidos, '')) AS cliente,
                i.nombreInstitucion,i.ruc as rucPuntoVenta
                FROM f_proforma fpr
                LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
                LEFT JOIN empresas em ON fpr.emp_id =em.id
                INNER JOIN f_detalle_proforma dpr ON dpr.prof_id=fpr.id
                left join usuario usa on fpr.ven_cliente = usa.idusuario
                LEFT JOIN institucion i ON fpr.id_ins_depacho = i.idInstitucion
                WHERE fpr.idPuntoventa= '$request->prof_id'
                GROUP BY fpr.id, fpr.prof_id,fpr.prof_observacion,em.nombre, em.img_base64
                order by fpr.created_at desc");
                return $query;
            }
    }

    public function GetProformasSolicitud(Request $request) {
        $query = DB::select("
            SELECT 
                fpr.*,  
                em.nombre, 
                em.img_base64,
                us.nombres AS username, 
                us.apellidos AS lastname, 
                COUNT(dpr.pro_codigo) AS total_items, 
                SUM(dpr.det_prof_cantidad) AS total_libros,
                CONCAT(COALESCE(usa.nombres, ''), ' ', COALESCE(usa.apellidos, '')) AS cliente,
                i.nombreInstitucion,
                i.ruc AS rucPuntoVenta
            FROM f_proforma fpr
            LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
            LEFT JOIN empresas em ON fpr.emp_id = em.id
            INNER JOIN f_detalle_proforma dpr ON dpr.prof_id = fpr.id
            LEFT JOIN usuario usa ON fpr.ven_cliente = usa.idusuario
            LEFT JOIN institucion i ON fpr.id_ins_depacho = i.idInstitucion
            WHERE fpr.idPuntoventa = ? 
            AND (fpr.ven_cliente = ? OR fpr.usuario_solicitud = ?)
            GROUP BY fpr.id, fpr.prof_id, fpr.prof_observacion, em.nombre, em.img_base64, 
                     us.nombres, us.apellidos, i.nombreInstitucion, i.ruc
            ORDER BY fpr.created_at DESC
        ", [$request->prof_id, $request->usuario, $request->usuario]);
    
        return $query;
    }
    

    //generar codigo para la proforma
    public function Get_Cod_Pro(Request $request){
        if($request->id==1){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
        }else if ($request->id==3){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
        }
        $getSecuencia = 1;
        $secuencia = "0000001"; // Valor por defecto
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
    public function getNumeroDocumento($empresa){
        $letra ="";
        if($empresa == 1){
            $letra = "P";
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
        }else if ($empresa==3){
            $letra = "C";
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
        }
        $getSecuencia = 1;
        $secuencia = "0000001"; // Valor por defecto
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
            }

            return $letra."-".$secuencia;
    }
    //registro y edicion de datos de la proforma y detalles de proforma
    public function Proforma_Registrar_modificar(Request $request)
    {
        try {

            
            $miarray = json_decode($request->data_detalle);
            DB::beginTransaction();
    
            $proforma = new Proforma;
    
            if ($request->id_group == 1 || $request->id_group == 22 || $request->id_group == 23) {
                $id_empresa = $request->emp_id;
                $iniciales = $request->iniciales;
                $getNumeroDocumento = $this->getNumeroDocumento($id_empresa);
                $ven_codigo = "PP-" . $iniciales . "-" . $getNumeroDocumento;
                $proforma->prof_id = $ven_codigo;
            }
    
            // Asignación de valores al modelo Proforma
            $proforma->usu_codigo = $request->usu_codigo;
            $proforma->pedido_id = $request->pedido_id;
            $proforma->emp_id = $request->emp_id;
            $proforma->idPuntoventa = $request->idPuntoventa;
            $proforma->prof_observacion = $request->prof_observacion;
            $proforma->prof_observacion_libreria = $request->prof_observacion_libreria;
            $proforma->prof_com = $request->prof_com;
            $proforma->prof_descuento = $request->prof_descuento;
            $proforma->pro_des_por = $request->pro_des_por;
            $proforma->prof_iva = $request->prof_iva;
            $proforma->prof_iva_por = $request->prof_iva_por;
            $proforma->prof_total = $request->prof_total;
            $proforma->prof_estado = $request->prof_estado;
            $proforma->prof_tipo_proforma = $request->prof_tipo_proforma;
            $proforma->created_at = $request->created_at;
    
            if ($request->emp_id == 1) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            } else if ($request->emp_id == 3) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            }
    
            if (!empty($query1)) {
                $id = $query1[0]->id;
                $codi = $query1[0]->cod;
                $co = (int)$codi + 1;
                $tipo_doc = f_tipo_documento::findOrFail($id);
                if ($request->emp_id == 1) {
                    $tipo_doc->tdo_secuencial_Prolipa = $co;
                } else if ($request->emp_id == 3) {
                    $tipo_doc->tdo_secuencial_calmed = $co;
                }
                $tipo_doc->save();
            }
    
            $proforma->idtipodoc = 5;
            $proforma->id_ins_depacho = $request->id_ins_depacho;
            $proforma->ven_cliente = $request->ven_cliente;
            $proforma->clientesidPerseo = $request->clientesidPerseo;
            
            // Si el cliente está definido, obtener la cédula
            if ($request->ven_cliente) {
                $user = User::where('idusuario', $request->ven_cliente)->first();
                $getCedula = $user->cedula;
                $proforma->ruc_cliente = $getCedula;
            }
    
            // Guardar la proforma
            $proforma->save();
            $ultimo_id = $proforma->id;
    
            $proformaGuardada = Proforma::find($proforma->id);

            if (!$proformaGuardada) {
                throw new \Exception("No se pudo guardar la proforma", 500);
            }else{
                
                if($proformaGuardada->prof_id == null){
                    $proformaGuardada->usuario_solicitud = $request->idusuario;
                    $guardado = $proformaGuardada->save();
                    if(!$guardado){
                         // Manejar el error, quizás retornando un mensaje o ejecutando otra acción
                         throw new \Exception('Error al guardar usuario de la solicitud', 500);
                    }
                    $temporada = DB::table('f_contratos_agrupados')->where('ca_codigo_agrupado', $proforma->idPuntoventa)->first();
                    if (!$temporada) {
                        // Manejar el error, quizás retornando un mensaje o ejecutando otra acción
                         throw new \Exception('No se encontró la temporada', 500);
                    }
                    $notificacion = new NotificacionGeneral();
                    $notificacion->id_padre = $proformaGuardada->id;
                    $notificacion->tipo = 4;
                    $notificacion->estado = 0;
                    $notificacion->descripcion = 'Se creo una Solicitud de proforma';
                    $notificacion->user_created = $request->idusuario;
                    $notificacion->id_periodo = $temporada->id_periodo;
                    $notificacion->nombre = 'Solicitud de proforma';
                    $success      = $notificacion->save();
                    if($success){
                        $channel = 'admin.notifications_verificaciones';
                        $event = 'NewNotification';
                        $data = [
                            'message' => 'Nueva notificación',
                        ];
                        $this->NotificacionRepository->notificacionVerificaciones($channel, $event, $data);
                    }
                }
            }
    
    
            // Guardar los detalles de la proforma
            foreach ($miarray as $key => $item) {
                if ((int)$item->cantidad != 0) {
                    $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$item->codigo_liquidacion'");
                    $codi = $query1[0]->stoc;
                    $co = (int)$codi - (int)$item->cantidad;
                    $pro = _14Producto::findOrFail($item->codigo_liquidacion);
                    $pro->pro_reservar = $co;
                    $pro->save();
    
                    // Crear y guardar el detalle de la proforma
                    $DetalleProforma = new DetalleProforma;
                    $DetalleProforma->prof_id = $ultimo_id;
                    $DetalleProforma->pro_codigo = $item->codigo_liquidacion;
                    $DetalleProforma->det_prof_cantidad = (int)$item->cantidad;
                    $DetalleProforma->det_prof_valor_u = $item->precio;
                    $DetalleProforma->save();
                }
            }
    
            // Confirmar la transacción
            DB::commit();
    
            return response()->json(['message' => 'Proforma y detalles guardados con éxito'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["error" => "0", "message" => "No se pudo guardar", 'error' => $e->getMessage()], 500);
        }
    }

    public function Proforma_Registrar_modificar_solicitud(Request $request)
    {
        try {

            
            $miarray = json_decode($request->data_detalle);
            DB::beginTransaction();
    
            $proforma = new Proforma;
    
            if ($request->id_group == 1 || $request->id_group == 22 || $request->id_group == 23) {
                if($request->prof_tipo_proforma != 0){
                    $id_empresa = $request->emp_id;
                    $iniciales = $request->iniciales;
                    $getNumeroDocumento = $this->getNumeroDocumento($id_empresa);
                    $ven_codigo = "PP-" . $iniciales . "-" . $getNumeroDocumento;
                    $proforma->prof_id = $ven_codigo;
                }
                
            }
    
            // Asignación de valores al modelo Proforma
            $proforma->usu_codigo = $request->usu_codigo;
            $proforma->pedido_id = $request->pedido_id;
            $proforma->emp_id = $request->emp_id;
            $proforma->idPuntoventa = $request->idPuntoventa;
            $proforma->prof_observacion = $request->prof_observacion;
            $proforma->prof_observacion_libreria = $request->prof_observacion_libreria;
            $proforma->prof_com = $request->prof_com;
            $proforma->prof_descuento = $request->prof_descuento;
            $proforma->pro_des_por = $request->pro_des_por;
            $proforma->prof_iva = $request->prof_iva;
            $proforma->prof_iva_por = $request->prof_iva_por;
            $proforma->prof_total = $request->prof_total;
            $proforma->prof_estado = $request->prof_estado;
            $proforma->prof_tipo_proforma = $request->prof_tipo_proforma;
            $proforma->created_at = $request->created_at;
    
            if ($request->emp_id == 1) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            } else if ($request->emp_id == 3) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            }
    
            if (!empty($query1)) {
                $id = $query1[0]->id;
                $codi = $query1[0]->cod;
                $co = (int)$codi + 1;
                $tipo_doc = f_tipo_documento::findOrFail($id);
                if ($request->emp_id == 1) {
                    $tipo_doc->tdo_secuencial_Prolipa = $co;
                } else if ($request->emp_id == 3) {
                    $tipo_doc->tdo_secuencial_calmed = $co;
                }
                $tipo_doc->save();
            }
    
            $proforma->idtipodoc = 5;
            $proforma->id_ins_depacho = $request->id_ins_depacho;
            $proforma->ven_cliente = $request->ven_cliente;
            $proforma->clientesidPerseo = $request->clientesidPerseo;
            
            // Si el cliente está definido, obtener la cédula
            if ($request->ven_cliente) {
                $user = User::where('idusuario', $request->ven_cliente)->first();
                $getCedula = $user->cedula;
                $proforma->ruc_cliente = $getCedula;
            }
    
            // Guardar la proforma
            $proforma->save();
            $ultimo_id = $proforma->id;
    
            $proformaGuardada = Proforma::find($proforma->id);

            if (!$proformaGuardada) {
                throw new \Exception("No se pudo guardar la proforma", 500);
            }else{
                
                if($proformaGuardada->prof_id == null){
                    $proformaGuardada->usuario_solicitud = $request->idusuario;
                    $guardado = $proformaGuardada->save();
                    if(!$guardado){
                         // Manejar el error, quizás retornando un mensaje o ejecutando otra acción
                         throw new \Exception('Error al guardar usuario de la solicitud', 500);
                    }
                    $temporada = DB::table('f_contratos_agrupados')->where('ca_codigo_agrupado', $proforma->idPuntoventa)->first();
                    if (!$temporada) {
                        // Manejar el error, quizás retornando un mensaje o ejecutando otra acción
                         throw new \Exception('No se encontró la temporada', 500);
                    }
                    $notificacion = new NotificacionGeneral();
                    $notificacion->id_padre = $proformaGuardada->id;
                    $notificacion->tipo = 4;
                    $notificacion->estado = 0;
                    $notificacion->descripcion = 'Se creo una Solicitud de proforma';
                    $notificacion->user_created = $request->idusuario;
                    $notificacion->id_periodo = $temporada->id_periodo;
                    $notificacion->nombre = 'Solicitud de proforma';
                    $success      = $notificacion->save();
                    if($success){
                        $channel = 'admin.notifications_verificaciones';
                        $event = 'NewNotification';
                        $data = [
                            'message' => 'Nueva notificación',
                        ];
                        $this->NotificacionRepository->notificacionVerificaciones($channel, $event, $data);
                    }
                }
            }
    
    
            // Guardar los detalles de la proforma
            foreach ($miarray as $key => $item) {
                if ((int)$item->cantidad != 0) {
                    $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$item->codigo_liquidacion'");
                    $codi = $query1[0]->stoc;
                    $co = (int)$codi - (int)$item->cantidad;
                    $pro = _14Producto::findOrFail($item->codigo_liquidacion);
                    $pro->pro_reservar = $co;
                    $pro->save();
    
                    // Crear y guardar el detalle de la proforma
                    $DetalleProforma = new DetalleProforma;
                    $DetalleProforma->prof_id = $ultimo_id;
                    $DetalleProforma->pro_codigo = $item->codigo_liquidacion;
                    $DetalleProforma->det_prof_cantidad = (int)$item->cantidad;
                    $DetalleProforma->det_prof_valor_u = $item->precio;
                    $DetalleProforma->save();
                }
            }
    
            // Confirmar la transacción
            DB::commit();
    
            return response()->json(['message' => 'Proforma y detalles guardados con éxito'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["error" => "0", "message" => "No se pudo guardar", 'error' => $e->getMessage()], 500);
        }
    }
    
    public function PostProforma_Editar_solicitud(Request $request)
       {
        try{

            $miarray=json_decode($request->data_detalle);
            DB::beginTransaction();
                $oldvalue = Proforma::findOrFail($request->id);
                $proforma = Proforma::findOrFail($request->id);
                $emp_idEdit = $proforma->emp_id;
                $profIdStatus = $oldvalue->prof_id;
                // $proforma->prof_id = $request->prof_id;
                $proforma->emp_id = $request->emp_id;
                if($request->id_group == 1 || $request->id_group == 22|| $request->id_group == 23){
                    if($request->prof_tipo_proforma != 0){
                        //CODIGO PROF_IF
                        if($oldvalue->prof_id == null || $oldvalue->prof_id ==""){
                            $id_empresa         = $request->emp_id;
                            $iniciales        = $request->iniciales;
                            $getNumeroDocumento = $this->getNumeroDocumento($id_empresa);
                            $ven_codigo         = "PP-".$iniciales."-".$getNumeroDocumento;
                            $proforma->prof_id  = $ven_codigo;
                        }
                    }
                }
                //CODIGO PROF_IF
                if($request->prof_observacion){
                    $proforma->prof_observacion = $request->prof_observacion;
                }
                $proforma->prof_observacion_libreria = $request->prof_observacion_libreria == null || $request->prof_observacion_libreria == 'null' ? '' : $request->prof_observacion_libreria;
                $proforma->prof_descuento = $request->prof_descuento;
                $proforma->pro_des_por = $request->pro_des_por;
                $proforma->prof_iva = $request->prof_iva;
                $proforma->prof_iva_por = $request->prof_iva_por;
                $proforma->prof_total = $request->prof_total;
                $proforma->user_editor = $request->id_usua;
                if($request->estado){
                    $proforma->prof_estado = $request->estado;
                }
                $proforma->id_ins_depacho = $request->id_ins_depacho;
                $proforma->ven_cliente = $request->ven_cliente;
                if($request->ven_cliente){
                $user                   = User::where('idusuario',$request->ven_cliente)->first();
                $getCedula              = $user->cedula;
                $proforma->ruc_cliente  = $getCedula;
                }
                $proforma->clientesidPerseo = $request->clientesidPerseo;
                $proforma->save();
                if($proforma){
                    //======SECUENCIA======
                    //si la libreria crea la solicitud y el facturador al editar ricien elige la empresa para asignar el codigo de proforma
                    if($emp_idEdit == null || $emp_idEdit == 0 || $profIdStatus == null || $profIdStatus == ""){
                        if($request->emp_id==1){
                            $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
                        }else if($request->emp_id==3){
                            $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
                        }
                        if(!empty($query1)){
                            $id=$query1[0]->id;
                            $codi=$query1[0]->cod;
                            $co=(int)$codi+1;
                            $tipo_doc = f_tipo_documento::findOrFail($id);
                            if($request->emp_id==1){
                                $tipo_doc->tdo_secuencial_Prolipa = $co;
                            }else if($request->emp_id==3){
                                $tipo_doc->tdo_secuencial_calmed = $co;
                            }
                            $tipo_doc->save();
                        }
                    }
                    //======SECUENCIA======
                    $newvalue = Proforma::findOrFail($request->id);
                    $this->tr_GuardarEnHistorico($request->prof_id,$request->id_usua,$oldvalue,$newvalue);
                }
                foreach($miarray as $key => $item){
                    if($item->det_prof_id!=null ||$item->det_prof_id!="" ){
                        $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                        $codi=$query1[0]->stoc;
                        $co=(int)$codi+(int)$item->nuevo;
                        $pro= _14Producto::findOrFail($item->pro_codigo);
                        $pro->pro_reservar = $co;
                        $pro->save();
                        $proforma2 = DetalleProforma::findOrFail($item->det_prof_id);
                        $proforma2->det_prof_cantidad = $item->cantidad;
                        $proforma2->save();
                    }  else{
                        if((int)$item->cantidad != 0){
                            $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            $codi=$query1[0]->stoc;
                            $co=(int)$codi-(int)$item->cantidad;
                            $pro= _14Producto::findOrFail($item->pro_codigo);
                            $pro->pro_reservar = $co;
                            $pro->save();
                            $proforma1 = new DetalleProforma;
                            $proforma1->prof_id = $request->id;
                            $proforma1->pro_codigo = $item->pro_codigo;
                            $proforma1->det_prof_cantidad = (int)$item->cantidad;
                            $proforma1->det_prof_valor_u = $item->det_prof_valor_u;
                            $proforma1->save();
                        }
                    }

                }
            DB::commit();
            return response()->json(['message' => 'Proforma actualizado con éxito'], 200);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json(["error"=>"0", "message" => "No se pudo actualizar", 'error' => $e->getMessage()], 500);
        }
    }
    
    public function PostProforma_Editar(Request $request)
       {
        try{
            set_time_limit(6000000);
            ini_set('max_execution_time', 6000000);
            $miarray=json_decode($request->data_detalle);
            DB::beginTransaction();
                $oldvalue = Proforma::findOrFail($request->id);
                $proforma = Proforma::findOrFail($request->id);
                $emp_idEdit = $proforma->emp_id;
                $profIdStatus = $oldvalue->prof_id;
                // $proforma->prof_id = $request->prof_id;
                $proforma->emp_id = $request->emp_id;
                if($request->id_group == 1 || $request->id_group == 22|| $request->id_group == 23){
                //CODIGO PROF_IF
                    if($oldvalue->prof_id == null || $oldvalue->prof_id ==""){
                        $id_empresa         = $request->emp_id;
                        $iniciales        = $request->iniciales;
                        $getNumeroDocumento = $this->getNumeroDocumento($id_empresa);
                        $ven_codigo         = "PP-".$iniciales."-".$getNumeroDocumento;
                        $proforma->prof_id  = $ven_codigo;
                    }
                }
                //CODIGO PROF_IF
                if($request->prof_observacion){
                    $proforma->prof_observacion = $request->prof_observacion;
                }
                $proforma->prof_observacion_libreria = $request->prof_observacion_libreria == null || $request->prof_observacion_libreria == 'null' ? '' : $request->prof_observacion_libreria;
                $proforma->prof_descuento = $request->prof_descuento;
                $proforma->pro_des_por = $request->pro_des_por;
                $proforma->prof_iva = $request->prof_iva;
                $proforma->prof_iva_por = $request->prof_iva_por;
                $proforma->prof_total = $request->prof_total;
                $proforma->user_editor = $request->id_usua;
                if($request->estado){
                    $proforma->prof_estado = $request->estado;
                }
                $proforma->id_ins_depacho = $request->id_ins_depacho;
                $proforma->ven_cliente = $request->ven_cliente;
                if($request->ven_cliente){
                $user                   = User::where('idusuario',$request->ven_cliente)->first();
                $getCedula              = $user->cedula;
                $proforma->ruc_cliente  = $getCedula;
                }
                $proforma->clientesidPerseo = $request->clientesidPerseo;
                $proforma->save();
                if($proforma){
                    //======SECUENCIA======
                    //si la libreria crea la solicitud y el facturador al editar ricien elige la empresa para asignar el codigo de proforma
                    if($emp_idEdit == null || $emp_idEdit == 0 || $profIdStatus == null || $profIdStatus == ""){
                        if($request->emp_id==1){
                            $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
                        }else if($request->emp_id==3){
                            $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
                        }
                        if(!empty($query1)){
                            $id=$query1[0]->id;
                            $codi=$query1[0]->cod;
                            $co=(int)$codi+1;
                            $tipo_doc = f_tipo_documento::findOrFail($id);
                            if($request->emp_id==1){
                                $tipo_doc->tdo_secuencial_Prolipa = $co;
                            }else if($request->emp_id==3){
                                $tipo_doc->tdo_secuencial_calmed = $co;
                            }
                            $tipo_doc->save();
                        }
                    }
                    //======SECUENCIA======
                    $newvalue = Proforma::findOrFail($request->id);
                    $this->tr_GuardarEnHistorico($request->prof_id,$request->id_usua,$oldvalue,$newvalue);
                }
                foreach($miarray as $key => $item){
                    if($item->det_prof_id!=null ||$item->det_prof_id!="" ){
                        $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                        $codi=$query1[0]->stoc;
                        $co=(int)$codi+(int)$item->nuevo;
                        $pro= _14Producto::findOrFail($item->pro_codigo);
                        $pro->pro_reservar = $co;
                        $pro->save();
                        $proforma2 = DetalleProforma::findOrFail($item->det_prof_id);
                        $proforma2->det_prof_cantidad = $item->cantidad;
                        $proforma2->save();
                    }  else{
                        if((int)$item->cantidad != 0){
                            $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                            $codi=$query1[0]->stoc;
                            $co=(int)$codi-(int)$item->cantidad;
                            $pro= _14Producto::findOrFail($item->pro_codigo);
                            $pro->pro_reservar = $co;
                            $pro->save();
                            $proforma1 = new DetalleProforma;
                            $proforma1->prof_id = $request->id;
                            $proforma1->pro_codigo = $item->pro_codigo;
                            $proforma1->det_prof_cantidad = (int)$item->cantidad;
                            $proforma1->det_prof_valor_u = $item->det_prof_valor_u;
                            $proforma1->save();
                        }
                    }

                }
            DB::commit();
            return response()->json(['message' => 'Proforma actualizado con éxito'], 200);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json(["error"=>"0", "message" => "No se pudo actualizar", 'error' => $e->getMessage()], 500);
        }
    }
    public function PostProformaIntitu(Request $request)
       {
        if($request->idInstitucion!='' || $request->idInstitucion!=null ){

            $inst = Institucion::findOrFail($request->idInstitucion);
            $inst->ruc = $request->ruc;
            $inst->telefonoInstitucion = $request->telefonoInstitucion;
            $inst->direccionInstitucion= $request->direccionInstitucion;
            $inst->email = $request->email;
            $inst->save();
            if($inst){
                return $inst;
            }else{
                 return "No se pudo actualizar";
            }
        } else {
            return ["error"=>"0", "message" => "No se pudo actualizar"];
        }
    }
    public function PostProformaSucursal(Request $request)
       {
        if($request->isuc_id!='' || $request->isuc_id!=null ){
             $sucur = InstitucionSucursales::findOrFail($request->isuc_id);
            $sucur->isuc_ruc = $request->isuc_ruc;
            $sucur->isuc_telefono = $request->isuc_telefono;
            $sucur->isuc_direccion= $request->isuc_direccion;
            $sucur->isuc_correo = $request->isuc_correo;
            $sucur->save();
            if($sucur){
                return $sucur;
            }else{
                return "No se pudo actualizar";
            }
        }else {
            return ["error"=>"0", "message" => "No se pudo actualizar"];
        }
    }
    //Cambiar el estado de proforma
    // public function Desactivar_Proforma(Request $request)
    // {
    //     if (!$request->id) {
    //         return response()->json(["error" => "No está ingresando ningún prof_id"], 400);
    //     }
    
    //     DB::beginTransaction();
    
    //     try {
    //         // Obtener la proforma
    //         $proform = Proforma::findOrFail($request->id);
            
    //         // Actualizar la proforma
    //         $oldvalue = clone $proform; // Crear copia del valor antiguo
    //         $proform->prof_estado = $request->prof_estado;
    //         $proform->user_editor = $request->id_usua;
    //         $proform->save();
    
    //         // Verificar si el tipo es 'anular'
    //         if ($request->tipo === 'anular') {
    //             // Obtener las ventas asociadas
    //             $ventas = DB::table('f_venta')->where('ven_idproforma', $proform->prof_id)->get();
    
    //             if ($ventas->isEmpty()) {
    //                 throw new \Exception('No se encontraron ventas asociadas');
    //             }
    
    //             foreach ($ventas as $venta) {
    //                 // Verificar estado de la venta
    //                 if ($venta->est_ven_codigo != 2 && $venta->est_ven_codigo != 3) {
    //                     return response()->json([
    //                         "status" => "0", 
    //                         "message" => "El documento " . $venta->ven_codigo . " ya no se encuentra pendiente de despacho. No se puede anular."
    //                     ]);
    //                 }
    
    //                 // Actualizar stock en los productos relacionados
    //                 $detalles = DB::table('f_detalle_venta')->where('ven_codigo', $venta->ven_codigo)->get();
    //                 if ($detalles->isEmpty()) {
    //                     return response()->json(["error" => "0", "message" => "No se encontró detalles del documento: " . $venta->ven_codigo]);
    //                 }
    
    //                 foreach ($detalles as $detalle) {
    //                     $producto = DB::table('1_4_cal_producto')->where('pro_codigo', $detalle->pro_codigo)->first();
    
    //                     if (!$producto) {
    //                         return response()->json(["status" => "0", "message" => "Producto no encontrado"]);
    //                     }
    
    //                     // Ajustar stock según la empresa
    //                     if ($venta->id_empresa == 1) {
    //                         DB::table('1_4_cal_producto')
    //                             ->where('pro_codigo', $detalle->pro_codigo)
    //                             ->update([
    //                                 'pro_deposito' => $producto->pro_deposito + (int)$detalle->det_ven_cantidad,
    //                                 'pro_reservar' => $producto->pro_reservar + (int)$detalle->det_ven_cantidad
    //                             ]);
    //                     } elseif ($venta->id_empresa == 3) {
    //                         DB::table('1_4_cal_producto')
    //                             ->where('pro_codigo', $detalle->pro_codigo)
    //                             ->update([
    //                                 'pro_depositoCalmed' => $producto->pro_depositoCalmed + (int)$detalle->det_ven_cantidad,
    //                                 'pro_reservar' => $producto->pro_reservar + (int)$detalle->det_ven_cantidad
    //                             ]);
    //                     }
    //                 }
    //             }
    //         } else {
    //             // Si no es 'anular', entonces es una actualización normal
    //             try {
    //                 $oldvalue = Proforma::findOrFail($request->id);
    //                 $proform = Proforma::find($request->id);
    
    //                 if (!$proform) {
    //                     return response()->json(['error' => 'El prof_id no existe en la base de datos'], 400);
    //                 }
    
    //                 // Actualizar los campos de la proforma
    //                 $proform->prof_estado = $request->prof_estado;
    //                 $proform->user_editor = $request->id_usua;
    //                 $proform->save();
    
    //                 // Registrar el cambio en el histórico
    //                 $newvalue = Proforma::findOrFail($request->id);
    //                 $this->tr_GuardarEnHistorico($request->id, $request->id_usua, $oldvalue, $newvalue);
    
    //                 DB::commit();
    //                 return response()->json(['message' => 'Proforma actualizada con éxito'], 200);
    //             } catch (\Exception $e) {
    //                 DB::rollback();
    //                 return response()->json(["error" => "0", "message" => "No se pudo actualizar", 'error' => $e->getMessage()], 500);
    //             }
    //         }
    
    //         // Si la operación fue de 'anular', retornar el mensaje respectivo
    //         DB::commit();
    //         return response()->json(['message' => 'Proforma ' . ($request->tipo === 'anular' ? 'anulada' : 'actualizada') . ' con éxito'], 200);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return response()->json(["error" => "0", "message" => "No se pudo procesar la solicitud", 'error' => $e->getMessage()], 500);
    //     }
    // }
    public function Desactivar_Proforma(Request $request)
    {
        DB::beginTransaction();  // Iniciamos la transacción para asegurar la consistencia de los datos
        $proforma_id = $request->id;
        $miarray=json_decode($request->dat);
        try {
            // Obtener la proforma
            $proform = DB::table('f_proforma')->where('id', $proforma_id)->first();
            
            if (!$proform) {
                throw new \Exception('Proforma no encontrada');
            }

            // Verificar que la proforma no esté en estado de "anulada"
            if ($proform->prof_estado == 0) {
                return response()->json(["status" => "0", "message" => "La proforma ya está anulada"]);
            }

            // // Verificar que el estado de la proforma es el adecuado para desactivarla
            // if ($proform->prof_estado != 'pendiente') {
            //     return response()->json(["status" => "0", "message" => "La proforma no se encuentra en estado pendiente"]);
            // }

            // Obtener las ventas asociadas a la proforma
            $ventas = DB::table('f_venta')->where('ven_idproforma', $proform->prof_id)->get();
            
            // if ($ventas->isEmpty()) {
            //     throw new \Exception('No se encontraron ventas asociadas a la proforma');
            // }
            if($ventas){
                foreach ($ventas as $venta) {
                    // Verificar si la venta está en estado pendiente de despacho (estado 2)
                    if ($venta->est_ven_codigo != 2 && $venta->est_ven_codigo != 3) {
                        return response()->json([
                            "status" => "0", 
                            "message" => "La venta " . $venta->ven_codigo . " ya fue despachada y no puede ser anulada."
                        ]);
                    }else{
                        if($venta->est_ven_codigo == 2){
                            // Anular la venta cambiando su estado
                            DB::table('f_venta')
                            ->where('ven_codigo', $venta->ven_codigo)
                            ->where('id_empresa', $venta->id_empresa)->update([
                                'est_ven_codigo' => 3,  // El estado "anulado" se debe definir en tu base de datos
                                'user_anulado' => $request->id_usua,  // El usuario que está realizando la anulación
                                'observacionAnulacion' => 'Se anulo desde la proforma'  // El usuario que está realizando la anulación
                            ]);

                             // Obtener los detalles de la venta
                            $detalles = DB::table('f_detalle_venta')->where('ven_codigo', $venta->ven_codigo)->where('id_empresa', $venta->id_empresa)->get();
                            if ($detalles->isEmpty()) {
                                return response()->json(["error" => "0", "message" => "No se encontraron detalles para la venta: " . $venta->ven_codigo]);
                            }
                
                            // Recorrer los detalles de la venta y restaurar el stock
                            foreach ($detalles as $detalle) {
                                // Obtener el producto asociado al detalle
                                $producto = DB::table('1_4_cal_producto')->where('pro_codigo', $detalle->pro_codigo)->first();
                                if (!$producto) {
                                    return response()->json(["status" => "0", "message" => "Producto no encontrado en la venta"]);
                                }
                
                                // Ajustar el stock según la empresa
                                if ($venta->id_empresa == 1) {
                                    if($venta->idtipodoc == 1){
                                        DB::table('1_4_cal_producto')
                                            ->where('pro_codigo', $detalle->pro_codigo)
                                            ->update([
                                                'pro_stock' => $producto->pro_stock + (int)$detalle->det_ven_cantidad,
                                            ]);
                                    }else{
                                        DB::table('1_4_cal_producto')
                                            ->where('pro_codigo', $detalle->pro_codigo)
                                            ->update([
                                                'pro_deposito' => $producto->pro_deposito + (int)$detalle->det_ven_cantidad,
                                            ]);
                                    }
                                } elseif ($venta->id_empresa == 3) {
                                    if($venta->idtipodoc == 1){
                                        DB::table('1_4_cal_producto')
                                            ->where('pro_codigo', $detalle->pro_codigo)
                                            ->update([
                                                'pro_stockCalmed' => $producto->pro_stockCalmed + (int)$detalle->det_ven_cantidad,
                                            ]);
                                    }else{
                                        DB::table('1_4_cal_producto')
                                            ->where('pro_codigo', $detalle->pro_codigo)
                                            ->update([
                                                'pro_depositoCalmed' => $producto->pro_depositoCalmed + (int)$detalle->det_ven_cantidad,
                                            ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Anular la proforma
            DB::table('f_proforma')->where('id', $proforma_id)->update([
                'prof_estado' => 0,
                'user_editor' => $request->id_usua  // El usuario que está realizando la anulación
            ]);

            foreach($miarray as $key => $item){
                $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$item->pro_codigo'");
                $codi=$query1[0]->stoc;
                $co=(int)$codi+(int)$item->det_prof_cantidad;
                $pro= _14Producto::findOrFail($item->pro_codigo);
                $pro->pro_reservar = $co;
                $pro->save();
            }
            
            DB::commit();  // Confirmamos la transacción

            return response()->json([
                "status" => "1", 
                "message" => "La proforma y las ventas asociadas han sido anuladas con éxito."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();  // Si ocurre un error, deshacemos todos los cambios

            return response()->json([
                "status" => "0", 
                "message" => "Error: " . $e->getMessage(),
                "line" => $e->getLine()
            ]);
        }
    }
    public function AceptarSolicitudProforma(Request $request)
    {
            if ($request->id) {
                try{
                    DB::beginTransaction();
                        $oldvalue= Proforma::findOrFail($request->id);
                        $proform= Proforma::find($request->id);

                        if (!$proform){
                            return "El prof_id no existe en la base de datos";
                        }
                        $proform->prof_estado = $request->prof_estado;
                        $proform->user_editor = $request->id_usua;
                        $proform->save();
                        if($proform){
                            $newvalue = Proforma::findOrFail($request->id);
                            $this->tr_GuardarEnHistorico($request->id,$request->id_usua,$oldvalue,$newvalue);
                        }
                    DB::commit();
                    return response()->json(['message' => 'Proforma actualizado con éxito'], 200);
                }catch(\Exception $e){
                    DB::rollback();
                    return response()->json(["error"=>"0", "message" => "No se pudo actualizar", 'error' => $e->getMessage()], 500);
                }
             } else {
                return "No está ingresando ningún prof_id";
            }
    }

    public function AprobarProforma(Request $request)
    {
        if (!$request->id) {
            return response()->json(["error" => "No está ingresando ningún prof_id"], 400);
        }
    
        DB::beginTransaction();
    
        try {
            // Obtener la proforma
            $proform = Proforma::findOrFail($request->id);
            
            // Actualizar la proforma
            $oldvalue = clone $proform; // Crear copia del valor antiguo
            $proform->prof_estado = $request->prof_estado;
            $proform->user_editor = $request->id_usua;
            $proform->save();
            try {
                $oldvalue = Proforma::findOrFail($request->id);
                $proform = Proforma::find($request->id);

                if (!$proform) {
                    return response()->json(['error' => 'El prof_id no existe en la base de datos'], 400);
                }

                // Actualizar los campos de la proforma
                $proform->prof_estado = $request->prof_estado;
                $proform->user_editor = $request->id_usua;
                $proform->save();

                // Registrar el cambio en el histórico
                $newvalue = Proforma::findOrFail($request->id);
                $this->tr_GuardarEnHistorico($request->id, $request->id_usua, $oldvalue, $newvalue);

                DB::commit();
                return response()->json(['message' => 'Proforma actualizada con éxito'], 200);
            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(["error" => "0", "message" => "No se pudo actualizar", 'error' => $e->getMessage()], 500);
            }
    
            // Si la operación fue de 'anular', retornar el mensaje respectivo
            DB::commit();
            return response()->json(['message' => 'Proforma actualizada con éxito'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["error" => "0", "message" => "No se pudo procesar la solicitud", 'error' => $e->getMessage()], 500);
        }
    }
    public function DesactivarProforma(Request $request)
    {
        if ($request->id) {
            try{
                DB::beginTransaction();
                    $oldvalue= Proforma::findOrFail($request->id);
                    $proform= Proforma::find($request->id);

                    if (!$proform){
                        return "El prof_id no existe en la base de datos";
                    }
                    $proform->prof_estado = $request->prof_estado;
                    $proform->user_editor = $request->id_usua;
                    $proform->save();
                    if($proform){
                        $newvalue = Proforma::findOrFail($request->id);
                        $this->tr_GuardarEnHistorico($request->id,$request->id_usua,$oldvalue,$newvalue);
                    }
                DB::commit();
                return response()->json(['message' => 'Proforma actualizado con éxito'], 200);
            }catch(\Exception $e){
                DB::rollback();
                return response()->json(["error"=>"0", "message" => "No se pudo actualizar", 'error' => $e->getMessage()], 500);
            }
        }
        else {
            return "No está ingresando ningún id";
        }
    }
//eliminacion de datos del detalle de la proforma
    public function Eliminar_DetaProforma(Request $request)
    {
        if ($request->det_prof_id) {
            try{
                DB::beginTransaction();
                    $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$request->pro_codigo'");
                    $codi=$query1[0]->stoc;
                    $co=(int)$codi+(int)$request->det_prof_cantidad;
                    $pro= _14Producto::findOrFail($request->pro_codigo);
                    $pro->pro_reservar = $co;
                    $pro->save();
                    $proforma = DetalleProforma::find($request->det_prof_id);

                    if (!$proforma) {
                        return "El det_prof_id no existe en la base de datos";
                    }
                    $proforma->delete();
                    // $ultimoId  = DetalleProforma::max('det_prof_id') + 1;
                    // DB::statement('ALTER TABLE f_detalle_proforma AUTO_INCREMENT = ' . $ultimoId);

                DB::commit();
                return response()->json(['message' => 'Detalle de la proforma eliminado con éxito'], 200);
            }catch(\Exception $e){
                DB::rollback();
                return response()->json(["error"=>"0", "message" => "No se pudo Eliminar", 'error' => $e->getMessage()], 500);
            }
        } else {
            return response()->json(["error"=>"0", "message" => "No está ingresando ningún det_prof_id", 'error' => $e->getMessage()], 500);
        }
    }
    //eliminar proforma y detalles
    public function Eliminar_Proforma(Request $request)
    {
        if ($request->id) {
            try {
                set_time_limit(6000000);
                ini_set('max_execution_time', 6000000);
                $miarray = json_decode($request->dat);
                DB::beginTransaction();
                $profor = DetalleProforma::where('prof_id', $request->id)->get();

                if ($profor->isEmpty()) {
                    return ["error" => "0", "message" =>"El prof_id no existe en la base de datos"];
                } else {
                    DetalleProforma::where('prof_id', $request->id)->delete();
                }

                $profor = DetalleProforma::where('prof_id', $request->id)->get();
                if ($profor->isEmpty()) {
                    $profort = Proforma::find($request->id);
                    if (!$profort) {
                        return ["error" => "0", "message" =>"El prof_id no existe en la base de datos"];
                    } else {
                        $profort->delete();
                    }
                }

                foreach ($miarray as $key => $item) {
                    $query1 = DB::SELECT("SELECT pro_reservar as stoc FROM 1_4_cal_producto WHERE pro_codigo='$item->pro_codigo'");
                    $codi = $query1[0]->stoc;
                    $co = (int)$codi + (int)$item->det_prof_cantidad;
                    $pro = _14Producto::findOrFail($item->pro_codigo);
                    $pro->pro_reservar = $co;
                    $pro->save();
                }
                DB::commit();
                return response()->json(['message' => 'Proforma eliminada con éxito'], 200);
            } catch (\Exception $e) {
                DB::rollback(); // Mover esto antes del return
                return ["error" => "0", "message" => "No se pudo actualizar", "error" => $e];
            }
        }else{
            return ["error" => "0", "message" =>"No hay el id de la proforma"];
        }
    }
    public function InfoAgrupadoPedido($ca_codigo_agrupado,$id_periodo){
        $query = $this->tr_pedidosXDespacho($ca_codigo_agrupado,$id_periodo);
        return $query;
    }
    public function InfoClienteAgrupado($ca_codigo_agrupado, $id_periodo){
        $query = DB::SELECT("SELECT DISTINCT usu.*, CONCAT(usu.nombres,' ', usu.apellidos) nombres
        FROM f_proforma f
        INNER JOIN f_venta fv ON fv.ven_idproforma = f.prof_id
        LEFT JOIN usuario usu ON fv.ven_cliente  = usu.idusuario
        WHERE f.idPuntoventa = '$ca_codigo_agrupado'
        AND fv.periodo_id = '$id_periodo'");

        return $query;
    }
    
    public function CambiarEmpresaPedido(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',  // Validar que el pedido exista
            'empresa' => 'required',  // Validar que la empresa exista
        ]);
    
        $id_proforma = $request->id;
        $id_empresa = $request->empresa;
    
        // Iniciar la transacción
        DB::beginTransaction();
    
        try {
            // Buscar el pedido
            $pedido = DB::table('f_proforma')->where('id', $id_proforma)->first();
    
            if (!$pedido) {
                // Si el pedido no existe, lanzar una excepción para que se haga rollback
                return response()->json([
                    'status' => 404,
                    'message' => 'Pedido no encontrado',
                ]);
            }
    
            // Buscar la empresa
            $empresa = DB::table('empresas')->where('id', $id_empresa)->first();
    
            if (!$empresa) {
                // Si la empresa no existe, lanzar una excepción para que se haga rollback
                return response()->json([
                    'status' => 404,
                    'message' => 'Empresa no encontrada',
                ]);
            }
    
            // Cambiar la empresa del pedido
            $updatedRows = DB::table('f_proforma')
                ->where('id', $id_proforma)
                ->update(['emp_id' => $empresa->id]);
    
            // Verificar si la actualización se realizó con éxito
            if ($updatedRows === 0) {
                // Si no se actualizó ningún registro, lanzar una excepción
                throw new \Exception('No se pudo actualizar el pedido. Hubo un error en la actualización.');
            }

            $pedidoActualizado = DB::SELECT("SELECT fpr.*,  em.nombre, em.img_base64,
            us.nombres as username, us.apellidos as lastname, COUNT(dpr.pro_codigo) AS item, SUM(dpr.det_prof_cantidad) AS libros,
            CONCAT(COALESCE(usa.nombres, ''), ' ', COALESCE(usa.apellidos, '')) AS cliente,
            i.nombreInstitucion,i.ruc as rucPuntoVenta
            FROM f_proforma fpr
            LEFT JOIN usuario us ON fpr.user_editor = us.idusuario
            LEFT JOIN empresas em ON fpr.emp_id =em.id
            INNER JOIN f_detalle_proforma dpr ON dpr.prof_id=fpr.id
            left join usuario usa on fpr.ven_cliente = usa.idusuario
            LEFT JOIN institucion i ON fpr.id_ins_depacho = i.idInstitucion
            WHERE fpr.id= '$id_proforma'
            GROUP BY fpr.id, fpr.prof_id,fpr.prof_observacion,em.nombre, em.img_base64
            order by fpr.created_at desc");

            // Si todo es correcto, hacer commit de la transacción
            DB::commit();
    
            return response()->json([
                'status' => 200,
                'message' => 'Pedido cambiado con éxito',
                'data' => $pedidoActualizado
            ]);
    
        } catch (\Exception $e) {
            // Si ocurre algún error, hacer rollback
            DB::rollback();
    
            return response()->json([
                'status' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Método específico para guardar proformas desde ProformaModal
     * Guarda el contrato en f_proforma y en cada f_detalle_proforma
     */
    public function Proforma_Guardar_Desde_Modal(Request $request)
    {
        try {

            
            $miarray = json_decode($request->data_detalle);
            
            // ======================================================
            // VALIDACIÓN DE STOCK EN TIEMPO REAL (ANTES DE GUARDAR)
            // ======================================================
            $productosInvalidos = [];
            
            foreach ($miarray as $item) {
                $cantidad = isset($item->det_prof_cantidad) ? $item->det_prof_cantidad : 
                           (isset($item->cantidad) ? $item->cantidad : 0);
                $pro_codigo = isset($item->pro_codigo) ? $item->pro_codigo : 
                             (isset($item->codigo_liquidacion) ? $item->codigo_liquidacion : null);
                
                if (!$pro_codigo || (int)$cantidad == 0) {
                    continue;
                }
                
                // Consultar stock ACTUAL en tiempo real
                $stockQuery = DB::SELECT("SELECT pro_codigo, pro_nombre, pro_reservar FROM 1_4_cal_producto WHERE pro_codigo = ?", [$pro_codigo]);
                
                if (!empty($stockQuery)) {
                    $stockActual = (int)$stockQuery[0]->pro_reservar;
                    $cantidadRequerida = (int)$cantidad;
                    
                    // Validar que haya suficiente stock
                    if ($stockActual < $cantidadRequerida) {
                        $productosInvalidos[] = [
                            'pro_codigo' => $pro_codigo,
                            'pro_nombre' => $stockQuery[0]->pro_nombre,
                            'stock_actual' => $stockActual,
                            'cantidad_requerida' => $cantidadRequerida,
                            'faltante' => $cantidadRequerida - $stockActual
                        ];
                    }
                }
            }
            
            // Si hay productos sin stock suficiente, retornar error
            if (!empty($productosInvalidos)) {
                return response()->json([
                    'status' => 400,
                    'error' => 'STOCK_INSUFICIENTE',
                    'message' => 'No hay stock suficiente para algunos productos',
                    'productos_invalidos' => $productosInvalidos
                ], 400);
            }
            
            // Si todo está OK, proceder con la transacción
            DB::beginTransaction();
    
            $proforma = new Proforma;
    
            // Generar código de proforma si el usuario tiene permisos
            if ($request->id_group == 1 || $request->id_group == 22 || $request->id_group == 23) {
                $id_empresa = $request->emp_id;
                $iniciales = $request->iniciales;
                $getNumeroDocumento = $this->getNumeroDocumento($id_empresa);
                $ven_codigo = "PP-" . $iniciales . "-" . $getNumeroDocumento;
                $proforma->prof_id = $ven_codigo;
            }
    
            // Asignación de valores al modelo Proforma
            $proforma->usu_codigo = $request->usu_codigo;
            $proforma->pedido_id = $request->pedido_id;
            $proforma->emp_id = $request->emp_id;
            $proforma->idPuntoventa = $request->idPuntoventa;
            $proforma->prof_observacion = $request->prof_observacion;
            $proforma->prof_observacion_libreria = $request->prof_observacion_libreria ?? '';
            $proforma->prof_com = $request->prof_com;
            $proforma->prof_descuento = $request->prof_descuento;
            $proforma->pro_des_por = $request->pro_des_por;
            $proforma->prof_iva = $request->prof_iva;
            $proforma->prof_iva_por = $request->prof_iva_por;
            $proforma->prof_total = $request->prof_total;
            $proforma->prof_estado = $request->prof_estado;
            $proforma->prof_tipo_proforma = $request->prof_tipo_proforma;
            $proforma->created_at = $request->created_at;
    
            // Actualizar secuencia de documento
            if ($request->emp_id == 1) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            } else if ($request->emp_id == 3) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            }
    
            if (!empty($query1)) {
                $id = $query1[0]->id;
                $codi = $query1[0]->cod;
                $co = (int)$codi + 1;
                $tipo_doc = f_tipo_documento::findOrFail($id);
                if ($request->emp_id == 1) {
                    $tipo_doc->tdo_secuencial_Prolipa = $co;
                } else if ($request->emp_id == 3) {
                    $tipo_doc->tdo_secuencial_calmed = $co;
                }
                $tipo_doc->save();
            }
    
            $proforma->idtipodoc = 5;
            $proforma->id_ins_depacho = $request->id_ins_depacho;
            $proforma->ven_cliente = $request->ven_cliente;
            $proforma->clientesidPerseo = $request->clientesidPerseo;
            
            // GUARDAR EL PERÍODO - Campo REQUERIDO
            if (!$request->id_periodo) {
                return response()->json([
                    "status" => 400,
                    "error" => "El período es requerido",
                    "message" => "No se puede guardar la proforma sin un período asignado"
                ], 400);
            }
            $proforma->id_periodo = $request->id_periodo;
            
            // Obtener cédula del cliente si está definido
            if ($request->ven_cliente) {
                $user = User::where('idusuario', $request->ven_cliente)->first();
                if ($user) {
                    $getCedula = $user->cedula;
                    $proforma->ruc_cliente = $getCedula;
                }
            }
            
            // GUARDAR EL CONTRATO en la proforma
            if ($request->contrato) {
                $proforma->contrato = $request->contrato;
            }
    
            // Guardar la proforma
            $proforma->save();
            $ultimo_id = $proforma->id;
    
            // Guardar los detalles de la proforma
            foreach ($miarray as $key => $item) {
                $cantidad = isset($item->det_prof_cantidad) ? $item->det_prof_cantidad : 
                           (isset($item->cantidad) ? $item->cantidad : 0);
                $pro_codigo = isset($item->pro_codigo) ? $item->pro_codigo : 
                             (isset($item->codigo_liquidacion) ? $item->codigo_liquidacion : null);
                $precio = isset($item->det_prof_valor_u) ? $item->det_prof_valor_u : 
                         (isset($item->precio) ? $item->precio : 0);
                
                if (!$pro_codigo) {
                    continue; // Saltar si no hay código de producto
                }
                
                if ((int)$cantidad != 0) {
                    // Actualizar stock reservado
                    $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$pro_codigo'");
                    if (!empty($query1)) {
                        $codi = $query1[0]->stoc;
                        $co = (int)$codi - (int)$cantidad;
                        $pro = _14Producto::findOrFail($pro_codigo);
                        $pro->pro_reservar = $co;
                        $pro->save();
                    }
    
                    // Crear y guardar el detalle de la proforma
                    $DetalleProforma = new DetalleProforma;
                    $DetalleProforma->prof_id = $ultimo_id;
                    $DetalleProforma->pro_codigo = $pro_codigo;
                    $DetalleProforma->det_prof_cantidad = (int)$cantidad;
                    $DetalleProforma->det_prof_valor_u = $precio;
                    
                    // GUARDAR EL CONTRATO también en el detalle
                    if ($request->contrato) {
                        $DetalleProforma->contrato = $request->contrato;
                    }
                    
                    $DetalleProforma->save();
                }
            }
    
            // Confirmar la transacción
            DB::commit();
    
            return response()->json([
                'status' => 200,
                'message' => 'Proforma guardada exitosamente',
                'proforma_id' => $ultimo_id,
                'prof_id' => $proforma->prof_id
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "No se pudo guardar la proforma",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Guardar Proforma desde Modal - VERSIÓN PERSEO
     * Este método es específico para proformas que usan el sistema de reservas de Perseo
     * Diferencias clave con el método normal:
     * - NO descuenta de pro_reservar
     * - SÍ incrementa reserva_perseo
     * - Guarda proforma_perseo = 1
     * - Guarda stock_perseo y contrato en cada detalle
     */
    public function Proforma_Perseo_Guardar_Desde_Modal(Request $request)
    {
        try {
            $miarray = json_decode($request->data_detalle);
            
            // ======================================================
            // NOTA: Para Perseo NO validamos stock tradicional (pro_reservar)
            // Solo consultamos el stock de Perseo que ya viene en los datos
            // ======================================================
            
            DB::beginTransaction();
    
            $proforma = new Proforma;
    
            // Generar código de proforma si el usuario tiene permisos
            if ($request->id_group == 1 || $request->id_group == 22 || $request->id_group == 23) {
                $id_empresa = $request->emp_id;
                $iniciales = $request->iniciales;
                $getNumeroDocumento = $this->getNumeroDocumento($id_empresa);
                $ven_codigo = "PP-" . $iniciales . "-" . $getNumeroDocumento;
                $proforma->prof_id = $ven_codigo;
            }
    
            // Asignación de valores al modelo Proforma
            $proforma->usu_codigo = $request->usu_codigo;
            $proforma->pedido_id = $request->pedido_id;
            $proforma->emp_id = $request->emp_id;
            $proforma->idPuntoventa = $request->idPuntoventa;
            $proforma->prof_observacion = $request->prof_observacion;
            $proforma->prof_observacion_libreria = $request->prof_observacion_libreria ?? '';
            $proforma->prof_com = $request->prof_com;
            $proforma->prof_descuento = $request->prof_descuento;
            $proforma->pro_des_por = $request->pro_des_por;
            $proforma->prof_iva = $request->prof_iva;
            $proforma->prof_iva_por = $request->prof_iva_por;
            $proforma->prof_total = $request->prof_total;
            $proforma->prof_estado = $request->prof_estado;
            $proforma->prof_tipo_proforma = $request->prof_tipo_proforma;
            $proforma->created_at = $request->created_at;
    
            // Actualizar secuencia de documento
            if ($request->emp_id == 1) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            } else if ($request->emp_id == 3) {
                $query1 = DB::SELECT("SELECT tdo_id as id, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-PROFORMA'");
            }
    
            if (!empty($query1)) {
                $id = $query1[0]->id;
                $codi = $query1[0]->cod;
                $co = (int)$codi + 1;
                $tipo_doc = f_tipo_documento::findOrFail($id);
                if ($request->emp_id == 1) {
                    $tipo_doc->tdo_secuencial_Prolipa = $co;
                } else if ($request->emp_id == 3) {
                    $tipo_doc->tdo_secuencial_calmed = $co;
                }
                $tipo_doc->save();
            }
    
            $proforma->idtipodoc = 5;
            $proforma->id_ins_depacho = $request->id_ins_depacho;
            $proforma->ven_cliente = $request->ven_cliente;
            $proforma->clientesidPerseo = $request->clientesidPerseo;
            
            // GUARDAR EL PERÍODO - Campo REQUERIDO
            if (!$request->id_periodo) {
                return response()->json([
                    "status" => 400,
                    "error" => "El período es requerido",
                    "message" => "No se puede guardar la proforma sin un período asignado"
                ], 400);
            }
            $proforma->id_periodo = $request->id_periodo;
            
            // Obtener cédula del cliente si está definido
            if ($request->ven_cliente) {
                $user = User::where('idusuario', $request->ven_cliente)->first();
                if ($user) {
                    $getCedula = $user->cedula;
                    $proforma->ruc_cliente = $getCedula;
                }
            }
            
            // ======================================================
            // CAMPO ESPECÍFICO DE PERSEO: proforma_perseo = 1
            // ======================================================
            $proforma->proforma_perseo = 1;
            
            // GUARDAR EL CONTRATO en la proforma
            if ($request->contrato) {
                $proforma->contrato = $request->contrato;
            }
    
            // Guardar la proforma
            $proforma->save();
            $ultimo_id = $proforma->id;
    
            // Guardar los detalles de la proforma
            foreach ($miarray as $key => $item) {
                $cantidad = isset($item->det_prof_cantidad) ? $item->det_prof_cantidad : 
                           (isset($item->cantidad) ? $item->cantidad : 0);
                $pro_codigo = isset($item->pro_codigo) ? $item->pro_codigo : 
                             (isset($item->codigo_liquidacion) ? $item->codigo_liquidacion : null);
                $precio = isset($item->det_prof_valor_u) ? $item->det_prof_valor_u : 
                         (isset($item->precio) ? $item->precio : 0);
                
                // Obtener stock_perseo del item (viene del frontend)
                $stock_perseo = isset($item->stock_perseo) ? $item->stock_perseo : null;
                
                // Obtener contrato del item (viene del frontend)
                $contrato_detalle = isset($item->contrato) ? $item->contrato : 
                                   ($request->contrato ? $request->contrato : null);
                
                if (!$pro_codigo) {
                    continue; // Saltar si no hay código de producto
                }
                
                if ((int)$cantidad != 0) {
                    // ======================================================
                    // LÓGICA ESPECÍFICA DE PERSEO: 
                    // INCREMENTAR reserva_perseo en lugar de decrementar pro_reservar
                    // ======================================================
                    DB::statement(
                        'UPDATE 1_4_cal_producto 
                         SET reserva_perseo = COALESCE(reserva_perseo, 0) + ? 
                         WHERE pro_codigo = ?',
                        [(int)$cantidad, $pro_codigo]
                    );
    
                    // Crear y guardar el detalle de la proforma
                    $DetalleProforma = new DetalleProforma;
                    $DetalleProforma->prof_id = $ultimo_id;
                    $DetalleProforma->pro_codigo = $pro_codigo;
                    $DetalleProforma->det_prof_cantidad = (int)$cantidad;
                    $DetalleProforma->det_prof_valor_u = $precio;
                    
                    // ======================================================
                    // CAMPOS ESPECÍFICOS DE PERSEO EN EL DETALLE
                    // ======================================================
                    // Guardar el stock de Perseo consultado al momento de la proforma
                    if ($stock_perseo !== null) {
                        $DetalleProforma->stock_perseo = (int)$stock_perseo;
                    }
                    
                    // Guardar el contrato en el detalle
                    if ($contrato_detalle) {
                        $DetalleProforma->contrato = $contrato_detalle;
                    }
                    
                    $DetalleProforma->save();
                }
            }
    
            // Confirmar la transacción
            DB::commit();
    
            return response()->json([
                'status' => 200,
                'message' => 'Proforma Perseo guardada exitosamente',
                'proforma_id' => $ultimo_id,
                'prof_id' => $proforma->prof_id,
                'tipo' => 'perseo'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "No se pudo guardar la proforma Perseo",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Actualizar Proforma desde Modal - VERSIÓN PERSEO
     * Este método es específico para actualizar proformas que usan el sistema de reservas de Perseo
     * Diferencias clave con el método normal:
     * - NO modifica pro_reservar
     * - SÍ ajusta reserva_perseo según los cambios
     * - Maneja stock_perseo en los detalles
     */
    public function Proforma_Perseo_Actualizar_Desde_Modal(Request $request)
    {
        try {
            $miarray = json_decode($request->data_detalle);
            $proforma_id = $request->proforma_id;
            
            if (!$proforma_id) {
                return response()->json([
                    "status" => 400,
                    "message" => "El ID de la proforma es requerido"
                ], 400);
            }
            
            // Obtener los detalles antiguos ANTES de modificar
            $detallesAntiguos = DetalleProforma::where('prof_id', $proforma_id)->get();
            
            // ======================================================
            // NOTA: Para Perseo NO validamos stock tradicional (pro_reservar)
            // El stock de Perseo ya viene consultado en los datos
            // ======================================================
            
            DB::beginTransaction();
    
            // Buscar la proforma existente
            $proforma = Proforma::findOrFail($proforma_id);
    
            // Actualizar datos de la proforma (NO se modifica prof_id ni created_at)
            $proforma->prof_observacion = $request->prof_observacion;
            $proforma->prof_observacion_libreria = $request->prof_observacion_libreria ?? '';
            $proforma->prof_com = $request->prof_com;
            $proforma->prof_descuento = $request->prof_descuento;
            $proforma->pro_des_por = $request->pro_des_por;
            $proforma->prof_iva = $request->prof_iva;
            $proforma->prof_iva_por = $request->prof_iva_por;
            $proforma->prof_total = $request->prof_total;
            $proforma->prof_estado = $request->prof_estado;
            
            // Actualizar lugar de despacho y cliente (si vienen en el request)
            if ($request->has('id_ins_depacho')) {
                $proforma->id_ins_depacho = $request->id_ins_depacho;
            }
            if ($request->has('ven_cliente')) {
                $proforma->ven_cliente = $request->ven_cliente;
            }
            if ($request->has('ruc_cliente')) {
                $proforma->ruc_cliente = $request->ruc_cliente;
            }
            if ($request->has('clientesidPerseo')) {
                $proforma->clientesidPerseo = $request->clientesidPerseo;
            }
            if ($request->has('emp_id')) {
                $proforma->emp_id = $request->emp_id;
            }
            
            // Actualizar período si viene en el request (VALIDAR QUE NO SEA NULL)
            if ($request->has('id_periodo') && $request->id_periodo) {
                $proforma->id_periodo = $request->id_periodo;
            }
            
            // Guardar cambios en la proforma
            $proforma->save();
            
            // ELIMINAR todos los detalles antiguos
            DetalleProforma::where('prof_id', $proforma_id)->delete();
            
            // PROCESAR NUEVOS DETALLES Y AJUSTAR RESERVA_PERSEO
            foreach ($miarray as $key => $item) {
                $cantidad_nueva = isset($item->det_prof_cantidad) ? $item->det_prof_cantidad : 
                                 (isset($item->cantidad) ? $item->cantidad : 0);
                $pro_codigo = isset($item->pro_codigo) ? $item->pro_codigo : 
                             (isset($item->codigo_liquidacion) ? $item->codigo_liquidacion : null);
                $precio = isset($item->det_prof_valor_u) ? $item->det_prof_valor_u : 
                         (isset($item->precio) ? $item->precio : 0);
                
                // Obtener stock_perseo del item (viene del frontend)
                $stock_perseo = isset($item->stock_perseo) ? $item->stock_perseo : null;
                
                // Obtener contrato del item (viene del frontend)
                $contrato_detalle = isset($item->contrato) ? $item->contrato : 
                                   ($request->contrato ? $request->contrato : null);
                
                if (!$pro_codigo || (int)$cantidad_nueva == 0) {
                    continue;
                }
                
                // Buscar si este libro existía en la proforma anterior
                $detalleAntiguo = $detallesAntiguos->firstWhere('pro_codigo', $pro_codigo);
                
                if ($detalleAntiguo) {
                    // EL LIBRO YA EXISTÍA - Comparar cantidades
                    $cantidad_antigua = (int)$detalleAntiguo->det_prof_cantidad;
                    $cantidad_nueva_int = (int)$cantidad_nueva;
                    $diferencia = $cantidad_nueva_int - $cantidad_antigua;
                    
                    if ($diferencia != 0) {
                        // ======================================================
                        // LÓGICA ESPECÍFICA DE PERSEO: 
                        // AJUSTAR reserva_perseo según la diferencia
                        // ======================================================
                        if ($diferencia > 0) {
                            // Aumentó la cantidad → INCREMENTAR reserva_perseo
                            DB::statement(
                                'UPDATE 1_4_cal_producto 
                                 SET reserva_perseo = COALESCE(reserva_perseo, 0) + ? 
                                 WHERE pro_codigo = ?',
                                [$diferencia, $pro_codigo]
                            );
                        } else {
                            // Disminuyó la cantidad → DECREMENTAR reserva_perseo
                            $diferencia_positiva = abs($diferencia);
                            DB::statement(
                                'UPDATE 1_4_cal_producto 
                                 SET reserva_perseo = GREATEST(COALESCE(reserva_perseo, 0) - ?, 0) 
                                 WHERE pro_codigo = ?',
                                [$diferencia_positiva, $pro_codigo]
                            );
                        }
                    }
                } else {
                    // ES UN LIBRO NUEVO - Incrementar reserva_perseo
                    DB::statement(
                        'UPDATE 1_4_cal_producto 
                         SET reserva_perseo = COALESCE(reserva_perseo, 0) + ? 
                         WHERE pro_codigo = ?',
                        [(int)$cantidad_nueva, $pro_codigo]
                    );
                }
                
                // Crear el nuevo detalle
                $DetalleProforma = new DetalleProforma;
                $DetalleProforma->prof_id = $proforma_id;
                $DetalleProforma->pro_codigo = $pro_codigo;
                $DetalleProforma->det_prof_cantidad = (int)$cantidad_nueva;
                $DetalleProforma->det_prof_valor_u = $precio;
                
                // Guardar campos específicos de Perseo
                if ($stock_perseo !== null) {
                    $DetalleProforma->stock_perseo = (int)$stock_perseo;
                }
                
                if ($contrato_detalle) {
                    $DetalleProforma->contrato = $contrato_detalle;
                }
                
                $DetalleProforma->save();
            }
            
            // VERIFICAR LIBROS ELIMINADOS
            foreach ($detallesAntiguos as $detalleAntiguo) {
                $libroEnNuevaLista = collect($miarray)->firstWhere('pro_codigo', $detalleAntiguo->pro_codigo);
                
                if (!$libroEnNuevaLista) {
                    // Este libro fue ELIMINADO - Decrementar reserva_perseo
                    DB::statement(
                        'UPDATE 1_4_cal_producto 
                         SET reserva_perseo = GREATEST(COALESCE(reserva_perseo, 0) - ?, 0) 
                         WHERE pro_codigo = ?',
                        [(int)$detalleAntiguo->det_prof_cantidad, $detalleAntiguo->pro_codigo]
                    );
                }
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 200,
                'message' => 'Proforma Perseo actualizada exitosamente',
                'proforma_id' => $proforma_id,
                'prof_id' => $proforma->prof_id,
                'tipo' => 'perseo'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "No se pudo actualizar la proforma Perseo",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Obtener libros proformados por contrato
     * Retorna la cantidad, descuento y totales de cada libro en las proformas del contrato
     */
    public function get_libros_proformados_por_contrato(Request $request)
    {
        try {
            $contrato = $request->contrato;
            $periodo = $request->periodo;
            
            if (!$contrato) {
                return response()->json([
                    "status" => 400,
                    "message" => "El contrato es requerido"
                ], 400);
            }
            
            // Consulta para obtener los libros proformados agrupados por producto Y descuento
            $librosProformados = DB::SELECT("
                SELECT 
                    dp.pro_codigo,
                    MAX(p.pro_nombre) as nombre_libro,
                    fp.pro_des_por as descuento_proforma,
                    SUM(dp.det_prof_cantidad) as cantidad_proformada,
                    MAX(dp.det_prof_valor_u) as precio_unitario,
                    SUM(dp.det_prof_cantidad * dp.det_prof_valor_u) as subtotal_proformado,
                    SUM(dp.det_prof_cantidad * dp.det_prof_valor_u * (1 - COALESCE(fp.pro_des_por, 0) / 100)) as total_proformado
                FROM f_detalle_proforma dp
                INNER JOIN f_proforma fp ON dp.prof_id = fp.id
                INNER JOIN 1_4_cal_producto p ON dp.pro_codigo = p.pro_codigo
                WHERE fp.contrato = ?
                AND fp.prof_estado <> 0
                GROUP BY dp.pro_codigo, fp.pro_des_por
                ORDER BY MAX(p.pro_nombre), fp.pro_des_por
            ", [$contrato]);
            
            return response()->json([
                "status" => 200,
                "data" => $librosProformados
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "Error al obtener libros proformados",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Obtener proformas por contrato
     * Retorna todas las proformas asociadas a un contrato específico
     */
    public function get_proformas_por_contrato(Request $request)
    {
        try {
            $contrato = $request->contrato;
            
            if (!$contrato) {
                return response()->json([
                    "status" => 400,
                    "message" => "El contrato es requerido"
                ], 400);
            }
            
            $proformas = DB::SELECT("
                SELECT 
                    fpr.id,
                    fpr.prof_id,
                    fpr.contrato,
                    fpr.prof_estado,
                    fpr.prof_total,
                    fpr.prof_descuento,
                    fpr.prof_iva,
                    fpr.prof_observacion,
                    fpr.created_at,
                    fpr.updated_at,
                    fpr.id_periodo,
                    fpr.proforma_perseo,
                    em.id as empresa_id,
                    em.nombre as empresa_nombre,
                    em.img_base64 as empresa_logo,
                    CONCAT(COALESCE(uc.nombres, ''), ' ', COALESCE(uc.apellidos, '')) AS cliente_nombre,
                    uc.cedula as cliente_ruc,
                    uc.email as cliente_email,
                    i.idInstitucion as despacho_id,
                    i.nombreInstitucion as despacho_nombre,
                    i.ruc as despacho_ruc,
                    i.direccionInstitucion as despacho_direccion,
                    i.telefonoInstitucion as despacho_telefono,
                    COUNT(dp.pro_codigo) as total_items,
                    SUM(dp.det_prof_cantidad) as total_cantidad,
                    CONCAT(COALESCE(usr.nombres, ''), ' ', COALESCE(usr.apellidos, '')) AS creado_por,
                    usr.cedula as creado_por_cedula
                FROM f_proforma fpr
                LEFT JOIN empresas em ON fpr.emp_id = em.id
                LEFT JOIN usuario uc ON fpr.ven_cliente = uc.idusuario
                LEFT JOIN institucion i ON fpr.id_ins_depacho = i.idInstitucion
                LEFT JOIN usuario usr ON fpr.usu_codigo = usr.idusuario
                LEFT JOIN f_detalle_proforma dp ON dp.prof_id = fpr.id
                WHERE fpr.contrato = ?
                GROUP BY fpr.id, fpr.prof_id, fpr.contrato, fpr.prof_estado, 
                         fpr.prof_total, fpr.prof_descuento, fpr.prof_iva, fpr.prof_observacion,
                         fpr.created_at, fpr.updated_at, fpr.id_periodo, fpr.proforma_perseo,
                         em.id, em.nombre, em.img_base64,
                         uc.nombres, uc.apellidos, uc.cedula, uc.email,
                         i.idInstitucion, i.nombreInstitucion, i.ruc, i.direccionInstitucion, i.telefonoInstitucion,
                         usr.nombres, usr.apellidos, usr.cedula
                ORDER BY fpr.created_at DESC
            ", [$contrato]);
            
            return response()->json([
                "status" => 200,
                "data" => $proformas
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "Error al obtener proformas",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Obtener datos completos de una proforma para edición
     * Retorna toda la información de la proforma incluyendo detalles y datos de cupo
     */
    public function get_proforma_para_editar($id, Request $request)
    {
        try {
            $proforma_id = $id;
            
            // Obtener datos completos de la proforma incluyendo información de cupo
            $proforma = DB::SELECT("
                SELECT 
                    fpr.id,
                    fpr.prof_id,
                    fpr.usu_codigo,
                    fpr.pedido_id,
                    fpr.emp_id,
                    fpr.idPuntoventa,
                    fpr.prof_observacion,
                    fpr.prof_observacion_libreria,
                    fpr.prof_com,
                    fpr.prof_descuento,
                    fpr.pro_des_por,
                    fpr.prof_iva,
                    fpr.prof_iva_por,
                    fpr.prof_total,
                    fpr.prof_estado,
                    fpr.prof_tipo_proforma,
                    fpr.idtipodoc,
                    fpr.id_ins_depacho,
                    fpr.ven_cliente,
                    fpr.clientesidPerseo,
                    fpr.ruc_cliente,
                    fpr.contrato,
                    fpr.created_at,
                    em.id as empresa_id,
                    em.nombre as empresa_nombre,
                    i.idInstitucion as despacho_id,
                    i.nombreInstitucion as despacho_nombre,
                    i.ruc as despacho_ruc,
                    i.direccionInstitucion as despacho_direccion,
                    c.nombre as despacho_ciudad,
                    CONCAT(COALESCE(uc.nombres, ''), ' ', COALESCE(uc.apellidos, '')) AS cliente_nombre,
                    uc.cedula as cliente_cedula,
                    uc.email as cliente_email,
                    uc.idusuario as cliente_idusuario,
                    ffp.ffp_id,
                    ffp.ffp_credito,
                    ffp.ffp_cupo,
                    ffp.ffp_descuento,
                    ffp.if_distribuidor,
                    p.tipo_venta
                FROM f_proforma fpr
                LEFT JOIN empresas em ON fpr.emp_id = em.id
                LEFT JOIN usuario uc ON fpr.ven_cliente = uc.idusuario
                LEFT JOIN institucion i ON fpr.id_ins_depacho = i.idInstitucion
                LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
                LEFT JOIN f_formulario_proforma ffp ON fpr.id_ins_depacho = ffp.idInstitucion
                LEFT JOIN pedidos p ON fpr.pedido_id = p.id_pedido 
                --  AND fpr.pedido_id = (SELECT idPeriodoEscolar FROM pedidos WHERE id_pedido = fpr.pedido_id LIMIT 1)
                WHERE fpr.id = ?
            ", [$proforma_id]);
            
            if (empty($proforma)) {
                return response()->json([
                    "status" => 404,
                    "message" => "Proforma no encontrada"
                ], 404);
            }
            
            // Obtener detalles de la proforma con información completa
            $detalles = DB::SELECT("
                SELECT 
                    dp.det_prof_id,
                    dp.prof_id,
                    dp.pro_codigo,
                    dp.det_prof_cantidad,
                    dp.det_prof_valor_u,
                    dp.contrato,
                    p.pro_nombre,
                    p.pro_reservar as stock_disponible,
                    p.pro_estado
                FROM f_detalle_proforma dp
                LEFT JOIN 1_4_cal_producto p ON dp.pro_codigo = p.pro_codigo
                WHERE dp.prof_id = ?
                ORDER BY dp.det_prof_id
            ", [$proforma_id]);
            
            // Verificar si es la primera proforma del pedido
            $esPrimera = $this->esPrimeraProforma($proforma[0]->pedido_id, $proforma_id);
            
            return response()->json([
                "status" => 200,
                "data" => [
                    "proforma" => $proforma[0],
                    "detalles" => $detalles,
                    "es_primera_proforma" => $esPrimera
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "Error al obtener la proforma",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Método helper para determinar si una proforma es la primera del pedido
     * Excluye proformas anuladas (prof_estado = 0)
     * 
     * @param int $pedidoId ID del pedido
     * @param int|null $proformaId ID de la proforma a verificar (null si es nueva)
     * @return bool true si es la primera proforma, false en caso contrario
     */
    private function esPrimeraProforma($pedidoId, $proformaId = null)
    {
        try {
            // Obtener la primera proforma del pedido (excluyendo anuladas)
            $primeraProforma = DB::table('f_proforma')
                ->where('pedido_id', $pedidoId)
                ->where('prof_estado', '!=', 0) // Excluir anuladas
                ->orderBy('created_at', 'asc')
                ->first();
            
            // Si no hay proformas, esta será la primera
            if (!$primeraProforma) {
                return true;
            }
            
            // Si estamos creando (no hay ID), verificar si ya existe alguna
            if ($proformaId === null) {
                return false; // Ya existe una proforma, esta no será la primera
            }
            
            // Si estamos editando, verificar si esta proforma es la primera
            return $primeraProforma->id == $proformaId;
            
        } catch (\Exception $e) {
            // En caso de error, retornar false para no bloquear operaciones
            return false;
        }
    }
    
    /**
     * Actualizar una proforma existente desde ProformaModal
     * Maneja el control de stock de manera inteligente según las cantidades modificadas
     */
    public function Proforma_Actualizar_Desde_Modal(Request $request)
    {
        try {

            // return $request;
            $miarray = json_decode($request->data_detalle);
            $proforma_id = $request->proforma_id;
            
            if (!$proforma_id) {
                return response()->json([
                    "status" => 400,
                    "message" => "El ID de la proforma es requerido"
                ], 400);
            }
            
            // Obtener los detalles antiguos ANTES de modificar
            $detallesAntiguos = DetalleProforma::where('prof_id', $proforma_id)->get();
            
            // ======================================================
            // VALIDACIÓN DE STOCK EN TIEMPO REAL (ANTES DE ACTUALIZAR)
            // ======================================================
            $productosInvalidos = [];
            
            foreach ($miarray as $item) {
                $cantidad_nueva = isset($item->det_prof_cantidad) ? $item->det_prof_cantidad : 
                                 (isset($item->cantidad) ? $item->cantidad : 0);
                $pro_codigo = isset($item->pro_codigo) ? $item->pro_codigo : 
                             (isset($item->codigo_liquidacion) ? $item->codigo_liquidacion : null);
                
                if (!$pro_codigo || (int)$cantidad_nueva == 0) {
                    continue;
                }
                
                // Consultar stock ACTUAL en tiempo real
                $stockQuery = DB::SELECT("SELECT pro_codigo, pro_nombre, pro_reservar FROM 1_4_cal_producto WHERE pro_codigo = ?", [$pro_codigo]);
                
                if (!empty($stockQuery)) {
                    $stockActual = (int)$stockQuery[0]->pro_reservar;
                    $cantidad_nueva_int = (int)$cantidad_nueva;
                    
                    // Buscar si este libro existía en la proforma anterior
                    $detalleAntiguo = $detallesAntiguos->firstWhere('pro_codigo', $pro_codigo);
                    
                    if ($detalleAntiguo) {
                        // El libro YA existía - calcular cuánto stock ADICIONAL necesitamos
                        $cantidad_antigua = (int)$detalleAntiguo->det_prof_cantidad;
                        $diferencia = $cantidad_nueva_int - $cantidad_antigua;
                        
                        // Si aumentó, validar que haya stock suficiente para cubrir el aumento
                        if ($diferencia > 0) {
                            if ($stockActual < $diferencia) {
                                $productosInvalidos[] = [
                                    'pro_codigo' => $pro_codigo,
                                    'pro_nombre' => $stockQuery[0]->pro_nombre,
                                    'stock_actual' => $stockActual,
                                    'cantidad_antigua' => $cantidad_antigua,
                                    'cantidad_nueva' => $cantidad_nueva_int,
                                    'aumento_requerido' => $diferencia,
                                    'faltante' => $diferencia - $stockActual
                                ];
                            }
                        }
                        // Si disminuyó o se mantuvo igual, no hay problema de stock
                    } else {
                        // Es un libro NUEVO - validar stock completo
                        if ($stockActual < $cantidad_nueva_int) {
                            $productosInvalidos[] = [
                                'pro_codigo' => $pro_codigo,
                                'pro_nombre' => $stockQuery[0]->pro_nombre,
                                'stock_actual' => $stockActual,
                                'cantidad_requerida' => $cantidad_nueva_int,
                                'faltante' => $cantidad_nueva_int - $stockActual,
                                'es_nuevo' => true
                            ];
                        }
                    }
                }
            }
            
            // Si hay productos sin stock suficiente, retornar error
            if (!empty($productosInvalidos)) {
                return response()->json([
                    'status' => 400,
                    'error' => 'STOCK_INSUFICIENTE',
                    'message' => 'No hay stock suficiente para actualizar la proforma',
                    'productos_invalidos' => $productosInvalidos
                ], 400);
            }
            
            // Si todo está OK, proceder con la transacción
            DB::beginTransaction();
    
            // Buscar la proforma existente
            $proforma = Proforma::findOrFail($proforma_id);
    
            // Actualizar datos de la proforma (NO se modifica prof_id ni created_at)
            $proforma->prof_observacion = $request->prof_observacion;
            $proforma->prof_observacion_libreria = $request->prof_observacion_libreria ?? '';
            $proforma->prof_com = $request->prof_com;
            $proforma->prof_descuento = $request->prof_descuento;
            $proforma->pro_des_por = $request->pro_des_por;
            $proforma->prof_iva = $request->prof_iva;
            $proforma->prof_iva_por = $request->prof_iva_por;
            $proforma->prof_total = $request->prof_total;
            $proforma->prof_estado = $request->prof_estado;
            
            // ✅ Actualizar lugar de despacho y cliente (si vienen en el request)
            if ($request->has('id_ins_depacho')) {
                $proforma->id_ins_depacho = $request->id_ins_depacho;
            }
            if ($request->has('ven_cliente')) {
                $proforma->ven_cliente = $request->ven_cliente;
            }
            if ($request->has('ruc_cliente')) {
                $proforma->ruc_cliente = $request->ruc_cliente;
            }
            if ($request->has('clientesidPerseo')) {
                $proforma->clientesidPerseo = $request->clientesidPerseo;
            }
            if ($request->has('emp_id')) {
                $proforma->emp_id = $request->emp_id;
            }
            
            // ✅ Actualizar período si viene en el request (VALIDAR QUE NO SEA NULL)
            if ($request->has('id_periodo') && $request->id_periodo) {
                $proforma->id_periodo = $request->id_periodo;
            }
            
            // Guardar cambios en la proforma
            $proforma->save();
            
            // ELIMINAR todos los detalles antiguos
            DetalleProforma::where('prof_id', $proforma_id)->delete();
            
            // PROCESAR NUEVOS DETALLES Y AJUSTAR STOCK
            foreach ($miarray as $key => $item) {
                $cantidad_nueva = isset($item->det_prof_cantidad) ? $item->det_prof_cantidad : 
                                 (isset($item->cantidad) ? $item->cantidad : 0);
                $pro_codigo = isset($item->pro_codigo) ? $item->pro_codigo : 
                             (isset($item->codigo_liquidacion) ? $item->codigo_liquidacion : null);
                $precio = isset($item->det_prof_valor_u) ? $item->det_prof_valor_u : 
                         (isset($item->precio) ? $item->precio : 0);
                
                if (!$pro_codigo || (int)$cantidad_nueva == 0) {
                    continue;
                }
                
                // Buscar si este libro existía en la proforma anterior
                $detalleAntiguo = $detallesAntiguos->firstWhere('pro_codigo', $pro_codigo);
                
                if ($detalleAntiguo) {
                    // EL LIBRO YA EXISTÍA - Comparar cantidades
                    $cantidad_antigua = (int)$detalleAntiguo->det_prof_cantidad;
                    $cantidad_nueva_int = (int)$cantidad_nueva;
                    $diferencia = $cantidad_nueva_int - $cantidad_antigua;
                    
                    if ($diferencia != 0) {
                        // Hay diferencia, ajustar stock
                        $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$pro_codigo'");
                        if (!empty($query1)) {
                            $stockActual = (int)$query1[0]->stoc;
                            
                            if ($diferencia > 0) {
                                // Aumentó la cantidad → RESTAR del stock
                                $nuevoStock = $stockActual - $diferencia;
                            } else {
                                // Disminuyó la cantidad → SUMAR al stock
                                $diferencia_positiva = abs($diferencia);
                                $nuevoStock = $stockActual + $diferencia_positiva;
                            }
                            
                            $pro = _14Producto::findOrFail($pro_codigo);
                            $pro->pro_reservar = $nuevoStock;
                            $pro->save();
                        }
                    }
                } else {
                    // ES UN LIBRO NUEVO - Restar cantidad del stock
                    $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='$pro_codigo'");
                    if (!empty($query1)) {
                        $stockActual = (int)$query1[0]->stoc;
                        $nuevoStock = $stockActual - (int)$cantidad_nueva;
                        
                        $pro = _14Producto::findOrFail($pro_codigo);
                        $pro->pro_reservar = $nuevoStock;
                        $pro->save();
                    }
                }
                
                // Crear el nuevo detalle
                $DetalleProforma = new DetalleProforma;
                $DetalleProforma->prof_id = $proforma_id;
                $DetalleProforma->pro_codigo = $pro_codigo;
                $DetalleProforma->det_prof_cantidad = (int)$cantidad_nueva;
                $DetalleProforma->det_prof_valor_u = $precio;
                
                if ($request->contrato) {
                    $DetalleProforma->contrato = $request->contrato;
                }
                
                $DetalleProforma->save();
            }
            
            // VERIFICAR LIBROS ELIMINADOS
            foreach ($detallesAntiguos as $detalleAntiguo) {
                $libroEnNuevaLista = collect($miarray)->firstWhere('pro_codigo', $detalleAntiguo->pro_codigo);
                
                if (!$libroEnNuevaLista) {
                    // Este libro fue ELIMINADO - Devolver su cantidad al stock
                    $query1 = DB::SELECT("SELECT pro_reservar as stoc from 1_4_cal_producto where pro_codigo='{$detalleAntiguo->pro_codigo}'");
                    if (!empty($query1)) {
                        $stockActual = (int)$query1[0]->stoc;
                        $nuevoStock = $stockActual + (int)$detalleAntiguo->det_prof_cantidad;
                        
                        $pro = _14Producto::findOrFail($detalleAntiguo->pro_codigo);
                        $pro->pro_reservar = $nuevoStock;
                        $pro->save();
                    }
                }
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 200,
                'message' => 'Proforma actualizada exitosamente',
                'proforma_id' => $proforma_id,
                'prof_id' => $proforma->prof_id
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "No se pudo actualizar la proforma",
                "line" => $e->getLine()
            ], 500);
        }
    }

    // ==================== NUEVOS MÉTODOS PARA PROFORMAS DE FACTURACIÓN ====================
    
    /**
     * Actualizar estado de proforma (Aprobar/Anular)
     * Estados: 0 = Anulada, 1 = Pendiente, 2 = Aprobada, 3 = Rechazada
     */
    public function actualizarEstadoProforma(Request $request)
    {
        try {
            DB::beginTransaction();

            $proforma = Proforma::findOrFail($request->id);
            $estadoAnterior = $proforma->prof_estado;
            $estadoNuevo = $request->prof_estado;

            // Validar que el estado sea válido
            if (!in_array($estadoNuevo, [0, 1, 2, 3])) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Estado inválido'
                ], 400);
            }

            // GUARDAR OLD VALUE (proforma + detalles ANTES del cambio)
            $detallesAntiguos = DetalleProforma::where('prof_id', $request->id)->get();
            $oldValue = [
                'proforma' => $proforma->toArray(),
                'detalles' => $detallesAntiguos->toArray()
            ];

            // Si se está anulando (estado 0), validar que solo se pueda anular desde estado 1 o 3
            if ($estadoNuevo == 0 && $estadoAnterior != 0) {
                // Solo se puede anular si está en estado 1 (Pendiente) o 3 (Aprobado)
                // NO se puede anular si está en estado 6 (Aprobado con pendiente - tiene ventas)
                if ($estadoAnterior != 1 && $estadoAnterior != 3) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Solo se pueden anular proformas en estado Pendiente (1) o Aprobado (3). Las proformas con ventas activas (estado 6) no se pueden anular.'
                    ], 400);
                }
                
                // Verificar si hay ventas NO anuladas (estado diferente de 3)
                $ventasActivas = DB::SELECT("SELECT v.ven_codigo, v.est_ven_codigo
                    FROM f_venta v
                    WHERE v.ven_idproforma = '$proforma->prof_id'
                    AND v.id_empresa = '$proforma->emp_id'
                    AND v.est_ven_codigo <> 3
                ");
                
                if (count($ventasActivas) > 0) {
                    $documentos = implode(', ', array_map(function($v) { return $v->ven_codigo; }, $ventasActivas));
                    return response()->json([
                        'status' => 400,
                        'message' => "No se puede anular la proforma porque tiene ventas activas. Por favor, primero anule los siguientes documentos: $documentos"
                    ], 400);
                }
                
                foreach ($detallesAntiguos as $detalle) {
                    $query = DB::SELECT("SELECT pro_reservar FROM 1_4_cal_producto WHERE pro_codigo = ?", [$detalle->pro_codigo]);
                    if (!empty($query)) {
                        $stockActual = (int)$query[0]->pro_reservar;
                        $nuevoStock = $stockActual + (int)$detalle->det_prof_cantidad;
                        
                        DB::UPDATE("UPDATE 1_4_cal_producto SET pro_reservar = ? WHERE pro_codigo = ?", [$nuevoStock, $detalle->pro_codigo]);
                    }
                }
            }

            // Actualizar estado
            $proforma->prof_estado = $estadoNuevo;
            $proforma->usu_codigo = $request->idusuario;
            $proforma->updated_at = now();
            $proforma->save();

            // GUARDAR NEW VALUE (proforma + detalles DESPUÉS del cambio)
            $proforma->refresh(); // Refrescar para obtener valores actualizados
            $detallesNuevos = DetalleProforma::where('prof_id', $request->id)->get();
            $newValue = [
                'proforma' => $proforma->toArray(),
                'detalles' => $detallesNuevos->toArray()
            ];

            // Registrar en histórico con old_value y new_value
            $historico = new Proformahistorico();
            $historico->id_prof = $proforma->prof_id;
            $historico->old_value = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
            $historico->new_value = json_encode($newValue, JSON_UNESCAPED_UNICODE);
            $historico->id_usua = $request->idusuario;
            $historico->created_at = now();
            $historico->save();

            DB::commit();

            $mensaje = '';
            switch ($estadoNuevo) {
                case 0:
                    $mensaje = 'Proforma anulada exitosamente';
                    break;
                case 2:
                    $mensaje = 'Proforma aprobada exitosamente';
                    break;
                case 3:
                    $mensaje = 'Proforma rechazada exitosamente';
                    break;
                default:
                    $mensaje = 'Estado actualizado exitosamente';
            }

            return response()->json([
                'status' => 200,
                'message' => $mensaje,
                'proforma' => $proforma
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 500,
                'error' => $e->getMessage(),
                'message' => 'No se pudo actualizar el estado de la proforma',
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Eliminar proforma (solo si está Anulada - estado 0)
     * No afecta el stock porque ya se devolvió al anular
     */
    public function eliminarProforma(Request $request)
    {
        try {
            DB::beginTransaction();

            $proforma = Proforma::findOrFail($request->prof_id);

            // Validar que la proforma esté en estado Anulada (0)
            if ($proforma->prof_estado != 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Solo se pueden eliminar proformas anuladas. Primero anule la proforma antes de eliminarla.'
                ], 400);
            }

            // Verificar que no tenga documentos de venta asociados
            $ventasAsociadas = DB::SELECT("SELECT COUNT(*) as total FROM f_venta WHERE ven_idproforma = ?", [$proforma->prof_id]);
            if ($ventasAsociadas[0]->total > 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'No se puede eliminar la proforma porque tiene documentos de venta asociados'
                ], 400);
            }

            // NO devolver stock aquí porque ya se devolvió al anular

            // Eliminar detalles
            DetalleProforma::where('prof_id', $request->prof_id)->delete();

            // Eliminar histórico
            // Proformahistorico::where('id_prof', $proforma->prof_id)->delete();

            // Eliminar proforma
            $proforma->delete();

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Proforma eliminada exitosamente'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 500,
                'error' => $e->getMessage(),
                'message' => 'No se pudo eliminar la proforma',
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Crear documento de venta desde una proforma aprobada
     * Reutiliza APIs existentes (Get_DatosFactura, Get_PuntosV, Get_tdocu)
     */
    public function crearVentaDesdeProforma(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validar que la proforma exista y esté aprobada
            $proforma = Proforma::where('id', $request->prof_id)
                ->where('prof_id', $request->prof_codigo)
                ->first();

            if (!$proforma) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Proforma no encontrada'
                ], 404);
            }

            // Validar que la proforma esté en estado 3 (Aprobado) o 6 (Aprobado con pendiente)
            if ($proforma->prof_estado != 3 && $proforma->prof_estado != 6) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Solo se pueden crear ventas desde proformas aprobadas (estado 3) o con pendientes (estado 6)'
                ], 400);
            }

            // Obtener detalles de venta del request (con cantidades modificadas)
            $detallesVenta = json_decode($request->detalles_venta, true);
            $detallesProforma = DetalleProforma::where('prof_id', $request->prof_id)->get();

            // VALIDAR STOCK antes de procesar
            $productosInvalidos = [];
            foreach ($detallesVenta as $detalleVenta) {
                $detalleProforma = $detallesProforma->firstWhere('pro_codigo', $detalleVenta['pro_codigo']);
                
                if (!$detalleProforma) {
                    continue;
                }

                $cantidadProforma = (int)$detalleProforma->det_prof_cantidad;
                $cantidadVenta = (int)$detalleVenta['det_prof_cantidad'];

                // Si la cantidad de venta es mayor a la de proforma, validar stock disponible
                if ($cantidadVenta > $cantidadProforma) {
                    $diferencia = $cantidadVenta - $cantidadProforma;
                    
                    $stockQuery = DB::SELECT("SELECT pro_codigo, pro_nombre, pro_reservar FROM 1_4_cal_producto WHERE pro_codigo = ?", [$detalleVenta['pro_codigo']]);
                    
                    if (!empty($stockQuery)) {
                        $stockDisponible = (int)$stockQuery[0]->pro_reservar;
                        
                        if ($stockDisponible < $diferencia) {
                            $productosInvalidos[] = [
                                'pro_codigo' => $detalleVenta['pro_codigo'],
                                'pro_nombre' => $stockQuery[0]->pro_nombre,
                                'stock_disponible' => $stockDisponible,
                                'cantidad_requerida' => $diferencia,
                                'faltante' => $diferencia - $stockDisponible
                            ];
                        }
                    }
                }
            }

            // Si hay productos sin stock suficiente, retornar error
            if (!empty($productosInvalidos)) {
                return response()->json([
                    'status' => 400,
                    'error' => 'STOCK_INSUFICIENTE',
                    'message' => 'No hay stock suficiente para algunos productos',
                    'productos_invalidos' => $productosInvalidos
                ], 400);
            }

            // Generar código de venta
            $codigoVenta = $this->generarCodigoVenta($request->empresa, $request->tipo_documento, $proforma->contrato);
            if (!$codigoVenta) {
                return response()->json([
                    'status' => 500,
                    'message' => 'No se pudo generar el código de venta'
                ], 500);
            }
            // Calcular totales basados en los detalles modificados
            $subtotalCalculado = 0;
            foreach ($detallesVenta as $item) {
                $subtotalCalculado += (float)$item['det_prof_cantidad'] * (float)$item['det_prof_valor_u'];
            }
            
            $porcentajeDescuento = (float)$proforma->pro_des_por;
            $descuentoCalculado = $subtotalCalculado * $porcentajeDescuento / 100;
            $baseImponible = $subtotalCalculado - $descuentoCalculado;
            $porcentajeIVA = (float)$proforma->prof_iva_por;
            $ivaCalculado = $baseImponible * $porcentajeIVA / 100;
            $totalFinal = $subtotalCalculado - $descuentoCalculado + $ivaCalculado;

            // VALIDAR que la proforma tenga periodo asignado (NO NULL)
            if (empty($request->periodo_id)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Periodo no especificado. No se puede generar el documento de venta.'
                ], 400);
            }

            // Crear documento de venta
            DB::table('f_venta')->insert([
                'ven_codigo' => $codigoVenta,
                'contrato' => $proforma->contrato,
                'ven_idproforma' => $proforma->prof_id,
                'id_empresa' => $request->empresa,
                'ven_valor' => $totalFinal,
                'institucion_id' => $proforma->id_ins_depacho,
                'ruc_cliente' => $proforma->ruc_cliente,
                'tip_ven_codigo' => $request->tipo_documento,
                'idtipodoc' => $request->tipo_documento,
                // 'idPuntoventa' => $request->punto_venta,
                'ven_fecha' => now(),
                'user_created' => $request->idusuario,
                'ven_subtotal' => $subtotalCalculado,
                'ven_desc_por' => $porcentajeDescuento,
                'ven_descuento' => $descuentoCalculado,
                'ven_transporte' => 0,
                'ven_iva' => $ivaCalculado,
                'est_ven_codigo' => 2,
                'ven_observacion' => $request->observacion ?? $proforma->prof_observacion,
                'ven_tipo_inst' => $request->tipo_venta ?? 'L',
                'ven_cliente' => $proforma->ven_cliente,
                'periodo_id' => $request->periodo_id
            ]);

            // Crear detalles de venta con cantidades modificadas y ajustar stock
            foreach ($detallesVenta as $detalleVenta) {
                $detalleProforma = $detallesProforma->firstWhere('pro_codigo', $detalleVenta['pro_codigo']);
                
                if (!$detalleProforma) {
                    continue;
                }

                // Insertar detalle de venta
                DB::table('f_detalle_venta')->insert([
                    'ven_codigo' => $codigoVenta,
                    'id_empresa' => $request->empresa,
                    'pro_codigo' => $detalleVenta['pro_codigo'],
                    'det_ven_cantidad' => $detalleVenta['det_prof_cantidad'],
                    'contrato' => $proforma->contrato,
                    'det_ven_valor_u' => $detalleVenta['det_prof_valor_u'],
                    'det_ven_dev' => 0,
                    // 'created_at' => now(),
                    // 'user_created' => $request->idusuario
                ]);

                // Ajustar stock inteligentemente según empresa, tipo de documento y activar_todo_stock
                $cantidadProforma = (int)$detalleProforma->det_prof_cantidad;
                $cantidadVenta = (int)$detalleVenta['det_prof_cantidad'];
                $activarTodoStock = $request->activar_todo_stock ?? false;
                
                // VALIDAR: NO se puede vender MÁS de lo proformado
                if ($cantidadVenta > $cantidadProforma) {
                    throw new \Exception("No se puede vender más de lo proformado para el producto {$detalleVenta['pro_codigo']}. Proformado: {$cantidadProforma}, Intentando vender: {$cantidadVenta}");
                }
                
                $producto = _14Producto::findOrFail($detalleVenta['pro_codigo']);
                
                // SIEMPRE restar la cantidad vendida del stock correspondiente
                $this->restarStockInteligente($producto, $cantidadVenta, $request->empresa, $request->tipo_documento, $activarTodoStock, $detalleVenta);
                
                // NO tocar pro_reservar aquí
                // - La reserva se hizo al crear la proforma
                // - Si se vende MENOS, la reserva "fantasma" se mantiene para futuros documentos del pendiente
                // - La reserva solo se devuelve al ANULAR la proforma o cuando se complete toda la venta
                
                $producto->save();
            }

            // Registrar en histórico de proforma con old_value y new_value
            $proformaConDetalles = [
                'proforma' => $proforma->toArray(),
                'detalles' => $detallesProforma->toArray(),
                'venta_generada' => [
                    'ven_codigo' => $codigoVenta,
                    'fecha_generacion' => now()
                ]
            ];
            
            $historico = new Proformahistorico();
            $historico->id_prof = $proforma->prof_id;
            $historico->old_value = json_encode(['proforma' => $proforma->toArray(), 'detalles' => $detallesProforma->toArray()], JSON_UNESCAPED_UNICODE);
            $historico->new_value = json_encode($proformaConDetalles, JSON_UNESCAPED_UNICODE);
            $historico->id_usua = $request->idusuario;
            $historico->created_at = now();
            $historico->save();

            // ACTUALIZAR ESTADO DE LA PROFORMA
            // Verificar si se vendió todo o quedaron pendientes
            // Necesitamos consultar TODAS las ventas de esta proforma, no solo la actual
            $todoVendido = true;
            
            // Obtener el total vendido de cada producto (incluyendo TODAS las ventas anteriores + esta)
            $cantidadesVendidasTotal = DB::SELECT("
                SELECT 
                    dv.pro_codigo,
                    SUM(dv.det_ven_cantidad) as cantidad_total_vendida
                FROM f_detalle_venta dv
                INNER JOIN f_venta fv ON dv.ven_codigo = fv.ven_codigo AND dv.id_empresa = fv.id_empresa
                INNER JOIN f_proforma fp ON fp.prof_id = fv.ven_idproforma AND fp.emp_id = fv.id_empresa
                WHERE fp.id = ?
                AND fv.est_ven_codigo <> 3
                GROUP BY dv.pro_codigo
            ", [$proforma->id]);
            
            // Convertir a array asociativo para fácil acceso
            $cantidadesMap = [];
            foreach ($cantidadesVendidasTotal as $item) {
                $cantidadesMap[$item->pro_codigo] = (int)$item->cantidad_total_vendida;
            }
            
            // Comparar el total vendido con lo proformado
            foreach ($detallesProforma as $detalleProforma) {
                $cantidadProforma = (int)$detalleProforma->det_prof_cantidad;
                $cantidadTotalVendida = $cantidadesMap[$detalleProforma->pro_codigo] ?? 0;
                
                // Si algún producto tiene menos vendido que lo proformado, hay pendientes
                if ($cantidadTotalVendida < $cantidadProforma) {
                    $todoVendido = false;
                    break;
                }
            }
            
            // Actualizar estado:
            // 2 = Finalizada (si se vendió todo)
            // 6 = Aprobado con pendiente (si quedaron items pendientes)
            $nuevoEstado = $todoVendido ? 2 : 6;
            
            DB::table('f_proforma')
                ->where('id', $proforma->id)
                ->update([
                    'prof_estado' => $nuevoEstado,
                    'updated_at' => now()
                    // 'user_updated' => $request->idusuario
                ]);

            DB::commit();

            $mensajeEstado = $todoVendido 
                ? 'Proforma finalizada completamente' 
                : 'Proforma con items pendientes';

            return response()->json([
                'status' => 200,
                'message' => "Documento de venta creado exitosamente. $mensajeEstado",
                'ven_codigo' => $codigoVenta,
                'estado_proforma' => $nuevoEstado,
                'todo_vendido' => $todoVendido
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 500,
                'error' => $e->getMessage(),
                'message' => 'No se pudo crear el documento de venta',
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Generar código de venta único
     * Formato: TipoDoc-Empresa-Temporada-Iniciales-Secuencial
     * Ejemplos:
     * - PF-C-S25-FR-0001032 (Prefactura)
     * - N-C-S25-FR-BC206 (Nota BC)
     * - N-C-S241-FR-AI006 (Nota AI)
     * 
     * Usa SELECT FOR UPDATE para evitar race conditions en concurrencia
     */
    private function generarCodigoVenta($empresa, $tipoDocumento, $contrato)
    {
        try {
            // 1. Obtener información completa del tipo de documento CON BLOQUEO DE FILA
            // Esto previene que dos procesos obtengan el mismo secuencial simultáneamente
            $tipoDoc = DB::SELECT("SELECT tdo_id, tdo_nombre, tdo_letra, tdo_secuencial_Prolipa, tdo_secuencial_calmed, tdo_secuencial_SinEmpresa 
                FROM f_tipo_documento 
                WHERE tdo_id = ?
                FOR UPDATE", [$tipoDocumento]);

            if (empty($tipoDoc)) {
                throw new \Exception('Tipo de documento no encontrado');
            }
            
            $letraTipoDoc = $tipoDoc[0]->tdo_letra;
            $nombreTipoDoc = $tipoDoc[0]->tdo_nombre;
            
            // 2. Obtener letra de la empresa (P = Prolipa, C = Calmed)
            $letraEmpresa = '';
            $secuenciaActual = 0;
            
            if ($empresa == 1) {
                $letraEmpresa = 'P';
                $secuenciaActual = $tipoDoc[0]->tdo_secuencial_Prolipa;
            } else if ($empresa == 3) {
                $letraEmpresa = 'C';
                $secuenciaActual = $tipoDoc[0]->tdo_secuencial_calmed;
            } else {
                $letraEmpresa = 'SE';
                $secuenciaActual = $tipoDoc[0]->tdo_secuencial_SinEmpresa;
            }
            
            // 3. Obtener código contrato (temporada) del período escolar usando el contrato
            $punto = DB::SELECT("SELECT pv.*, pe.codigo_contrato 
                FROM pedidos pv
                LEFT JOIN periodoescolar pe ON pv.id_periodo = pe.idperiodoescolar
                WHERE pv.contrato_generado = ?", [$contrato]);
            
            if (empty($punto)) {
                throw new \Exception('Contrato no encontrado en pedidos');
            }
            
            $codigoContrato = $punto[0]->codigo_contrato ?? '';
            
            if (empty($codigoContrato)) {
                throw new \Exception('No se pudo obtener el código de contrato del período escolar');
            }
            
            // 4. Obtener iniciales del usuario desde el request
            $usuario = DB::SELECT("SELECT iniciales FROM usuario WHERE idusuario = ?", [request()->input('idusuario')]);
            
            if (empty($usuario) || empty($usuario[0]->iniciales)) {
                throw new \Exception('No se pudieron obtener las iniciales del usuario');
            }
            
            $iniciales = $usuario[0]->iniciales;
            
            // 5. Incrementar secuencial
            $nuevoSecuencial = $secuenciaActual + 1;
            
            // 6. Formatear secuencial según el tipo de documento
            $secuenciaFormateada = '';
            
            // Detectar si es NOTA DE VENTA (BC) o (AI) basado en el nombre
            if (strpos($nombreTipoDoc, 'NOTA DE VENTA (BC)') !== false) {
                // Formato: BC + número corto (BC206, BC001, etc.)
                $secuenciaFormateada = 'BC' . str_pad($nuevoSecuencial, 3, '0', STR_PAD_LEFT);
            } 
            else if (strpos($nombreTipoDoc, 'NOTA DE VENTA (AI)') !== false) {
                // Formato: AI + número corto (AI006, AI001, etc.)
                $secuenciaFormateada = 'AI' . str_pad($nuevoSecuencial, 3, '0', STR_PAD_LEFT);
            } 
            else {
                // Formato estándar: 7 dígitos (0001032, 0000001, etc.)
                $secuenciaFormateada = str_pad($nuevoSecuencial, 7, '0', STR_PAD_LEFT);
            }
            
            // 7. Actualizar la secuencia en la base de datos
            // El UPDATE se ejecuta mientras mantenemos el bloqueo de la fila
            if ($empresa == 1) {
                DB::UPDATE("UPDATE f_tipo_documento SET tdo_secuencial_Prolipa = ? WHERE tdo_id = ?", [$nuevoSecuencial, $tipoDocumento]);
            } else if ($empresa == 3) {
                DB::UPDATE("UPDATE f_tipo_documento SET tdo_secuencial_calmed = ? WHERE tdo_id = ?", [$nuevoSecuencial, $tipoDocumento]);
            } else {
                DB::UPDATE("UPDATE f_tipo_documento SET tdo_secuencial_SinEmpresa = ? WHERE tdo_id = ?", [$nuevoSecuencial, $tipoDocumento]);
            }
            
            // 8. Generar código final: TipoDoc-Empresa-Temporada-Iniciales-Secuencial
            $codigo = $letraTipoDoc=='BC'|| $letraTipoDoc=='AI'? 'N' . '-' . $letraEmpresa . '-' . $codigoContrato . '-' . $iniciales . '-' . $secuenciaFormateada:'PF' . '-' . $letraEmpresa . '-' . $codigoContrato . '-' . $iniciales . '-' . $secuenciaFormateada;
            
            return $codigo;
            
        } catch (\Exception $e) {
            throw new \Exception('Error al generar código de venta: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener stocks de productos por sus códigos
     */
    public function Get_Stocks_Productos(Request $request)
    {
        try {
            $codigos = $request->codigos;
            
            if (empty($codigos) || !is_array($codigos)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Debe proporcionar un array de códigos de productos'
                ], 400);
            }
            
            // Consultar stocks de productos
            $productos = DB::table('1_4_cal_producto')
                ->select(
                    'pro_codigo',
                    'pro_reservar',
                    'pro_stock',
                    'pro_deposito',
                    'pro_stockCalmed',
                    'pro_depositoCalmed'
                )
                ->whereIn('pro_codigo', $codigos)
                ->get();
            
            return response()->json($productos);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener stocks: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener todas las ventas de un contrato específico
     * Similar a get_proformas_por_contrato pero para documentos de venta
     */
    public function get_ventas_por_contrato(Request $request)
    {
        try {
            $contrato = $request->contrato;
            
            if (!$contrato) {
                return response()->json([
                    "status" => 400,
                    "message" => "El contrato es requerido"
                ], 400);
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
                    fv.user_created,
                    fv.ven_fecha,
                    fv.periodo_id,
                    fv.ven_tipo_inst,
                    fpr.contrato,
                    fpr.prof_id,
                    em.id as empresa_id,
                    em.nombre as empresa_nombre,
                    em.img_base64 as empresa_logo,
                    CONCAT(COALESCE(uc.nombres, ''), ' ', COALESCE(uc.apellidos, '')) AS cliente_nombre,
                    uc.cedula as cliente_ruc,
                    uc.email as cliente_email,
                    i.idInstitucion as institucion_id,
                    i.nombreInstitucion as institucion_nombre,
                    i.ruc as institucion_ruc,
                    i.direccionInstitucion as institucion_direccion,
                    i.telefonoInstitucion as institucion_telefono,
                    COUNT(DISTINCT dfv.pro_codigo) as total_items,
                    SUM(dfv.det_ven_cantidad) as total_cantidad,
                    CONCAT(COALESCE(usr.nombres, ''), ' ', COALESCE(usr.apellidos, '')) AS creado_por,
                    usr.cedula as creado_por_cedula,
                    td.tdo_id,
                    td.tdo_nombre,
                    CASE 
                        WHEN fv.est_ven_codigo = 0 THEN 'Pendiente'
                        WHEN fv.est_ven_codigo = 1 THEN 'Despachado'
                        WHEN fv.est_ven_codigo = 2 THEN 'Liquidado'
                        WHEN fv.est_ven_codigo = 3 THEN 'Anulado'
                        ELSE 'Desconocido'
                    END as estado_texto,
                    CONCAT(COALESCE(ua.nombres, ''), ' ', COALESCE(ua.apellidos, '')) AS anulado_por,
                    fv.user_anulado,
                    fv.estadoPerseo,
                    fv.anuladoEnPerseo
                FROM f_venta fv
                LEFT JOIN f_proforma fpr ON fpr.prof_id = fv.ven_idproforma
                LEFT JOIN empresas em ON fv.id_empresa = em.id
                LEFT JOIN institucion i ON fpr.id_ins_depacho = i.idInstitucion
                LEFT JOIN usuario uc ON fpr.ven_cliente = uc.idusuario
                LEFT JOIN usuario usr ON fv.user_created = usr.idusuario
                LEFT JOIN usuario ua ON fv.user_anulado = ua.idusuario
                LEFT JOIN f_detalle_venta dfv ON fv.ven_codigo = dfv.ven_codigo AND fv.id_empresa = dfv.id_empresa
                LEFT JOIN f_tipo_documento td ON fv.idtipodoc = td.tdo_id
                WHERE fpr.contrato = ?
                GROUP BY fv.ven_codigo, fv.id_empresa, fv.ven_idproforma, fv.est_ven_codigo, 
                         fv.idtipodoc, fv.ven_subtotal, fv.ven_descuento, fv.ven_iva, 
                         fv.ven_transporte, fv.ven_valor, fv.ven_observacion, fv.user_created,
                         fv.ven_fecha, fv.periodo_id,
                         fpr.contrato, fpr.prof_id, em.id, em.nombre, em.img_base64,
                         uc.nombres, uc.apellidos, uc.cedula, uc.email,
                         i.idInstitucion, i.nombreInstitucion, i.ruc, i.direccionInstitucion, i.telefonoInstitucion,
                         usr.nombres, usr.apellidos, usr.cedula, td.tdo_id, td.tdo_nombre,
                         ua.nombres, ua.apellidos, fv.user_anulado, fv.estadoPerseo, fv.anuladoEnPerseo
                ORDER BY fv.ven_fecha DESC
            ", [$contrato]);
            
            return response()->json([
                "status" => 200,
                "data" => $ventas
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "Error al obtener ventas",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Obtener libros vendidos por contrato
     * Similar a get_libros_proformados_por_contrato pero para ventas
     * Agrupa por producto y descuento
     */
    public function get_libros_vendidos_por_contrato(Request $request)
    {
        try {
            $contrato = $request->contrato;
            $periodo = $request->periodo;
            
            if (!$contrato) {
                return response()->json([
                    "status" => 400,
                    "message" => "El contrato es requerido"
                ], 400);
            }
            
            // Consulta para obtener los libros vendidos agrupados por producto Y descuento
            // Excluye ventas anuladas (est_ven_codigo = 3)
            $librosVendidos = DB::SELECT("
                SELECT 
                    dv.pro_codigo,
                    MAX(p.pro_nombre) as nombre_libro,
                    fv.ven_desc_por as descuento_venta,
                    SUM(dv.det_ven_cantidad) as cantidad_vendida,
                    MAX(dv.det_ven_valor_u) as precio_unitario,
                    SUM(dv.det_ven_cantidad * dv.det_ven_valor_u) as subtotal_vendido,
                    SUM(dv.det_ven_cantidad * dv.det_ven_valor_u * (1 - COALESCE(fv.ven_desc_por, 0) / 100)) as total_vendido
                FROM f_detalle_venta dv
                INNER JOIN f_venta fv ON dv.ven_codigo = fv.ven_codigo AND dv.id_empresa = fv.id_empresa
                INNER JOIN 1_4_cal_producto p ON dv.pro_codigo = p.pro_codigo
                INNER JOIN f_proforma fp ON fv.ven_idproforma = fp.prof_id
                WHERE fp.contrato = ?
                AND fv.est_ven_codigo <> 3
                GROUP BY dv.pro_codigo, fv.ven_desc_por
                ORDER BY MAX(p.pro_nombre), fv.ven_desc_por
            ", [$contrato]);
            
            return response()->json([
                "status" => 200,
                "data" => $librosVendidos
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "Error al obtener libros vendidos",
                "line" => $e->getLine()
            ], 500);
        }
    }

    /**
     * Obtener cantidades ya vendidas de una proforma específica
     * Para permitir ventas parciales sucesivas
     */
    public function get_cantidades_vendidas_proforma(Request $request)
    {
        try {
            $proformaId = $request->proforma_id;
            
            if (!$proformaId) {
                return response()->json([
                    "status" => 400,
                    "message" => "El ID de la proforma es requerido"
                ], 400);
            }
            
            // Consulta para obtener las cantidades ya vendidas de esta proforma
            // Agrupa por producto y suma todas las ventas NO anuladas
            $cantidadesVendidas = DB::SELECT("
                SELECT 
                    dv.pro_codigo,
                    SUM(dv.det_ven_cantidad) as cantidad_vendida
                FROM f_detalle_venta dv
                INNER JOIN f_venta fv ON dv.ven_codigo = fv.ven_codigo AND dv.id_empresa = fv.id_empresa
                INNER JOIN f_proforma fp ON fp.prof_id = fv.ven_idproforma AND fp.emp_id = fv.id_empresa
                WHERE fp.id = ?
                AND fv.est_ven_codigo <> 3
                GROUP BY dv.pro_codigo
            ", [$proformaId]);
            
            // Convertir a array asociativo para fácil acceso
            $resultado = [];
            foreach ($cantidadesVendidas as $item) {
                $resultado[$item->pro_codigo] = [
                    'pro_codigo' => $item->pro_codigo,
                    'cantidad_vendida' => (int)$item->cantidad_vendida
                ];
            }
            
            return response()->json([
                "status" => 200,
                "data" => $resultado
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "Error al obtener cantidades vendidas de la proforma",
                "line" => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Restar stock inteligentemente según empresa, tipo de documento y activar_todo_stock
     * 
     * Lógica:
     * - Empresa 1 (PROLIPA):
     *   - Tipo 1 (Factura): resta de pro_stock
     *   - Tipo 3,4 (Notas): resta de pro_deposito
     * - Empresa 3 (CALMED):
     *   - Tipo 1 (Factura): resta de pro_stockCalmed
     *   - Tipo 3,4 (Notas): resta de pro_depositoCalmed
     * 
     * Si activar_todo_stock = true y no hay suficiente en el stock correcto,
     * intenta restar de los otros stocks disponibles en orden de prioridad
     * 
     * @param _14Producto $producto Modelo del producto
     * @param int $cantidad Cantidad a restar
     * @param int $empresa ID de la empresa (1=PROLIPA, 3=CALMED)
     * @param int $tipoDocumento Tipo de documento (1=Factura, 3,4=Notas)
     * @param bool $activarTodoStock Si está activada la opción de usar todo el stock
     * @param array $detalleVenta Detalle de venta con los 4 stocks individuales
     * @throws \Exception Si no hay stock suficiente
     */
    private function restarStockInteligente($producto, $cantidad, $empresa, $tipoDocumento, $activarTodoStock, $detalleVenta)
    {
        // Determinar el campo de stock principal según empresa y tipo de documento
        $campoPrincipal = '';
        
        if ($empresa == 1) { // PROLIPA
            $campoPrincipal = ($tipoDocumento == 1) ? 'pro_stock' : 'pro_deposito';
        } elseif ($empresa == 3) { // CALMED
            $campoPrincipal = ($tipoDocumento == 1) ? 'pro_stockCalmed' : 'pro_depositoCalmed';
        }
        
        // Intentar restar del stock principal
        if ($producto->$campoPrincipal >= $cantidad) {
            // Hay suficiente en el stock principal
            $producto->$campoPrincipal -= $cantidad;
            // NO restar pro_reservar aquí - ya fue restado al crear la proforma
            return;
        }
        
        // Si no hay suficiente en el stock principal
        if (!$activarTodoStock) {
            // Si NO está activado "todo el stock", lanzar error
            throw new \Exception("Stock insuficiente en {$campoPrincipal} para el producto {$producto->pro_codigo}. Disponible: {$producto->$campoPrincipal}, Requerido: {$cantidad}");
        }
        
        // Si está activado "todo el stock", intentar restar de otros stocks disponibles
        $cantidadRestante = $cantidad;
        
        // 1. Restar lo que hay en el stock principal
        if ($producto->$campoPrincipal > 0) {
            $cantidadRestante -= $producto->$campoPrincipal;
            $producto->$campoPrincipal = 0;
        }
        
        // 2. Definir orden de prioridad para los otros stocks
        $stocksDisponibles = [
            'pro_stock' => $producto->pro_stock,
            'pro_deposito' => $producto->pro_deposito,
            'pro_stockCalmed' => $producto->pro_stockCalmed,
            'pro_depositoCalmed' => $producto->pro_depositoCalmed
        ];
        
        // Quitar el stock principal del array
        unset($stocksDisponibles[$campoPrincipal]);
        
        // 3. Restar de los otros stocks en orden hasta completar la cantidad
        foreach ($stocksDisponibles as $campo => $valor) {
            if ($cantidadRestante <= 0) {
                break;
            }
            
            if ($valor > 0) {
                if ($valor >= $cantidadRestante) {
                    // Hay suficiente en este stock
                    $producto->$campo -= $cantidadRestante;
                    $cantidadRestante = 0;
                } else {
                    // Restar todo lo que hay en este stock
                    $producto->$campo = 0;
                    $cantidadRestante -= $valor;
                }
            }
        }
        
        // 4. Verificar que se pudo restar toda la cantidad
        if ($cantidadRestante > 0) {
            throw new \Exception("Stock total insuficiente para el producto {$producto->pro_codigo}. Faltante: {$cantidadRestante}");
        }
        
        // NO restar pro_reservar aquí - ya fue restado al crear la proforma
        // Solo se ajustará en crearVentaDesdeProforma si hay diferencia entre proforma y venta
    }
    
    /**
     * Regresar stock al anular una venta
     * Solo devuelve al campo específico del que se descontó (pro_stock, pro_deposito, pro_stockCalmed, pro_depositoCalmed)
     * NO toca pro_reservar porque la reserva sigue vigente desde la proforma
     * 
     * @param int $codi Stock actual del campo específico
     * @param object $item Detalle de la venta con cantidad
     * @param int $tipo Tipo de documento (1=Factura, 3,4=Notas)
     * @param int $id_empresa ID de la empresa (1=PROLIPA, 3=CALMED)
     */
    public function regresarStock($codi,$item,$tipo,$id_empresa){
        // Calcular el nuevo stock (devolver la cantidad vendida)
        $co = (int)$codi + (int)$item->det_ven_cantidad;
        $pro = _14Producto::findOrFail($item->pro_codigo);
        
        // PROLIPA (empresa 1)
        if($id_empresa == 1){
            if($tipo == 1){
                // Factura → devolver a pro_stock
                $pro->pro_stock = $co;
            } else {
                // Nota → devolver a pro_deposito
                $pro->pro_deposito = $co;
            }
            $pro->save();
        }
        
        // CALMED (empresa 3)
        if($id_empresa == 3){
            if($tipo == 1){
                // Factura → devolver a pro_stockCalmed
                $pro->pro_stockCalmed = $co;
            } else {
                // Nota → devolver a pro_depositoCalmed
                $pro->pro_depositoCalmed = $co;
            }
            $pro->save();
        }
        
        // NO tocar pro_reservar - la reserva sigue vigente desde la proforma
        // La reserva solo se devuelve al anular la proforma completa
    }
    
    /**
     * Anular una venta
     * - Cambia el estado del documento a 3 (anulado)
     * - Retorna el stock al campo correspondiente (sin tocar pro_reservar)
     * - Actualiza el estado de la proforma según las ventas restantes:
     *   * Estado 6 (aprobado con pendiente): si hay más proformado que vendido
     *   * Estado 3 (aprobado): si no hay ventas activas
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function anularVenta(Request $request)
    {
        try {

            
            // Validar parámetros requeridos
            if (!$request->ven_codigo || !$request->empresa || !$request->tipodoc) {
                return response()->json([
                    "status" => "0", 
                    "message" => "Faltan parámetros requeridos: ven_codigo, empresa, tipodoc"
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Validar que exista la venta
            $venta = Ventas::where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->empresa)
                ->first();
                
            if (!$venta) {
                return response()->json([
                    "status" => "0", 
                    "message" => "El documento no existe en la base de datos"
                ], 404);
            }
            
            // Validar que el documento pueda ser anulado (solo estado 2 - Pendiente)
            if ($venta->est_ven_codigo != 2) {
                $estadoActual = '';
                switch ($venta->est_ven_codigo) {
                    case 1:
                        $estadoActual = 'despachado';
                        break;
                    case 3:
                        $estadoActual = 'anulado';
                        break;
                    default:
                        $estadoActual = 'en un estado que no permite anulación';
                        break;
                }
                
                return response()->json([
                    "status" => "0", 
                    "message" => "No se puede anular el documento porque está $estadoActual. Solo se pueden anular documentos en estado Pendiente (estado 2)"
                ], 400);
            }
            
            // Validar que no tenga devoluciones registradas
            $validateDevuelto = DB::SELECT("SELECT * FROM f_detalle_venta v
                WHERE v.ven_codigo = '$request->ven_codigo'
                AND v.det_ven_dev > 0
                AND v.id_empresa = '$request->empresa'
            ");
            
            if (count($validateDevuelto) > 0) {
                return response()->json([
                    "status" => "0", 
                    "message" => "El documento $request->ven_codigo tiene devoluciones registradas, no se puede anular"
                ], 400);
            }
            
            // Obtener los detalles de la venta para regresar el stock
            $detalleVenta = DB::SELECT("SELECT * FROM f_detalle_venta 
                WHERE ven_codigo = '$request->ven_codigo' 
                AND id_empresa = '$request->empresa'
            ");
            
            // Validar que existan detalles
            if (!$detalleVenta || count($detalleVenta) == 0) {
                return response()->json([
                    "status" => "0", 
                    "message" => "No se encontraron detalles para el documento $request->ven_codigo"
                ], 404);
            }
            
            // Actualizar el estado del documento a anulado (3)
            DB::table('f_venta')
                ->where('ven_codigo', $request->ven_codigo)
                ->where('id_empresa', $request->empresa)
                ->update([
                    'est_ven_codigo' => 3,
                    'user_anulado' => $request->id_usuario,
                    'observacionAnulacion' => $request->observacionAnulacion,
                    'fecha_anulacion' => now(),
                ]);
            
            // Regresar el stock de cada producto
            foreach ($detalleVenta as $item) {
                // Obtener el stock actual del campo correspondiente
                if ($request->empresa == 1) {
                    if ($request->tipodoc == 1) {
                        // PROLIPA Factura → pro_stock
                        $query1 = DB::SELECT("SELECT pro_stock as stoc FROM 1_4_cal_producto WHERE pro_codigo='$item->pro_codigo'");
                    } else {
                        // PROLIPA Nota → pro_deposito
                        $query1 = DB::SELECT("SELECT pro_deposito as stoc FROM 1_4_cal_producto WHERE pro_codigo='$item->pro_codigo'");
                    }
                }
                
                if ($request->empresa == 3) {
                    if ($request->tipodoc == 1) {
                        // CALMED Factura → pro_stockCalmed
                        $query1 = DB::SELECT("SELECT pro_stockCalmed as stoc FROM 1_4_cal_producto WHERE pro_codigo='$item->pro_codigo'");
                    } else {
                        // CALMED Nota → pro_depositoCalmed
                        $query1 = DB::SELECT("SELECT pro_depositoCalmed as stoc FROM 1_4_cal_producto WHERE pro_codigo='$item->pro_codigo'");
                    }
                }
                
                $codi = $query1[0]->stoc;
                
                // Regresar el stock sin tocar pro_reservar
                $this->regresarStock($codi, $item, $request->tipodoc, $request->empresa);
            }
            
            // Actualizar el estado de la proforma según las ventas restantes
            $proforma = Proforma::where('prof_id', $venta->ven_idproforma)
                ->where('emp_id', $venta->id_empresa)
                ->first();
            
            if ($proforma) {
                // Primero verificar si hay ALGUNA venta activa (no anulada)
                $ventasActivas = DB::SELECT("SELECT COUNT(*) as total
                    FROM f_venta v
                    WHERE v.ven_idproforma = '$proforma->prof_id'
                    AND v.id_empresa = '$proforma->emp_id'
                    AND v.est_ven_codigo <> 3
                ");
                
                $hayVentasActivas = ($ventasActivas[0]->total ?? 0) > 0;
                
                // Si no hay ventas activas, volver a estado Aprobado (3)
                if (!$hayVentasActivas) {
                    $proforma->prof_estado = 3;
                    $proforma->save();
                } else {
                    // Si hay ventas activas, verificar si está todo vendido o hay pendiente
                    $dataProforma = DB::SELECT("SELECT * FROM f_detalle_proforma dp
                        WHERE dp.prof_id = '$proforma->id'
                    ");
                    
                    $todoVendido = true;
                    
                    foreach ($dataProforma as $itemProforma) {
                        $cantidadProforma = $itemProforma->det_prof_cantidad;
                        
                        // Obtener la cantidad vendida (sin incluir anuladas - estado 3)
                        $getFacturada = DB::SELECT("SELECT COALESCE(SUM(d.det_ven_cantidad), 0) AS cantidadFacturada
                            FROM f_detalle_venta d
                            INNER JOIN f_venta v ON d.ven_codigo = v.ven_codigo AND v.id_empresa = d.id_empresa
                            WHERE d.idProforma = '$proforma->id'
                            AND d.pro_codigo = '$itemProforma->pro_codigo'
                            AND v.est_ven_codigo <> 3
                        ");
                        
                        $cantidadFacturada = (int)($getFacturada[0]->cantidadFacturada ?? 0);
                        
                        // Si aún hay cantidades pendientes de vender
                        if ($cantidadFacturada < $cantidadProforma) {
                            $todoVendido = false;
                            break; // Ya sabemos que no está todo vendido
                        }
                    }
                    
                    // Determinar el estado según si está todo vendido o no
                    if ($todoVendido) {
                        // Todo vendido → Estado 2 (Finalizada)
                        $proforma->prof_estado = 2;
                    } else {
                        // Hay ventas activas pero no todo vendido → Estado 6 (Aprobada con pendiente)
                        $proforma->prof_estado = 6;
                    }
                    
                    $proforma->save();
                }
            }
            
            DB::commit();
            
            $mensajeEstado = '';
            if (isset($proforma)) {
                switch ($proforma->prof_estado) {
                    case 2:
                        $mensajeEstado = ' - Proforma finalizada';
                        break;
                    case 6:
                        $mensajeEstado = ' - Proforma aprobada con pendiente';
                        break;
                    case 3:
                        $mensajeEstado = ' - Proforma aprobada';
                        break;
                }
            }
            
            return response()->json([
                'status' => '1',
                'message' => 'Anulación exitosa de la venta' . $mensajeEstado,
                'estado_proforma' => $proforma->prof_estado ?? null
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => "0", 
                "message" => "No se pudo anular la venta",
                "error" => $e->getMessage()
            ], 500);
        }
    }
    
}
