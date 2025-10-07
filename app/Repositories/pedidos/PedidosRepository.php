<?php
namespace App\Repositories\pedidos;

use App\Models\Pedidos;
use App\Repositories\BaseRepository;
use DB;
class  PedidosRepository extends BaseRepository
{
    public function __construct(Pedidos $pedidoRepository)
    {
        parent::__construct($pedidoRepository);
    }
    public function getPrecioXLibro($id_serie,$libro_idlibro,$area_idarea,$periodo,$year){
        $precio = 0;
        $query = [];
        if($id_serie == 6){
            $query = DB::SELECT("SELECT f.pvp AS precio
            FROM pedidos_formato f
            WHERE f.id_serie    = '6'
            AND f.id_area       = '69'
            AND f.id_libro      = '$libro_idlibro'
            AND f.id_periodo    = '$periodo'");
        }else{
            $query = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = ls.id_serie
                    AND f.id_area = a.area_idarea
                    AND f.id_periodo = '$periodo'
                )as precio
                FROM libros_series ls
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                WHERE ls.id_serie = '$id_serie'
                AND a.area_idarea  = '$area_idarea'
                AND l.Estado_idEstado = '1'
                AND a.estado = '1'
                AND ls.year = '$year'
                LIMIT 1
            ");
        }
        if(count($query) > 0){
            $precio = $query[0]->precio;
        }
        return $precio;
    }
    public function getLibroPedidosVal($plan_lector,$id_periodo,$id_serie,$id_area,$year){
        if($plan_lector > 0 ){
            $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,l.asignatura_idasignatura,
            (
                SELECT f.pvp AS precio
                FROM pedidos_formato f
                WHERE f.id_serie = '6'
                AND f.id_area = '69'
                AND f.id_libro = '$plan_lector'
                AND f.id_periodo = '$id_periodo'
            )as precio, ls.codigo_liquidacion,ls.version,ls.year
            FROM libro l
            left join libros_series ls  on ls.idLibro = l.idlibro
            WHERE l.idlibro = '$plan_lector'
            ");
            $valores = $getPlanlector;
        }else{
            $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,l.asignatura_idasignatura,
            (
                SELECT f.pvp AS precio
                FROM pedidos_formato f
                WHERE f.id_serie = ls.id_serie
                AND f.id_area = a.area_idarea
                AND f.id_periodo = '$id_periodo'
            )as precio
            FROM libros_series ls
            LEFT JOIN libro l ON ls.idLibro = l.idlibro
            LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
            WHERE ls.id_serie = '$id_serie'
            AND a.area_idarea  = '$id_area'
            AND l.Estado_idEstado = '1'
            AND a.estado = '1'
            AND ls.year = '$year'
            LIMIT 1
            ");
            $valores = $getLibros;
        }
        $datos[0] = (Object)[
            "idlibro"           => $valores[0]->idlibro,
            "nombrelibro"       => $valores[0]->nombrelibro,
            "precio"            => $valores[0]->precio,
            "idasignatura"      => $valores[0]->asignatura_idasignatura,
            "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
        ];
        return $datos;
    }
    public function getLibrosNormalesFormato($periodo){
        $series = DB::SELECT("SELECT * FROM series s WHERE s.id_serie != 6"); // omitir plan lector
        $datos  = [];
        foreach($series as $key => $value){
            $query = DB::SELECT("SELECT DISTINCT l.idlibro as libro_id , l.nombrelibro ,l.nombrelibro as nombre_libro ,pf.pvp AS precio,
            ls.codigo_liquidacion as codigo
            FROM area ar
            INNER JOIN asignatura a ON ar.idarea = a.area_idarea
            INNER JOIN libro l ON a.idasignatura = l.asignatura_idasignatura
            INNER JOIN libros_series ls ON l.idlibro = ls.idLibro
            INNER JOIN tipoareas t ON ar.tipoareas_idtipoarea = t.idtipoarea
            INNER JOIN pedidos_formato pf ON pf.id_area = ar.idarea
            WHERE ls.id_serie = ?
            AND pf.id_periodo = ?
            AND pf.id_serie = ?
            AND ar.estado = '1'
            AND a.estado = '1'
            AND pf.pvp <> 0
            AND l.Estado_idEstado = 1
            ORDER BY l.nombrelibro ASC", [$value->id_serie, $periodo, $value->id_serie]);
            $datos[$key] = [
                $query,
            ];
        }
        if(count($datos) == 0){
          return [];
        }
        $resultado = collect($datos)->flatten(10);
        return $resultado;
    }
    public function getLibrosPlanLectorFormato($periodo){
        $libros_plan = DB::SELECT("SELECT DISTINCT l.idlibro as libro_id , l.nombrelibro, l.nombrelibro as nombre_libro ,p.pvp AS precio,
        ls.codigo_liquidacion as codigo
        FROM libro l
        INNER JOIN libros_series ls ON l.idLibro = ls.idLibro
        LEFT JOIN pedidos_formato p ON l.idlibro = p.id_libro AND p.id_periodo = $periodo
        WHERE ls.id_serie = 6
        ORDER BY l.nombrelibro ASC");

        return $libros_plan;
    }
    public function obtenerLibroxPedidoTodo($pedido){
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*,
        p.descuento, p.id_periodo,
        p.anticipo, p.comision, CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
        se.nombre_serie
        FROM pedidos_val_area pv
        left join area ar ON  pv.id_area = ar.idarea
        left join series se ON pv.id_serie = se.id_serie
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        WHERE pv.id_pedido = '$pedido'
        GROUP BY pv.id;
        ");
        if(empty($val_pedido)){
            return $val_pedido;
        }
        $arreglo = [];
        $cont    = 0;
        //obtener solo los alcances activos
        foreach($val_pedido as $k => $tr){
            //Cuando es el pedido original
            $alcance_id = 0;
            $alcance_id = $tr->alcance;
            if($alcance_id == 0){
                $arreglo[$cont] =   (object)[
                    "id"                => $tr->id,
                    "id_pedido"         => $tr->id_pedido,
                    "valor"             => $tr->valor,
                    "id_area"           => $tr->id_area,
                    "tipo_val"          => $tr->tipo_val,
                    "id_serie"          => $tr->id_serie,
                    "year"              => $tr->year,
                    "plan_lector"       => $tr->plan_lector,
                    "alcance"           => $tr->alcance,
                    "descuento"         => $tr->descuento,
                    "id_periodo"        => $tr->id_periodo,
                    "anticipo"          => $tr->anticipo,
                    "comision"          => $tr->comision,
                    "serieArea"         => $tr->serieArea,
                    "nombre_serie"      => $tr->nombre_serie,
                    "alcance"           => $alcance_id
                ];
            }else{
                //validate que el alcance este cerrado o aprobado
                $query = $this->obtenerAlcanceAbiertoXId($alcance_id);
                if(count($query) > 0){
                    $arreglo[$cont] = (object) [
                        "id"                => $tr->id,
                        "id_pedido"         => $tr->id_pedido,
                        "valor"             => $tr->valor,
                        "id_area"           => $tr->id_area,
                        "tipo_val"          => $tr->tipo_val,
                        "id_serie"          => $tr->id_serie,
                        "year"              => $tr->year,
                        "plan_lector"       => $tr->plan_lector,
                        "alcance"           => $tr->alcance,
                        "descuento"         => $tr->descuento,
                        "id_periodo"        => $tr->id_periodo,
                        "anticipo"          => $tr->anticipo,
                        "comision"          => $tr->comision,
                        "serieArea"         => $tr->serieArea,
                        "nombre_serie"      => $tr->nombre_serie,
                        "alcance"           => $alcance_id
                    ];
                }
            }
            $cont++;
        }
        //mostrar el arreglo bien
        $renderSet = [];
        $renderSet = array_values($arreglo);
        if(count($renderSet) == 0){
            return $renderSet;
        }
        $datos = [];
        $contador = 0;
        //return $renderSet;
        foreach($renderSet as $key => $item){
            $valores = [];
            //plan lector
            if($item->plan_lector > 0 ){
                $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro, pro.pro_reservar,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = '6'
                    AND f.id_area = '69'
                    AND f.id_libro = '$item->plan_lector'
                    AND f.id_periodo = '$item->id_periodo'
                )as precio, ls.codigo_liquidacion,ls.version,ls.year
                FROM libro l
                left join libros_series ls  on ls.idLibro = l.idlibro
                inner join 1_4_cal_producto pro on ls.codigo_liquidacion=pro.pro_codigo
                WHERE l.idlibro = '$item->plan_lector'
                ");
                $valores = $getPlanlector;
            }else{
                $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro, pro.pro_reservar,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = ls.id_serie
                    AND f.id_area = a.area_idarea
                    AND f.id_periodo = '$item->id_periodo'
                )as precio
                FROM libros_series ls
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                inner join 1_4_cal_producto pro on ls.codigo_liquidacion=pro.pro_codigo
                WHERE ls.id_serie = '$item->id_serie'
                AND a.area_idarea  = '$item->id_area'
                AND l.Estado_idEstado = '1'
                AND a.estado = '1'
                AND ls.year = '$item->year'
                LIMIT 1
                ");
                $valores = $getLibros;
            }
            $datos[$contador] = (Object)[
                // "id"                => $item->id,
                "id_pedido"         => $item->id_pedido,
                "valor"             => $item->valor,
                "id_area"           => $item->id_area,
                "tipo_val"          => $item->tipo_val,
                "id_serie"          => $item->id_serie,
                "year"              => $item->year,
                "anio"              => $valores[0]->year,
                "version"           => $valores[0]->version,
                "descuento"         => $item->descuento,
                "anticipo"          => $item->anticipo,
                "comision"          => $item->comision,
                "plan_lector"       => $item->plan_lector,
                "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                "idlibro"           => $valores[0]->idlibro,
                "nombrelibro"       => $valores[0]->nombrelibro,
                "precio"            => $valores[0]->precio,
                "stock"             => $valores[0]->pro_reservar,
                // "subtotal"          => $item->valor * $valores[0]->precio,
                "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
                "alcance"           => $item->alcance,
            ];
            $contador++;
        }
        //si el codigo de liquidacion se repite sumar en el valor
        // Crear un array asociativo para agrupar por codigo_liquidacion
        $grouped = [];

        foreach ($datos as $item) {
            $codigo = $item->codigo_liquidacion;

            if (!isset($grouped[$codigo])) {
                $grouped[$codigo] = $item;
            } else {
                $grouped[$codigo]->valor += $item->valor;
            }
        }

        // Convertir el array asociativo de nuevo a un array indexado
        $result = array_values($grouped);
        //subtotal
        foreach($result as $key => $item){
            $result[$key]->subtotal = $item->valor * $item->precio;
        }
        return $result;
    }
    public function obtenerAlcanceAbiertoXId($id){
        $query = DB::SELECT("SELECT * FROM pedidos_alcance a
        WHERE a.id = '$id'
        AND a.estado_alcance = '1'");
        return $query;
    }
    public function stockReservaProducto($pro_codigo){
        $query = DB::SELECT("SELECT pro_codigo,pro_reservar FROM 1_4_cal_producto WHERE pro_codigo = '$pro_codigo' ");
        return $query;
    }

    //INICIO METODOS JEYSON
    public function getLibrosNormalesFormato_new($periodo){
        $series = DB::SELECT("SELECT * FROM series s WHERE s.id_serie != 6"); // omitir plan lector
        $datos  = [];
        foreach($series as $key => $value){
            $query = DB::SELECT("SELECT DISTINCT l.idlibro as libro_id , l.nombrelibro ,l.nombrelibro as nombre_libro ,pf.pfn_pvp AS precio,
            ls.codigo_liquidacion as codigo
            FROM area ar
            INNER JOIN asignatura a ON ar.idarea = a.area_idarea
            INNER JOIN libro l ON a.idasignatura = l.asignatura_idasignatura
            INNER JOIN libros_series ls ON l.idlibro = ls.idLibro
            INNER JOIN tipoareas t ON ar.tipoareas_idtipoarea = t.idtipoarea
            INNER JOIN pedidos_formato_new pf ON pf.idlibro = l.idlibro
            WHERE ls.id_serie = ?
            AND pf.idperiodoescolar = ?
            AND ar.estado = '1'
            AND a.estado = '1'
            AND pf.pfn_estado = 1
            AND l.Estado_idEstado = 1
            ORDER BY l.nombrelibro ASC", [$value->id_serie, $periodo]);
            $datos[$key] = [
                $query,
            ];
        }
        if(count($datos) == 0){
          return [];
        }
        $resultado = collect($datos)->flatten(10);
        return $resultado;
    }
    public function getLibrosPlanLectorFormato_new($periodo){
        $libros_plan = DB::SELECT("SELECT DISTINCT l.idlibro as libro_id , l.nombrelibro, l.nombrelibro as nombre_libro ,p.pfn_pvp AS precio,
        ls.codigo_liquidacion as codigo
        FROM libro l
        INNER JOIN libros_series ls ON l.idLibro = ls.idLibro
        LEFT JOIN pedidos_formato_new p ON l.idlibro = p.idlibro
        AND p.idperiodoescolar = $periodo
        WHERE ls.id_serie = 6
        AND p.pfn_estado = 1
        ORDER BY l.nombrelibro ASC;");

        return $libros_plan;
    }

    public function obtenerLibroxPedidoTodo_new($pedido){
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.pvn_id AS id, pv.id_pedido,
        pv.pvn_cantidad AS valor,
        CASE
            WHEN s.id_serie = 6 THEN l.idlibro
            ELSE ar.idarea
        END as id_area,
        s.id_serie,
        CASE
            WHEN s.id_serie = 6 THEN 0
            ELSE ls.year
        END as year,
        ls.year as anio,
        CASE
            WHEN s.id_serie = 6 THEN l.idlibro
            ELSE 0
        END as plan_lector,
        pv.pvn_tipo AS alcance, pv.created_at, pv.updated_at,
        CASE
            WHEN s.id_serie = 6 THEN NULL
            ELSE CONCAT(s.nombre_serie, ' ', ar.nombrearea)
        END as serieArea,
        l.idlibro, l.nombrelibro, p.descuento, p.id_periodo, p.anticipo, p.comision, s.nombre_serie,
        ls.version, asi.idasignatura, ls.codigo_liquidacion, l.descripcionlibro
        FROM pedidos_val_area_new pv
        LEFT JOIN libro l ON  pv.idlibro = l.idlibro
        LEFT JOIN libros_series ls ON pv.idlibro = ls.idLibro
        LEFT JOIN asignatura asi ON l.asignatura_idasignatura = asi.idasignatura
        LEFT JOIN area ar ON asi.area_idarea = ar.idarea
        LEFT JOIN series s ON ls.id_serie = s.id_serie
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        WHERE pv.id_pedido = '$pedido'
        GROUP BY pv.pvn_id, s.nombre_serie, ls.year, s.id_serie, ls.version, ls.codigo_liquidacion;
        ");
        if(empty($val_pedido)){
            return $val_pedido;
        }
        $arreglo = [];
        $cont    = 0;
        //obtener solo los alcances activos
        foreach($val_pedido as $k => $tr){
            //Cuando es el pedido original
            $alcance_id = 0;
            $alcance_id = $tr->alcance;
            if($alcance_id == 0){
                $arreglo[$cont] =   (object)[
                    "id"                => $tr->id,
                    "id_pedido"         => $tr->id_pedido,
                    "valor"             => $tr->valor,
                    "id_area"           => $tr->id_area,
                    "id_serie"          => $tr->id_serie,
                    "year"              => $tr->year,
                    "plan_lector"       => $tr->plan_lector,
                    "alcance"           => $tr->alcance,
                    "descuento"         => $tr->descuento,
                    "id_periodo"        => $tr->id_periodo,
                    "anticipo"          => $tr->anticipo,
                    "comision"          => $tr->comision,
                    "serieArea"         => $tr->serieArea,
                    "nombre_serie"      => $tr->nombre_serie,
                    "version"           => $tr->version,
                    "idlibro"           => $tr->idlibro,
                    "nombrelibro"       => $tr->nombrelibro,
                    "codigo_liquidacion"=> $tr->codigo_liquidacion,
                    "anio"              => $tr->anio,
                    "alcance"           => $alcance_id
                ];
            }else{
                //validate que el alcance este cerrado o aprobado
                $query = $this->obtenerAlcanceAbiertoXId($alcance_id);
                if(count($query) > 0){
                    $arreglo[$cont] = (object) [
                        "id"                => $tr->id,
                        "id_pedido"         => $tr->id_pedido,
                        "valor"             => $tr->valor,
                        "id_area"           => $tr->id_area,
                        "id_serie"          => $tr->id_serie,
                        "year"              => $tr->year,
                        "plan_lector"       => $tr->plan_lector,
                        "alcance"           => $tr->alcance,
                        "descuento"         => $tr->descuento,
                        "id_periodo"        => $tr->id_periodo,
                        "anticipo"          => $tr->anticipo,
                        "comision"          => $tr->comision,
                        "serieArea"         => $tr->serieArea,
                        "nombre_serie"      => $tr->nombre_serie,
                        "version"           => $tr->version,
                        "idlibro"           => $tr->idlibro,
                        "nombrelibro"       => $tr->nombrelibro,
                        "codigo_liquidacion"=> $tr->codigo_liquidacion,
                        "anio"              => $tr->anio,
                        "alcance"           => $alcance_id
                    ];
                }
            }
            $cont++;
        }
        //mostrar el arreglo bien
        $renderSet = [];
        $renderSet = array_values($arreglo);
        if(count($renderSet) == 0){
            return $renderSet;
        }
        $datos = [];
        $contador = 0;
        //return $renderSet;
        foreach($renderSet as $item){
            $pfn_pvp_result = (float) DB::table('pedidos_formato_new')
            ->where('idperiodoescolar', $item->id_periodo)
            ->where('idlibro', $item->idlibro)
            ->value('pfn_pvp');

            // Obtener los valores de pro_stock y pro_deposito
            $stock_producto = DB::table('1_4_cal_producto')
            ->where('pro_codigo', $item->codigo_liquidacion)
            ->select('pro_reservar')
            ->first();
            $datos[$contador] = (Object)[
                // "id"                => $item->id,
                "id_pedido"         => $item->id_pedido,
                "valor"             => $item->valor,
                "id_area"           => $item->id_area,
                "id_serie"          => $item->id_serie,
                "year"              => $item->year,
                "anio"              => $item->anio,
                "version"           => $item->version,
                "descuento"         => $item->descuento,
                "anticipo"          => $item->anticipo,
                "comision"          => $item->comision,
                "plan_lector"       => $item->plan_lector,
                "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$item->nombrelibro : $item->serieArea,
                "idlibro"           => $item->idlibro,
                "nombrelibro"       => $item->nombrelibro,
                "precio"            => $pfn_pvp_result,
                "stock"             => $stock_producto->pro_reservar,
                // "subtotal"          => $item->valor * $valores[0]->precio,
                "codigo_liquidacion"=> $item->codigo_liquidacion,
                "alcance"           => $item->alcance,
            ];
            $contador++;
        }
        //si el codigo de liquidacion se repite sumar en el valor
        // Crear un array asociativo para agrupar por codigo_liquidacion
        $grouped = [];

        foreach ($datos as $item) {
            $codigo = $item->codigo_liquidacion;

            if (!isset($grouped[$codigo])) {
                $grouped[$codigo] = $item;
            } else {
                $grouped[$codigo]->valor += $item->valor;
            }
        }

        // Convertir el array asociativo de nuevo a un array indexado
        $result = array_values($grouped);
        //subtotal
        foreach($result as $key => $item){
            $result[$key]->subtotal = $item->valor * $item->precio;
        }
        return $result;
    }

    public function getPrecioXLibro_new($id_serie, $libro_idlibro, $area_idarea, $periodo, $year) {
        $precio = 0;

        // Obtén el valor del campo `pfn_pvp`
        $pfn_pvp_result = DB::table('pedidos_formato_new')
            ->where('idperiodoescolar', $periodo)
            ->where('idlibro', $libro_idlibro)
            ->value('pfn_pvp');

        // Verifica si se obtuvo un resultado
        if (!is_null($pfn_pvp_result)) {
            $precio = (float) $pfn_pvp_result;
        }

        return $precio;
    }
    //FIN METODO JEYSON
    public function ReportePedidoGeneral($periodo, $agrupado = 0){
         $query = DB::SELECT("SELECT
            p.id_asesor,
            p.id_institucion,
            p.tipo_venta_descr,
            p.id_pedido,
            CONCAT(u.nombres,' ', u.apellidos) AS asesor,
            i.nombreInstitucion,
            CASE
                WHEN i.punto_venta = 1 THEN 'SI'
                ELSE 'NO'
            END AS punto_venta,
            p.contrato_generado,
            p.TotalVentaReal AS ventaBrutaLiquidado,
            ROUND(p.TotalVentaReal - ((p.TotalVentaReal * p.descuento)/100), 2) AS ven_neta_liquidado,
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
            ), 0)) AS ventaBrutaPedido,
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
            ORDER BY i.nombreInstitucion ASC
        ");

        foreach ($query as $q) {
            // Calcular porcentaje de comisión
            $q->valorComisionPedido = round(($q->ventaBrutaPedido * $q->comision) / 100, 2);
            // Calcular venta neta (ventaBruta - porcentaje descuento)
            $q->ven_netaPedido = round($q->ventaBrutaPedido - (($q->ventaBrutaPedido * $q->comision) / 100), 2);

            // Crear propiedad totalPagosConvenio
            $q->totalPagosConvenio = $q->totalPagos + $q->totalConvenioAprobado;

            // Agregamos la propiedad calculada sin modificar la consulta SQL
            $q->xPagarPedido = $q->valorComisionPedido - $q->totalPagosConvenio;

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
        if($agrupado == 1){
             $grouped = collect($query)
            ->groupBy('asesor')
            ->map(function ($rows) {
                return [
                    'id_asesor'           => $rows->first()->id_asesor,
                    'asesor'              => $rows->first()->asesor,
                    'ventaBrutaLiquidado' => $rows->sum('ventaBrutaLiquidado'),
                    'ven_neta_liquidado'  => $rows->sum('ven_neta_liquidado'),
                    'ventaPedido'         => $rows->sum('ventaPedido'),
                    'ventaAlcance'        => $rows->sum('ventaAlcance'),
                    'totalPagos'          => $rows->sum('totalPagos'),
                    'totalConvenioAprobado' => $rows->sum('totalConvenioAprobado'),
                    'valorComisionPedido' => $rows->sum('valorComisionPedido'),
                    'ventaBrutaPedido'    => $rows->sum('ventaBrutaPedido'),
                    'ven_netaPedido'      => $rows->sum('ven_netaPedido'),
                    'totalPagosConvenio'  => $rows->sum('totalPagosConvenio'),
                    'xPagarPedido'        => $rows->sum('xPagarPedido'),
                    'if_distribuidor'     => $rows->max('if_distribuidor'),
                    'EsDistribuidor'      => $rows->max('if_distribuidor') == 1 ? 'SI' : 'NO',
                ];
            })
            ->values();
            return $grouped;
        }
        return $query;
    }


    public function pedidosNuevoXLibroSinAlcances($periodo){
         // --- pedidos ---
        $getPedidos = DB::SELECT("SELECT
                ls.codigo_liquidacion,
                l.nombrelibro,
                s.nombre_serie,
                SUM(pp.pvn_cantidad) AS total_cantidad,
                fp.pfn_pvp AS precio,
                (SUM(pp.pvn_cantidad) * fp.pfn_pvp) AS pedido
            FROM pedidos_val_area_new pp
            LEFT JOIN pedidos p ON p.id_pedido = pp.id_pedido
            LEFT JOIN pedidos_formato_new fp ON fp.idlibro = pp.idlibro AND fp.idperiodoescolar = '$periodo'
            LEFT JOIN libro l ON l.idlibro = pp.id_libro
            LEFT JOIN libros_series ls ON ls.idLibro = l.idlibro
            LEFT JOIN series s ON s.id_serie = ls.id_serie
            WHERE p.contrato_generado IS NOT NULL
            AND p.estado = '1'
            AND p.tipo = '0'
            AND p.id_periodo = '$periodo'
            AND pp.pvn_tipo = '0'
            GROUP BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie, fp.pfn_pvp
            ORDER BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie
        ");
        return $getPedidos;
    }

    public function pedidosAnteriorXLibroSinAlcances($periodo){
         // --- pedidos ---
        $getPedidos = DB::SELECT("SELECT
                ls.idLibro,
                ls.codigo_liquidacion,
                l.nombrelibro,
                s.nombre_serie,
                SUM(pp.valor) AS total_cantidad,
                fp.pfn_pvp AS precio,
                (SUM(pp.valor) * fp.pfn_pvp) AS pedido
            FROM pedidos_val_area pp
            LEFT JOIN pedidos p ON p.id_pedido = pp.id_pedido
            LEFT JOIN pedidos_formato_new fp ON fp.idlibro = pp.id_libro AND fp.idperiodoescolar = '$periodo'
            LEFT JOIN libro l ON l.idlibro = pp.id_libro
            LEFT JOIN libros_series ls ON ls.idLibro = l.idlibro
            LEFT JOIN series s ON s.id_serie = ls.id_serie
            WHERE p.contrato_generado IS NOT NULL
            AND p.estado = '1'
            AND p.tipo = '0'
            AND p.id_periodo = '$periodo'
            AND pp.alcance = '0'
            GROUP BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie, fp.pfn_pvp, ls.idLibro
            ORDER BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie
        ");
        return $getPedidos;
    }

    public function alcancesNuevoXlibros($periodo){
        $getAlcances = DB::SELECT("SELECT
                ls.codigo_liquidacion,
                l.nombrelibro,
                s.nombre_serie,
                SUM(pp.pvn_cantidad) AS total_cantidad,
                fp.pfn_pvp AS precio,
                SUM(pp.pvn_cantidad * fp.pfn_pvp) AS pedido
            FROM pedidos_val_area_new pp
            INNER JOIN pedidos_alcance a ON a.id = pp.pvn_tipo
            LEFT JOIN pedidos p ON p.id_pedido = pp.id_pedido
            LEFT JOIN pedidos_formato_new fp ON fp.idlibro = pp.idlibro AND fp.idperiodoescolar = '$periodo'
            LEFT JOIN libro l ON l.idlibro = pp.id_libro
            LEFT JOIN libros_series ls ON ls.idLibro = l.idlibro
            LEFT JOIN series s ON s.id_serie = ls.id_serie
            WHERE p.contrato_generado IS NOT NULL
            AND p.estado = '1'
            AND p.tipo = '0'
            AND p.id_periodo = '$periodo'
            AND pp.pvn_tipo <> '0'
            AND a.estado_alcance = '1'
            GROUP BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie, fp.pfn_pvp
            ORDER BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie
        ");
        return $getAlcances;
    }

    public function alcancesAnteriorXlibros($periodo){
        $getAlcances = DB::SELECT("SELECT
                ls.codigo_liquidacion,
                l.nombrelibro,
                s.nombre_serie,
                SUM(pp.valor) AS total_cantidad,
                fp.pfn_pvp AS precio,
                SUM(pp.valor * fp.pfn_pvp) AS pedido
            FROM pedidos_val_area pp
            INNER JOIN pedidos_alcance a ON a.id = pp.alcance
            LEFT JOIN pedidos p ON p.id_pedido = pp.id_pedido
            LEFT JOIN pedidos_formato_new fp ON fp.idlibro = pp.id_libro AND fp.idperiodoescolar = '$periodo'
            LEFT JOIN libro l ON l.idlibro = pp.id_libro
            LEFT JOIN libros_series ls ON ls.idLibro = l.idlibro
            LEFT JOIN series s ON s.id_serie = ls.id_serie
            WHERE p.contrato_generado IS NOT NULL
            AND p.estado = '1'
            AND p.tipo = '0'
            AND p.id_periodo = '$periodo'
            AND pp.alcance <> '0'
            AND a.estado_alcance = '1'
            GROUP BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie, fp.pfn_pvp
            ORDER BY ls.codigo_liquidacion, l.nombrelibro, s.nombre_serie
        ");
        return $getAlcances;
    }
}
