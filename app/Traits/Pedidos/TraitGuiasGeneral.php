<?php

namespace App\Traits\Pedidos;

use App\Models\Pedidos;
use DB;
use Illuminate\Support\Facades\Http;
trait TraitGuiasGeneral
{
    public function tr_obtenerSecuenciaGuia($id){
        $secuencia = DB::SELECT("SELECT  * FROM f_tipo_documento d
        WHERE d.tdo_id = ?",[$id]);
        return $secuencia;
    }
    public function tr_guiasXEstado($estado_entrega){
        $query = DB::SELECT("SELECT p.id_pedido,p.ven_codigo,p.fecha_entrega_bodega
        FROM pedidos p
        WHERE p.tipo = '1'
        AND p.estado= '1'
        AND p.estado_entrega = ?
        ",[$estado_entrega]);
        return $query;
    }
    public function tr_pedidoxLibro($request){
        $query = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro
        FROM libros_series ls
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
        WHERE ls.id_serie = '$request->id_serie'
        AND a.area_idarea  = '$request->id_area'
        AND l.Estado_idEstado = '1'
        AND a.estado = '1'
        AND ls.year = '$request->libro'
        LIMIT 1
       ");
       return $query;
    }
    public function tr_pedidoxLibroPlanLector($request){
        $query = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro
        FROM libros_series ls
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
        WHERE ls.id_serie = '6'
        AND l.Estado_idEstado = '1'
        AND a.estado = '1'
        AND l.idlibro = '$request->plan_lector'
        LIMIT 1
        ");
        return $query;
    }

    public function tr_cantidadDevueltaGuias($asesor_id, $pro_codigo, $periodo_id) {
        $query = DB::SELECT("SELECT
            -- Cantidades PENDIENTES (estado 0)
            SUM(CASE WHEN d.estado = 0 THEN gd.cantidad_devuelta ELSE 0 END) AS cantidad_devuelta_pedidos_pendiente,
            SUM(CASE WHEN d.estado = 0 THEN gd.cantidad_devuelta_codigoslibros ELSE 0 END) AS cantidad_devuelta_codigoslibros_pendiente,

            -- Cantidades APROBADAS (estado 1)
            SUM(CASE WHEN d.estado = 1 THEN gd.cantidad_devuelta ELSE 0 END) AS cantidad_devuelta_pedidos_aprobada,
            SUM(CASE WHEN d.estado = 1 THEN gd.cantidad_devuelta_codigoslibros ELSE 0 END) AS cantidad_devuelta_codigoslibros_aprobada

        FROM pedidos_guias_devolucion_detalle gd
        LEFT JOIN pedidos_guias_devolucion d
            ON d.id = gd.pedidos_guias_devolucion_id
        WHERE gd.asesor_id = '$asesor_id'
        AND gd.periodo_id = '$periodo_id'
        AND gd.pro_codigo = '$pro_codigo';

        ");
        foreach ($query as $key => $value) {
            //devuelto_pedidos_codigoslibros_total
            $value->cantidad_devuelta_total                          = $value->cantidad_devuelta_pedidos_aprobada + $value->cantidad_devuelta_codigoslibros_aprobada;
            //devuelto_pedidos_total
            $value->devuelto_pedidos_total_aprobada                  = $value->cantidad_devuelta_pedidos_aprobada;
            //devuelto_codigoslibros_total
            $value->devuelto_codigoslibros_total_aprobada            = $value->cantidad_devuelta_codigoslibros_aprobada;
            //devuelto_total_pendiente
            $value->devuelto_total_pendiente                         = $value->cantidad_devuelta_pedidos_pendiente + $value->cantidad_devuelta_codigoslibros_pendiente;
        }
        return $query;
    }
}
