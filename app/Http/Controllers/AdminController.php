<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\tipoJuegos;
use App\Models\J_juegos;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\_14Producto;
use App\Models\CodigosLibros;
use App\Models\CodigosLibrosDevolucionHeader;
use App\Models\CodigosLibrosDevolucionSon;
use App\Models\CuotasPorCobrar;
use App\Models\EstudianteMatriculado;
use App\Models\HistoricoCodigos;
use App\Models\Institucion;
use App\Models\Libro;
use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use App\Models\Models\Verificacion\VerificacionDescuento;
use App\Models\Models\Verificacion\VerificacionDescuentoDetalle;
use App\Models\PedidoAlcance;
use App\Models\PedidoAlcanceHistorico;
use App\Models\PedidoConvenio;
use App\Models\PedidoDocumentoDocente;
use App\Models\Pedidos;
use App\Models\RepresentanteEconomico;
use App\Models\RepresentanteLegal;
use App\Models\SallePreguntasEvaluacion;
use App\Models\SeminarioCapacitador;
use App\Models\Temporada;
use App\Models\User;
use App\Models\Usuario;
use App\Models\Verificacion;
use App\Models\Video;
use App\Repositories\Codigos\CodigosRepository;
use App\Repositories\Facturacion\DevolucionRepository;
use App\Repositories\pedidos\PedidosRepository;
use DB;
use GraphQL\Server\RequestError;
use Mail;
use Illuminate\Support\Facades\Http;
use stdClass;
use App\Traits\Pedidos\TraitPedidosGeneral;
use App\Traits\Codigos\TraitCodigosGeneral;
use App\Traits\Verificacion\TraitVerificacionGeneral;
use PDO;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AdminController extends Controller
{
    use TraitPedidosGeneral;
    use TraitCodigosGeneral;
    use TraitVerificacionGeneral;
    protected $devolucionRepository;
    private $codigosRepository;
    protected $pedidoRepository;
    public function __construct( DevolucionRepository  $devolucionRepository,CodigosRepository $codigosRepository, PedidosRepository $pedidoRepository)
    {

        $this->devolucionRepository  = $devolucionRepository;
        $this->codigosRepository     = $codigosRepository;
        $this->pedidoRepository      = $pedidoRepository;
    }
    public function groqProxy(Request $request)
    {
        $apiKey = "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"; // ponla en .env
        $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json'
                ])
                ->post($apiUrl, [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => $request->input('messages'),
                    'temperature' => 0.7,
                    'max_tokens' => 1024
                ]);

            if ($response->status() === 429) {
                return response()->json([
                    'choices' => [[
                        'message' => [
                            'content' => '⏳ El servicio está muy ocupado. Intenta nuevamente en unos segundos.'
                        ]
                    ]]
                ]);
            }

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error conectando con Groq: ' . $e->getMessage()
            ], 500);
        }
    }

    // public function proxy(Request $request)
    // {
    //     $apiKey = 'AIzaSyDxG7aeJOuw8GpHnlvRu1wv8FKca-aO6qI';
    //     $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=' . $apiKey;

    //     try {
    //         $response = Http::timeout(30)
    //             ->withHeaders(['Content-Type' => 'application/json'])
    //             ->post($apiUrl, $request->all());

    //         // Si hay error de cuota, devolver mensaje amigable
    //         if ($response->status() == 429) {
    //             return response()->json([
    //                 'candidates' => [[
    //                     'content' => [
    //                         'parts' => [[
    //                             'text' => '⏳ El servicio está ocupado. Por favor, intenta de nuevo en unos segundos.'
    //                         ]]
    //                     ]
    //                 ]]
    //             ]);
    //         }

    //         return response()->json($response->json(), $response->status());

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error' => 'Error de conexión con Gemini: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function listModels()
    {
        $apiKey = 'AIzaSyDxG7aeJOuw8GpHnlvRu1wv8FKca-aO6qI';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

        $response = Http::get($url);
        return response()->json($response->json());
    }
    public function getFilesTest(){
      $query = DB::SELECT("SELECT pd.*,c.convenio_anios
      FROM pedidos_convenios_detalle pd
      LEFT JOIN pedidos_convenios c on  pd.pedido_convenio_institucion = c.id
      where pd.id_pedido > 1
      and pd.estado = '1'
      ");
        foreach($query as $key => $item){
            DB::table('pedidos')
            ->where('id_pedido',$item->id_pedido)
            ->update(["convenio_anios" => $item->convenio_anios,"pedidos_convenios_id" => $item->pedido_convenio_institucion]);
        }
        return "se guardo correctamente";
    }

    // public function datoEscuela(Request $request){
    //      set_time_limit(6000);
    //     ini_set('max_execution_time', 6000);
    //    $buscarUsuario = DB::SELECT("SELECT codl.idusuario

    //    FROM codigoslibros AS codl, usuario AS u, institucion AS its
    //    WHERE its.idInstitucion = 424
    //    AND its.idInstitucion = u.institucion_idInstitucion
    //    AND codl.idusuario = u.idusuario
    //    AND u.cedula <> '000000016'
    //     ORDER BY codl.idusuario DESC
    //    LIMIT 10
    //    ");



    //     $data  = [];
    //     $datos = [];
    //     $libros=[];
    //    foreach($buscarUsuario as $key => $item){
    //         $buscarLibros = DB::SELECT("SELECT  * FROM codigoslibros
    //         WHERE idusuario  = '$item->idusuario'
    //         ORDER BY updated_at DESC
    //         ");

    //         foreach($buscarLibros  as $l => $tr){

    //             $libros[$l] = [
    //                 "codigo" => $tr->codigo
    //             ];


    //             $data[$key] =[
    //                 "usuario" => $item->idusuario,
    //                 "libros" => $libros
    //             ];
    //         }


    //    }
    //    $datos = [
    //        "informacion" => $data
    //    ];
    //    return $datos;
    // }
    public function index()
    {
        $usuarios = DB::select("CALL `prolipa` ();");
        return $usuarios;
    }
    function filtrarPorEdad($persona) {
        return $persona["edad"] == 30;
    }
    // public function pruebaApi(Request $request){
    //     try {
    //         set_time_limit(6000000);
    //         ini_set('max_execution_time', 6000000);
    //         $datos=[];
    //         //anterior
    //         $periodo                = $request->periodo_idUno;
    //         //despues
    //         $periodo2               = $request->periodo_idDos;
    //         $codigosContrato        = $request->codigoC;
    //         $codigoContratoComparar = $request->codigoC2;
    //         if($codigosContrato == null || $codigoContratoComparar == null){ return ["status" => "0", "message" => "No hay codigo de periodo"]; }
    //         //obtener los vendedores que tienen pedidos
    //         $query = DB::SELECT("SELECT DISTINCT p.id_asesor ,
    //         CONCAT(u.nombres, ' ', u.apellidos) AS asesor, u.cedula,u.iniciales
    //         FROM pedidos p
    //         LEFT JOIN usuario u ON p.id_asesor = u.idusuario
    //         WHERE p.id_asesor <> '68750'
    //         AND p.id_asesor <> '6698'
    //         AND u.id_group = '11'
    //         ");
    //         $datos = [];
    //         foreach($query as $keyP => $itemP){
    //             //asesores
    //             $teran = ["OT","OAT"];
    //             $galo  = ["EZ","EZP"];
    //             //VARIABLES
    //             $iniciales  = $itemP->iniciales;
    //             //ASESORES QUE TIENE MAS DE UNA INICIAL
    //             $valores            = [];
    //             $valores2           = [];
    //             $arrayAsesor        = [];
    //             $JsonDespues        = [];
    //             $JsonAntes          = [];
    //             $contratosDespues   = [];
    //             $arraySinContrato   = [];
    //             $ventaBrutaActual   = 0;
    //             $ven_neta_actual    = 0;
    //             //==========CONTRATOS===================
    //             if($iniciales == 'OT' || $iniciales == 'EZ'){
    //                 if($iniciales == 'OT') $arrayAsesor = $teran;
    //                 if($iniciales == 'EZ') $arrayAsesor = $galo;
    //                 foreach($arrayAsesor as $key => $item){
    //                     //PERIODO DESPUES
    //                     $json      = $this->getContratos($itemP->id_asesor,$item,$periodo2,$codigoContratoComparar);
    //                     //PERIODO ANTES
    //                     $json2      = $this->getContratos($itemP->id_asesor,$item,$periodo,$codigosContrato);
    //                     $valores[$key]  = $json;
    //                     $valores2[$key] = $json2;
    //                 }
    //                 // return array_values($valores2);
    //                 $JsonDespues         =  array_merge($valores[0], $valores[1]);
    //                 $JsonAntes           =  array_merge($valores2[0], $valores2[1]);
    //             }else{
    //                  //PERIODO DESPUES
    //                 $JsonDespues        = $this->getContratos($itemP->id_asesor,$iniciales,$periodo2,$codigoContratoComparar);
    //                  //PERIODO ANTES
    //                 $JsonAntes          = $this->getContratos($itemP->id_asesor,$iniciales,$periodo,$codigosContrato);
    //             }
    //             //quitar duplicar de JsonDespues y $JsonAntes
    //             $JsonDespues = array_values(array_unique($JsonDespues, SORT_REGULAR));
    //             $JsonAntes   = array_values(array_unique($JsonAntes, SORT_REGULAR));
    //             //==========SIN CONTRATOS===================
    //             $getSinContrato = $this->getSinContratoProlipa($itemP->id_asesor,$periodo);
    //             if(empty($getSinContrato)){
    //                 $ventaBrutaActual = 0;
    //                 $ven_neta_actual  = 0;
    //             }else{
    //                 $ventaBrutaActual = $getSinContrato[0]->ventaBrutaActual;
    //                 $ven_neta_actual  = $getSinContrato[0]->ven_neta_actual;
    //             }
    //             $arraySinContrato[0] = [
    //                 "ventaBrutaActual"      => $ventaBrutaActual == null ? '0' :$ventaBrutaActual,
    //                 "ven_neta_actual"       => $ven_neta_actual  == null ? '0' :$ven_neta_actual,
    //             ];
    //             //SEND ARRAY
    //             $contratosDespues = [
    //                 "contratos"             => $JsonDespues,
    //                 "sin_contratos"         => $arraySinContrato
    //             ];
    //             $datos[$keyP] = [
    //                 "id_asesor"             => $itemP->id_asesor,
    //                 "asesor"                => $itemP->asesor,
    //                 "iniciales"             => $itemP->iniciales,
    //                 "cedula"                => $itemP->cedula,
    //                 "ContratosDespues"      => $contratosDespues,
    //                 "ContratosAnterior"     => $JsonAntes,
    //              ];
    //         }//FIN FOR EACH ASESORES
    //         // TRAER REGALADOS
    //         $regaladosAnterior = DB::SELECT("SELECT
    //                 CONCAT(u.nombres, ' ', u.apellidos) AS asesor,  -- Nombre completo del asesor
    //                 p.id_asesor,  -- ID del asesor
    //                 -- Con documentos
    //                 SUM(CASE WHEN c.codigo_proforma IS NOT NULL THEN 1 ELSE 0 END) AS cantidad_con_documentos,
    //                 SUM(CASE
    //                         WHEN v.ven_desc_por = '100' THEN 0
    //                         ELSE pr.pfn_pvp * (1 - (CAST(v.ven_desc_por AS DECIMAL(5,2)) / 100))
    //                     END) AS valor_total_con_descuento,
    //                 -- Sin documentos
    //                 SUM(CASE WHEN c.codigo_proforma IS NULL THEN 1 ELSE 0 END) AS cantidad_sin_documentos,
    //                 0 AS valor_sin_documentos  -- Valor para 'sin documentos' es 0
    //             FROM
    //                 codigoslibros c
    //             LEFT JOIN
    //                 pedidos p ON p.contrato_generado = c.contrato
    //             LEFT JOIN
    //                 pedidos_formato_new pr ON pr.idlibro = c.libro_idlibro
    //             LEFT JOIN
    //                 f_venta v ON v.ven_codigo = c.codigo_proforma AND v.id_empresa = c.proforma_empresa
    //             LEFT JOIN
    //                 libros_series ls ON ls.idLibro = c.libro_idlibro
    //             LEFT JOIN
    //                 usuario u ON u.idusuario = p.id_asesor  -- Relación con usuario para obtener el nombre completo
    //             WHERE
    //                 c.contrato IS NOT NULL
    //                 AND c.contrato != ''
    //                 AND c.contrato != '0'
    //                 AND c.prueba_diagnostica = '0'
    //                 AND c.bc_periodo = '$periodo'
    //                 AND c.estado_liquidacion = '2'
    //                 AND pr.idperiodoescolar = '$periodo'
    //             GROUP BY
    //                 u.nombres, u.apellidos, p.id_asesor;  -- Agrupamos por asesor (nombres, apellidos, id_asesor)
    //             ");
    //         // agregar como regalados donde el id_asesor sea igual
    //         foreach($datos as $key => $item){
    //             foreach($regaladosAnterior as $k => $tr){
    //                 if($item['id_asesor'] == $tr->id_asesor){
    //                     $datos[$key]['regaladosAnterior'] = [
    //                         "cantidad_con_documentos"   => $tr->cantidad_con_documentos,
    //                         "valor_total_con_descuento" => $tr->valor_total_con_descuento,
    //                         "cantidad_sin_documentos"   => $tr->cantidad_sin_documentos,
    //                         "valor_sin_documentos"      => $tr->valor_sin_documentos,
    //                     ];
    //                 }
    //             }
    //             if(!isset($datos[$key]['regaladosAnterior'])){
    //                 $datos[$key]['regaladosAnterior'] = [
    //                     "cantidad_con_documentos"   => 0,
    //                     "valor_total_con_descuento" => 0,
    //                     "cantidad_sin_documentos"   => 0,
    //                     "valor_sin_documentos"      => 0,
    //                 ];
    //             }
    //         }
    //         return $datos;
    //         } catch (\Exception  $ex) {
    //         return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex->getMessage()];
    //     }
    // }

    //api:get pruebatest?periodo_idUno=25&periodo_idDos=25&codigoC=S24&codigoC2=S24&ifRegalados=0
    public function pruebaApi(Request $request){
        try {
            set_time_limit(6000000);
            ini_set('max_execution_time', 6000000);


            $datos = [];

            // Periodos
            $periodo = $request->periodo_idUno;
            $periodo2 = $request->periodo_idDos;
            $codigosContrato = $request->codigoC;
            $codigoContratoComparar = $request->codigoC2;
            $ifRegalados = $request->input('ifRegalados','1'); //0 = No traer regalados, 1 = traer regalados

            // Validación de parámetros
            if ($codigosContrato == null || $codigoContratoComparar == null) {
                return ["status" => "0", "message" => "No hay código de periodo"];
            }

            // Obtener los vendedores que tienen pedidos
            $query = DB::SELECT("SELECT DISTINCT p.id_asesor,
                CONCAT(u.nombres, ' ', u.apellidos) AS asesor, u.cedula, u.iniciales
                FROM pedidos p
                LEFT JOIN usuario u ON p.id_asesor = u.idusuario
                WHERE p.id_asesor <> '68750'
                -- AND p.id_asesor <> '6698'
                AND u.id_group = '11'
            ");

            // Procesar cada asesor
            foreach ($query as $keyP => $itemP) {
                $teran = ["OT", "OAT"];
                $galo = ["EZ", "EZP"];
                $iniciales = $itemP->iniciales;
                $arrayAsesor = [];
                $JsonDespues = [];
                $JsonAntes = [];

                if ($iniciales == 'OT' || $iniciales == 'EZ') {
                    if ($iniciales == 'OT') $arrayAsesor = $teran;
                    if ($iniciales == 'EZ') $arrayAsesor = $galo;

                    foreach ($arrayAsesor as $key => $item) {
                        // $JsonDespues[] = $this->getContratos($itemP->id_asesor, $item, $periodo2, $codigoContratoComparar);
                        // $JsonAntes[] = $this->getContratos($itemP->id_asesor, $item, $periodo, $codigosContrato);
                        $JsonDespues = array_merge($JsonDespues, $this->getContratos($itemP->id_asesor, $item, $periodo2, $codigoContratoComparar));
                        $JsonAntes   = array_merge($JsonAntes,   $this->getContratos($itemP->id_asesor, $item, $periodo, $codigosContrato));

                    }
                } else {
                    $JsonDespues = $this->getContratos($itemP->id_asesor, $iniciales, $periodo2, $codigoContratoComparar);
                    $JsonAntes = $this->getContratos($itemP->id_asesor, $iniciales, $periodo, $codigosContrato);
                }

                // Eliminar duplicados
                $JsonDespues = array_values(array_unique($JsonDespues, SORT_REGULAR));
                $JsonAntes = array_values(array_unique($JsonAntes, SORT_REGULAR));

                // Obtener datos de sin contratos
                $getSinContrato = $this->getSinContratoProlipa($itemP->id_asesor, $periodo);
                $ventaBrutaActual = $getSinContrato ? $getSinContrato[0]->ventaBrutaActual : 0;
                $ven_neta_actual = $getSinContrato ? $getSinContrato[0]->ven_neta_actual : 0;

                $arraySinContrato[0] = [
                    "ventaBrutaActual" => $ventaBrutaActual,
                    "ven_neta_actual" => $ven_neta_actual,
                ];

                $contratosDespues = [
                    "contratos" => $JsonDespues,
                    "sin_contratos" => $arraySinContrato
                ];

                // Agregar asesor a los datos
                $datos[$keyP] = [
                    "id_asesor" => $itemP->id_asesor,
                    "asesor" => $itemP->asesor,
                    "iniciales" => $itemP->iniciales,
                    "cedula" => $itemP->cedula,
                    "ContratosDespues" => $contratosDespues,
                    "ContratosAnterior" => $JsonAntes,
                ];
            } // fin foreach
            if($ifRegalados == '0'){
                // return $datos; // Retornar datos sin regalados
                // if ($periodo != $periodo2) {
                // // periodo anterior
                // $getAsesoresPedidos = $this->pedidoRepository->ReportePedidoGeneral($periodo,1);
                // // periodo despues
                // $getAsesoresPedidos2 = $this->pedidoRepository->ReportePedidoGeneral($periodo2,1);
                // } else {
                // $getAsesoresPedidos = $this->pedidoRepository->ReportePedidoGeneral($periodo2,1);
                // $getAsesoresPedidos2 = $getAsesoresPedidos;
                // }
              if ($periodo != $periodo2) {
                    // periodo anterior
                    $getAsesoresPedidos = $this->pedidoRepository->ReportePedidoGeneral($periodo, 1);
                    // periodo después
                    $getAsesoresPedidos2 = $this->pedidoRepository->ReportePedidoGeneral($periodo2, 1);
                } else {
                    $getAsesoresPedidos = $this->pedidoRepository->ReportePedidoGeneral($periodo2, 1);
                    $getAsesoresPedidos2 = $getAsesoresPedidos;
                }
                // agregar datosVentaAnterior y datosVentaDespues filtrados por id_asesor
                $datos = collect($datos)->map(function ($item) use ($getAsesoresPedidos, $getAsesoresPedidos2) {
                    $idAsesor = is_array($item) ? $item['id_asesor'] : $item->id_asesor;

                    // filtramos por id_asesor en cada periodo
                    $datosVentaAnterior = collect($getAsesoresPedidos)->where('id_asesor', $idAsesor)->values();
                    $datosVentaDespues  = collect($getAsesoresPedidos2)->where('id_asesor', $idAsesor)->values();

                    // añadimos las nuevas propiedades al item original
                    if (is_array($item)) {
                        $item['datosVentaAnterior'] = $datosVentaAnterior;
                        $item['datosVentaDespues']  = $datosVentaDespues;
                    } else {
                        $item->datosVentaAnterior = $datosVentaAnterior;
                        $item->datosVentaDespues  = $datosVentaDespues;
                    }

                    return $item;
                })->toArray();

                return $datos;


                // foreach($datos as $key => $item){

                // }
                return $datos; // Retornar datos sin regalados
            }

            // Definir los regalados
            if ($periodo != $periodo2) {
                // Si los periodos son diferentes, se obtienen los regalados de cada periodo
                $regaladosAnterior = $this->getRegalados($periodo);
                $regaladosDespues = $this->getRegalados($periodo2);

                // Agregar regalados a los datos de cada asesor
                $this->agregarRegalados($datos, $regaladosAnterior, 'regaladosAnterior');
                $this->agregarRegalados($datos, $regaladosDespues, 'regaladosDespues');
            } else {
                // Si los periodos son iguales, solo asignar el mismo valor para regaladosAnterior y regaladosDespues
                $regalados = $this->getRegalados($periodo);
                $this->agregarRegalados($datos, $regalados, 'regaladosAnterior');
                $this->agregarRegalados($datos, $regalados, 'regaladosDespues');
            }

            // Cupones
            $cuponesAnterior = DB::SELECT("SELECT
                p.id_asesor,
                SUM(v.total_descuento) AS total_descuento
            FROM verificaciones_descuentos v
            LEFT JOIN pedidos p
                ON p.contrato_generado = v.contrato
            WHERE p.estado = '1'
            AND p.id_periodo = '$periodo'
            AND v.estado = '1'
            GROUP BY p.id_asesor;
            ");
            // Cupones despues
            $cuponesDespues = DB::SELECT("SELECT
                p.id_asesor,
                SUM(v.total_descuento) AS total_descuento
            FROM verificaciones_descuentos v
            LEFT JOIN pedidos p
                ON p.contrato_generado = v.contrato
            WHERE p.estado = '1'
            AND p.id_periodo = '$periodo2'
            AND v.estado = '1'
            GROUP BY p.id_asesor;
            ");
            // filtrar para agregar a cada asesor por id_asesor
            foreach ($datos as $key => $item) {
                foreach ($cuponesAnterior as $k => $tr) {
                    if ($item['id_asesor'] == $tr->id_asesor) {
                        $datos[$key]['cuponesAnterior'] = [
                            "total_descuento" => $tr->total_descuento,
                        ];
                    }
                }
                if (!isset($datos[$key]['cuponesAnterior'])) {
                    $datos[$key]['cuponesAnterior'] = [
                        "total_descuento" => 0,
                    ];
                }

                foreach ($cuponesDespues as $k => $tr) {
                    if ($item['id_asesor'] == $tr->id_asesor) {
                        $datos[$key]['cuponesDespues'] = [
                            "total_descuento" => $tr->total_descuento,
                        ];
                    }
                }
                if (!isset($datos[$key]['cuponesDespues'])) {
                    $datos[$key]['cuponesDespues'] = [
                        "total_descuento" => 0,
                    ];
                }
            }

            return $datos;

        } catch (\Exception $ex) {
            return ["status" => "0", "message" => "Hubo problemas con la conexión al servidor: " . $ex->getMessage()];
        }
    }



    public function getRegalados($periodo) {
        // return DB::SELECT("
        //     SELECT
        //         CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
        //         p.id_asesor,
        //         SUM(CASE WHEN c.codigo_proforma IS NOT NULL THEN 1 ELSE 0 END) AS cantidad_con_documentos,
        //         SUM(CASE
        //                 WHEN v.ven_desc_por = '100' THEN 0
        //                 ELSE pr.pfn_pvp * (1 - (CAST(v.ven_desc_por AS DECIMAL(5,2)) / 100))
        //             END) AS valor_total_con_descuento,
        //         SUM(CASE WHEN c.codigo_proforma IS NULL THEN 1 ELSE 0 END) AS cantidad_sin_documentos,
        //         0 AS valor_sin_documentos
        //     FROM
        //         codigoslibros c
        //     LEFT JOIN
        //         pedidos p ON p.contrato_generado = c.contrato
        //     LEFT JOIN
        //         pedidos_formato_new pr ON pr.idlibro = c.libro_idlibro
        //     LEFT JOIN
        //         f_venta v ON v.ven_codigo = c.codigo_proforma AND v.id_empresa = c.proforma_empresa
        //     LEFT JOIN
        //         libros_series ls ON ls.idLibro = c.libro_idlibro
        //     LEFT JOIN
        //         usuario u ON u.idusuario = p.id_asesor
        //     WHERE
        //         c.contrato IS NOT NULL
        //         AND c.contrato != ''
        //         AND c.contrato != '0'
        //         AND c.prueba_diagnostica = '0'
        //         AND c.bc_periodo = '$periodo'
        //         AND c.estado_liquidacion = '2'
        //         AND pr.idperiodoescolar = '$periodo'
        //     GROUP BY
        //         u.nombres, u.apellidos, p.id_asesor;
        // ");
        return DB::SELECT("SELECT
            CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
            p.id_asesor,

            -- Codigos incluidos
            SUM(CASE WHEN c.codigo_proforma IS NOT NULL
                        AND (c.quitar_de_reporte  <> '1')
                    THEN 1 ELSE 0 END) AS cantidad_con_documentos,

            SUM(CASE
                    WHEN (c.quitar_de_reporte IS NULL OR c.quitar_de_reporte <> '1')
                        AND v.ven_desc_por != '100'
                    THEN pr.pfn_pvp * (1 - (CAST(v.ven_desc_por AS DECIMAL(5,2)) / 100))
                    ELSE 0
                END) AS valor_total_con_descuento,

            SUM(CASE WHEN c.codigo_proforma IS NULL
                        AND (c.quitar_de_reporte IS NULL OR c.quitar_de_reporte <> '1')
                    THEN 1 ELSE 0 END) AS cantidad_sin_documentos,

            0 AS valor_sin_documentos,

            -- Codigos excluidos (quitados del reporte)
            SUM(CASE WHEN c.quitar_de_reporte = '1' THEN 1 ELSE 0 END) AS cantidad_excluidos

        FROM
            codigoslibros c
        LEFT JOIN
            pedidos p ON p.contrato_generado = c.contrato
        LEFT JOIN
            pedidos_formato_new pr ON pr.idlibro = c.libro_idlibro
        LEFT JOIN
            f_venta v ON v.ven_codigo = c.codigo_proforma AND v.id_empresa = c.proforma_empresa
        LEFT JOIN
            libros_series ls ON ls.idLibro = c.libro_idlibro
        LEFT JOIN
            usuario u ON u.idusuario = p.id_asesor
        WHERE
            c.contrato IS NOT NULL
            AND c.contrato != ''
            AND c.contrato != '0'
            AND c.prueba_diagnostica = '0'
            AND c.bc_periodo = '$periodo'
            AND c.estado_liquidacion = '2'
            AND pr.idperiodoescolar = '$periodo'
        GROUP BY
            u.nombres, u.apellidos, p.id_asesor;

        ");
    }

    public function agregarRegalados(&$datos, $regalados, $key) {
        foreach ($datos as $keyP => $item) {
            foreach ($regalados as $regalado) {
                if ($item['id_asesor'] == $regalado->id_asesor) {
                    $datos[$keyP][$key] = [
                        "cantidad_con_documentos" => $regalado->cantidad_con_documentos,
                        "valor_total_con_descuento" => $regalado->valor_total_con_descuento,
                        "cantidad_sin_documentos" => $regalado->cantidad_sin_documentos,
                        "valor_sin_documentos" => $regalado->valor_sin_documentos,
                        "cantidad_excluidos" => $regalado->cantidad_excluidos
                    ];
                }
            }
            if (!isset($datos[$keyP][$key])) {
                $datos[$keyP][$key] = [
                    "cantidad_con_documentos" => 0,
                    "valor_total_con_descuento" => 0,
                    "cantidad_sin_documentos" => 0,
                    "valor_sin_documentos" => 0,
                    "cantidad_excluidos" => 0
                ];
            }
        }
    }


    public function getContratos($id_asesor,$iniciales,$periodo,$codigoContrato=null){
        if($periodo > 21){  return $this->getContratosAsesorProlipa($id_asesor,$periodo); }
        else             {  return $this->getContratosFueraProlipa($iniciales,$codigoContrato); }
    }
    public function getContratosAsesorProlipa($id_asesor,$periodo){
        $query = DB::SELECT("SELECT p.TotalVentaReal as VEN_VALOR, pe.codigo_contrato as PERIODO,
            (p.TotalVentaReal - ((p.TotalVentaReal * p.descuento)/100)) AS ven_neta,
            p.contrato_generado as contrato,
            NULL AS ven_convertido
        FROM pedidos p
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE p.id_asesor = ?
            AND p.tipo = '0'
            AND p.estado = '1'
            AND p.id_periodo = ?
        ", [$id_asesor, $periodo]);
        return $query;
    }
    public function getContratosFueraProlipa($iniciales,$periodo){
        $query = DB::SELECT("SELECT l.ven_valor as VEN_VALOR,
        ( l.ven_valor - ((l.ven_valor * l.ven_descuento)/100)) AS ven_neta,
        l.ven_codigo,l.ven_convertido,
        SUBSTRING(l.ven_codigo, 3, 3) AS PERIODO
        FROM 1_4_venta l
        WHERE l.ven_d_codigo = ?
        AND l.ven_codigo LIKE CONCAT('%C-', ?, '%')
        AND l.est_ven_codigo <> '3'
        -- AND l.ven_convertido IS NULL
        ", [$iniciales,$periodo]);
        return $query;
    }
    public function getSinContratoProlipa($id_asesor,$periodo){
        $getSinContrato = DB::SELECT("SELECT SUM(p.total_venta)  as ventaBrutaActual,
        SUM(( p.total_venta - ((p.total_venta * p.descuento)/100))) AS ven_neta_actual
        FROM pedidos p
        WHERE p.id_asesor = ?
        AND p.id_periodo = ?
        AND p.contrato_generado IS NULL
        AND p.estado = '1'
        ", [$id_asesor, $periodo]);
        return $getSinContrato;
    }
    public function UpdateCodigo($codigo,$union,$TipoVenta){
        if($TipoVenta == 1){
            return $this->updateCodigoVentaDirecta($codigo,$union);
        }
        if($TipoVenta == 2){
            return $this->updateCodigoVentaLista($codigo,$union);
        }
    }
    public function updateCodigoVentaDirecta($codigo,$union){
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion', '=', '1')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'factura'           => 'f001',
                'bc_institucion'    => 981,
                'bc_periodo'        => 22,
                'venta_estado'      => 2,
                'codigo_union'      => $union
            ]);
        return $codigo;
    }
    public function updateCodigoVentaLista($codigo,$union){
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion', '=', '1')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'factura'                   => 'f001',
                'venta_lista_institucion'   => 981,
                'bc_periodo'                => 22,
                'venta_estado'              => 2,
                'codigo_union'              => $union
            ]);
        return $codigo;
    }
    public function quitarTildes($texto) {
        $tildes = array(
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U'
        );
        return strtr($texto, $tildes);
    }
    public function get_geolocation($apiKey, $ip, $lang = "en", $fields = "*", $excludes = "") {
        $url = "https://api.ipgeolocation.io/ipgeo?apiKey=".$apiKey."&ip=".$ip."&lang=".$lang."&fields=".$fields."&excludes=".$excludes;
        $cURL = curl_init();

        curl_setopt($cURL, CURLOPT_URL, $url);
        curl_setopt($cURL, CURLOPT_HTTPGET, true);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: '.$_SERVER['HTTP_USER_AGENT']
        ));

        return curl_exec($cURL);
    }
    public function getLibrosAsesores($periodo,$asesor_id){
        $val_pedido = DB::SELECT("SELECT pv.valor,
        pv.id_area, pv.tipo_val, pv.id_serie, pv.year,pv.plan_lector,pv.alcance,
        p.id_periodo,
        CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
        se.nombre_serie,p.id_asesor, CONCAT(u.nombres,' ',u.apellidos) as asesor
        FROM pedidos_val_area pv
        LEFT JOIN area ar ON  pv.id_area = ar.idarea
        LEFT JOIN series se ON pv.id_serie = se.id_serie
        LEFT JOIN pedidos p ON pv.id_pedido = p.id_pedido
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        WHERE p.id_periodo  = '$periodo'
        AND p.id_asesor     = '$asesor_id'
        AND p.tipo        = '1'
        AND p.estado      = '1'
        AND p.estado_entrega = '2'
        GROUP BY pv.id
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
                    "valor"             => $tr->valor,
                    "id_area"           => $tr->id_area,
                    "tipo_val"          => $tr->tipo_val,
                    "id_serie"          => $tr->id_serie,
                    "year"              => $tr->year,
                    "plan_lector"       => $tr->plan_lector,
                    "id_periodo"        => $tr->id_periodo,
                    "serieArea"         => $tr->serieArea,
                    "nombre_serie"      => $tr->nombre_serie,
                    "alcance"           => $tr->alcance,
                    "alcance"           => $alcance_id
                ];
            }else{
                //validate que el alcance este cerrado o aprobado
                $query = $this->getAlcanceAbiertoXId($alcance_id);
                if(count($query) > 0){
                    $arreglo[$cont] = (object) [
                        "valor"             => $tr->valor,
                        "id_area"           => $tr->id_area,
                        "tipo_val"          => $tr->tipo_val,
                        "id_serie"          => $tr->id_serie,
                        "year"              => $tr->year,
                        "plan_lector"       => $tr->plan_lector,
                        "id_periodo"        => $tr->id_periodo,
                        "serieArea"         => $tr->serieArea,
                        "nombre_serie"      => $tr->nombre_serie,
                        "alcance"           => $tr->alcance,
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
                $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,pro.pro_reservar, l.descripcionlibro,
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
                $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,pro.pro_reservar, l.descripcionlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = ls.id_serie
                    AND f.id_area = a.area_idarea
                    AND f.id_periodo = '$item->id_periodo'
                )as precio
                FROM libros_series ls
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                inner join 1_4_cal_producto pro on ls.codigo_liquidacion=pro.pro_codigo
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
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
                // "id_area"           => $item->id_area,
                "valor"             => $item->valor,
                // "id_serie"          => $item->id_serie,
                // "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                // "libro_id"          => $valores[0]->idlibro,
                "nombrelibro"       => $valores[0]->nombrelibro,
                // "nombre_serie"      => $item->nombre_serie,
                "precio"            => $valores[0]->precio,
                "codigo"            => $valores[0]->codigo_liquidacion,
                // "stock"             => $valores[0]->pro_reservar,
                // "descripcion"       => $valores[0]->descripcionlibro,
            ];
            $contador++;
        }
           //si el codigo de liquidacion se repite sumar en el valor
        // Crear un array asociativo para agrupar por codigo_liquidacion
        $grouped = [];

        foreach ($datos as $item) {
            $codigo = $item->codigo;

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
    public function pruebaData(Request $request){
        $ids = [
            "3138",
            "3173",
            "3364",
            "3250",
            "3569",
            "2909",
            "2947",
            "2966",
            "3184",
            "3192",
            "3216",
            "3240",
            "3472",
            "3096",
            "2946",
            "2957",
            "3000",
            "3003",
            "3461",

            "3237",
            "2916",
            "2956",
            "3186",
            "2999",
            "3078",
            "2954",
            "2968",
            "2973",
            "3205",




            "3047",
            "3122",
            "3157",
            "3170",
            "3214",
            "3228",
            "3231",
            "3267",
            "3278",
            "3283",
            "3296",
            "3298",
            "3301",
            "3307",
            "3315",
            "3319",
            "3323",
            "3355",
            "3370",
            "3389",
            "3402",
            "3414",
            "3418",
            "3440",
            "3503",
            "3504",
            "3509",
            "3573",
            "3593",
            "3624",
            "3628",
            "3631",
            "3650",
            "3722",
            "3732",
            "3813",
            "3854",
            "3924",
            "3994",
            "3054",
            "3136",
            "3243",
            "3303",
            "3309",
            "3310",
            "3316",
            "3324",
            "3327",
            "3331",
            "3341",
            "3343",
            "3396",
            "3469",
            "3502",
            "3522",
            "3536",
            "3552",
            "3553",
            "3600",
            "3610",
            "3640",
            "2937",
            "2945",
            "3079",
            "3120",
            "3142",
            "3191",
            "3232",
            "3286",
            "3466",
            "3497",
            "3654",
            "2978",
            "3027",
            "3071",
            "3153",
            "3268",
            "3302",
            "3328",
            "3347",
            "3377",
            "2888",
            "2901",
            "2912",
            "2922",
            "3008",
            "3051",
            "3052",
            "3056",
            "3111",
            "3131",
            "3185",
            "3208",
            "3221",
            "3318",
            "3424",
            "3591",
            "3756",
            "2913",
            "2928",
            "3105",
            "3155",
            "3190",
            "3304",
            "3574",
            "2926",
            "2970",
            "3224",
            "3257",
            "3258",
            "3382",
            "2884",
            "2911",
            "2938",
            "2953",
            "2997",
            "3132",
            "3133",
            "3144",
            "3161",
            "3384",
            "3432",
            "3601",
            "3029",
            "2944",
            "3171",
            "2918",
            "2930",
            "2940",
            "2975",
            "3140",
            "3194",
            "2914",
            "2931",
            "2941",
            "2965",
            "3135",
            "3015",
            "3147",
            "3193",
            "3577",
            "4038",
            "2962",
            "3130",
            "3145",
            "3104",
            "3368",
            "3374",
            "3210",
            "3321",
            "3353",
            "3360",
            "3409",
            "3435",
            "3451",
            "3463",
            "3492",
            "3517",
            "3603",
            "3735",
            "3808",
            "3867",
            "3868",
            "3970",
            "3993",
            "3340",
            "3398",
            "3471",
            "3519",
            "3530",
            "3533",
            "3747",
            "2948",
            "3086",
            "3256",
            "3271",
            "3400",
            "3420",
            "2920",
            "2955",
            "3036",
            "3460",
            "3555",
            "3176",
            "3211",
            "3273",
            "3381",
            "3465",
            "3506",
            "2951",
            "3035",
            "3511",
            "2919",
            "3038",
            "3123",
            "3207",
            "3242",
            "3540",
            "3188",
            "3212",
            "2917",
            "3953",
            "3493",
            "3662",
            "2963",
            "3247",
            "3390",
            "3410",
            "3562",
            "3234",
            "3539",
            "3549",
            "3633",
            "3001",
            "3262",
            "3069",
            "3274",
            "3388",
            "3404",
            "3408",
            "3338",
            "3076",
            "3066",
            "3363",
            "3149",
            "3124",
            "3089",
            "3564",
            "3169",
            "3178",
            "3084",
            "3039"
        ];
        $query = DB::SELECT("SELECT *
            FROM (
                SELECT
                    CONCAT(u.nombres,' ', u.apellidos) AS docente,
                    u.cedula,

                    (
                        SELECT COUNT(*)
                        FROM salle_preguntas_evaluacion p
                        WHERE p.id_evaluacion = e.id_evaluacion
                    ) AS total_preguntas,

                    (
                        SELECT COUNT(*)
                        FROM salle_respuestas_preguntas r
                        WHERE r.id_evaluacion = e.id_evaluacion
                    ) AS total_respuestas,

                    e.*
                FROM salle_evaluaciones e
                LEFT JOIN usuario u ON u.idusuario = e.id_usuario
                WHERE e.n_evaluacion = '7'
                AND e.estado = '2'
            ) t
            WHERE t.total_preguntas <> t.total_respuestas
            AND t.id_evaluacion in (" . implode(',', $ids) .  ")
            ");
        $dataResultados = [];
        foreach($query as $key => $item){
            $preguntasFaltantes = DB::SELECT("SELECT p.*, pe.puntaje_pregunta
            FROM salle_preguntas_evaluacion p
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = p.id_pregunta
            LEFT JOIN salle_respuestas_preguntas r
                ON r.id_pregunta = p.id_pregunta
            AND r.id_evaluacion = p.id_evaluacion
            WHERE p.id_evaluacion = ?
            AND r.id_pregunta IS NULL
            AND pe.id_tipo_pregunta <> '1'
            ", [$item->id_evaluacion]);
            $contador = 0;
            // por cada pregunta buscar una opcion aleatoria
            foreach($preguntasFaltantes as $k => $tr){
                $respuestaPregunta = null;
                //
                // $opcionAleatoria = DB::SELECT("SELECT t.id_opcion_pregunta
                // FROM (
                //     SELECT sor.id_opcion_pregunta
                //     FROM salle_opciones_preguntas sor
                //     WHERE sor.id_pregunta = ?
                //     ORDER BY sor.id_opcion_pregunta ASC
                //     LIMIT 2
                // ) t
                // ORDER BY RAND()
                // LIMIT 1;
                // ", [$tr->id_pregunta]);
                //cambiar a opcion correcta
                $opcionAleatoria = DB::SELECT("SELECT sor.id_opcion_pregunta
                    FROM salle_opciones_preguntas sor
                    WHERE sor.id_pregunta = ?
                    AND sor.tipo = '1'
                ", [$tr->id_pregunta]);
                if($opcionAleatoria){
                    $respuestaPregunta = $opcionAleatoria[0]->id_opcion_pregunta;
                    $puntajeSet = 0;
                    //evaluacion si es correcta o no
                    $evaluateRespuesta = DB::SELECT("SELECT * FROM salle_opciones_preguntas sor
                        WHERE sor.id_pregunta = ?
                        AND sor.id_opcion_pregunta = ?
                        AND sor.tipo = '1'
                        ", [$tr->id_pregunta, $respuestaPregunta]);
                    //SI ES MAYOR A CERO ES CORRECTA
                    if(count($evaluateRespuesta) > 0){
                        $puntajeSet = $tr->puntaje_pregunta;
                    }else{
                        $puntajeSet = 0;
                    }
                    //validar si ya existe la respuesta antes de insertar (para evitar duplicados)
                    $validarExistencia = DB::SELECT("SELECT * FROM salle_respuestas_preguntas
                        WHERE id_evaluacion = ?
                        AND id_pregunta = ?
                        AND id_usuario = ?
                        ", [$tr->id_evaluacion, $tr->id_pregunta, $item->id_usuario]);
                    if(count($validarExistencia) > 0){
                        continue; //si ya existe continuar al siguiente
                    }
                    // insertar la respuesta
                    DB::table('salle_respuestas_preguntas')->insert([
                        'id_evaluacion'         => $tr->id_evaluacion,
                        'id_pregunta'           => $tr->id_pregunta,
                        'id_usuario'            => $item->id_usuario,
                        'respuesta'             => $respuestaPregunta,
                        'puntaje'               => $puntajeSet,
                    ]);
                    //actualizar salle_preguntas_evaluacion para sumar el puntaje
                    DB::table('salle_preguntas_evaluacion')->where('id_evaluacion', $tr->id_evaluacion)
                    ->where('id_pregunta', $tr->id_pregunta)
                    ->update([
                        'calificacion_final'  => $puntajeSet,
                        'updated_at'          => now(),
                    ]);
                    //actualizar salle_evaluaciones para sumar el calificacion_total
                    DB::table('salle_evaluaciones')->where('id_evaluacion', $tr->id_evaluacion)
                    ->update([
                        'calificacion_total'  => DB::raw('calificacion_total + ' . $puntajeSet),
                        'updated_at'          => now(),
                    ]);
                    $contador++;

                }

            }
            $totalPuntajeSet = 0;
            $totalPuntajeEvaluacion = 0;
            //actualizar salle_evaluaciones calificacion_total
            $getPuntajeRespuestas = DB::SELECT("SELECT SUM(e.calificacion_final) AS totalRespuestas FROM salle_preguntas_evaluacion e
            WHERE e.id_evaluacion = ?", [$item->id_evaluacion]);
            $totalPuntajeSet  = !empty($getPuntajeRespuestas) ? $getPuntajeRespuestas[0]->totalRespuestas : 0;
            //total puntaje evaluacion
            $getPuntajeEvaluacion = DB::SELECT("SELECT sum(pe.puntaje_pregunta) as totalPuntajeEvaluacion FROM salle_preguntas_evaluacion e
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = e.id_pregunta
            WHERE e.id_evaluacion = ?
            ;", [$item->id_evaluacion]);
            $totalPuntajeEvaluacion = !empty($getPuntajeEvaluacion) ? $getPuntajeEvaluacion[0]->totalPuntajeEvaluacion : 0;
            //calificacion final total puntaje respuestas / total puntaje evaluacion * 100
            $calificacionFinal = 0;
            if($totalPuntajeEvaluacion > 0){
                $calificacionFinal = ($totalPuntajeSet / $totalPuntajeEvaluacion) * 100;
            }

            // $totalRespuestasTEST = 0;
            // $respuestasTest = DB::SELECT("SELECT SUM(e.puntaje) as totalRespuestasTEST FROM salle_respuestas_preguntas e
            // WHERE e.id_evaluacion = ?;", [$item->id_evaluacion]);
            // $totalRespuestasTEST = $respuestasTest[0]->totalRespuestasTEST;
            $dataResultados[$key] = (object)[
                "docente"               => $item->docente,
                "cedula"                => $item->cedula,
                "id_evaluacion"         => $item->id_evaluacion,
                "total_preguntas"       => $item->total_preguntas,
                "total_respuestas"      => $item->total_respuestas,
                "respuestas_insertadas"   => $contador,
                "total_puntaje_obtenido" => $totalPuntajeSet,
                "total_puntaje_evaluacion" => $totalPuntajeEvaluacion,
                "calificacion_total"      => $calificacionFinal,
                // "total_respuestas_test"   => $totalRespuestasTEST
            ];
            //actualizar salle_evaluaciones calificacion_final
            DB::table('salle_evaluaciones')->where('id_evaluacion', $item->id_evaluacion)
            ->update([
                'calificacion_total'  => $calificacionFinal,
                'updated_at'          => now(),
            ]);
        }
        return $dataResultados;
        return "hola mundo";
        // $getCodigos = 'SMCLL3-CFZCY9WFCC,
        // PSMCLL3-8E7RBKE7Y7,
        // SMCLL3-N9TKFGNVT2,
        // PSMCLL3-99KD9EWVD5,
        // SMCM3-HC2E4KCV9Z,
        // PSMCM3-YDAV4898MG,
        // SMCM3-TZRZ8CPKXW,
        // PSMCM3-8P2XNPBHGF,
        // SMCH3-4YZCDREM4A,
        // PSMCH3-SX7YU6CHAK,
        // SMCH3-9YBRZPPFRV,
        // PSMCH3-5FHKADR6HD,
        // SMCB3-YT535X2C36,
        // PSMCB3-97ETA4K26B,
        // SMCB3-CW829B4S4K,
        // PSMCB3-ESBSM2FS95
        // ';
        // $lineas = explode(",", $getCodigos); // Separar por coma
        // // Recorrer y armar el array
        // foreach ($lineas as $linea) {
        //     $codigoLimpio = trim($linea); // Limpiar espacios o saltos de línea
        //     if (!empty($codigoLimpio)) {
        //         $codigos[] = ['codigo' => $codigoLimpio];
        //     }
        // }
        // // Los combos que quieres añadir, excluyendo el combo 'CMB-5YZAW6'
        // $combos = [
        //     'CMB-444UPF',
        //     'CMB-FCV984',
        // ];
        // $libro = 'CFAC3';
        // $getLibrosCombo = _14Producto::findOrFail($libro);
        // if(!$getLibrosCombo){
        //     return ["status" => "0", "message" => "No se encontro el libro $libro"];
        // }
        // $prefixes       = explode(',', $getLibrosCombo->codigos_combos);
        // // Los prefijos (tipos de códigos) que quieres procesar
        // // $prefixes = $request->input('prefixes', ['SEAE2', 'CERP', 'CAMM', 'CUNA']);
        // // Definir la cantidad de códigos por combo
        // $cantidadPorCombo = 8;


        // // Inicializar los arrays para almacenar los resultados
        // $comboOrdenado = [];
        // $combosSinCodigos = [];
        // $codigosProblemas = [];

        // // Se crea un array de colecciones para cada prefijo
        // $filteredByPrefix = [];
        // foreach ($prefixes as $prefix) {
        //     $filteredByPrefix[$prefix] = collect($codigos)->filter(function ($item) use ($prefix) {
        //         return strpos($item['codigo'], $prefix) !== false;
        //     });
        // }

        // // Alternar entre combo y códigos
        // $comboIndex = 0;
        // while ($comboIndex < count($combos)) {
        //     // Verificar si hay suficientes códigos disponibles antes de agregar el combo
        //     $totalDisponibles = collect($filteredByPrefix)->sum(fn($collection) => $collection->count());

        //     if ($totalDisponibles < $cantidadPorCombo) {
        //         // Si no hay suficientes códigos, mover el combo a la lista de problemas y continuar
        //         $combosSinCodigos[] = $combos[$comboIndex];

        //         // Capturar los códigos que no se pudieron usar
        //         foreach ($filteredByPrefix as $prefix => $collection) {
        //             if ($collection->isNotEmpty()) {
        //                 $codigosProblemas = array_merge($codigosProblemas, $collection->all());
        //                 $filteredByPrefix[$prefix] = collect(); // Vaciar la colección para evitar duplicados
        //             }
        //         }

        //         $comboIndex++;
        //         continue;
        //     }

        //     // Añadir un combo primero
        //     $comboOrdenado[] = ['codigo' => $combos[$comboIndex]];
        //     $comboIndex++;

        //     // Añadir códigos
        //     $codesAdded = 0;
        //     while ($codesAdded < $cantidadPorCombo) {
        //         foreach ($prefixes as $prefix) {
        //             if ($filteredByPrefix[$prefix]->isNotEmpty()) {
        //                 // Tomar un código de este prefijo
        //                 $comboOrdenado[] = $filteredByPrefix[$prefix]->shift();
        //                 $codesAdded++;

        //                 if ($codesAdded < $cantidadPorCombo && $filteredByPrefix[$prefix]->isNotEmpty()) {
        //                     $comboOrdenado[] = $filteredByPrefix[$prefix]->shift();
        //                     $codesAdded++;
        //                 }
        //             }

        //             if ($codesAdded >= $cantidadPorCombo) {
        //                 break;
        //             }
        //         }
        //     }
        // }

        // // Retornar el array con la propiedad 'codigo', los combos con problema y los códigos con problema
        // return response()->json([
        //     'combo' => $comboOrdenado,
        //     'combos_sin_codigos' => $combosSinCodigos,
        //     'codigos_problemas' => $codigosProblemas
        // ]);


        // Retorna el array con la propiedad 'codigo'
        return;
        // $clientIP = \Request::getClientIp(true);
        // // $clientIP =  $request->ip();
        // $apiKey = "aba8c348cd6d4d14af6af2294f04d356";
        // // $ip = "186.4.218.168";
        // $ip = $clientIP;
        // $location = $this->get_geolocation($apiKey, $ip);
        // $decodedLocation = json_decode($location, true);

        // echo "<pre>";
        // print_r($decodedLocation);
        // echo "</pre>";

        // return;
        $formData = [
            "api_key" => "RfVaC9hIMhn49J4jSq2_I_.QLazmDGrbZQ8o8ePUEcU-"
        ];
        $data           = Http::post('http://190.12.43.171:8181/api/consulta_provincias',$formData);
        $datos          = json_decode($data, true);
        return $datos;

        // $tracerouteOutput = $this->runSystemCommand('traceroute 190.12.43.171');
        // $telnetOutput = $this->runSystemCommand('telnet 190.12.43.171 443');

        // return response()->json([
        //     'traceroute' => $tracerouteOutput,
        //     'telnet' => $telnetOutput,
        // ]);
    }
    //ALEATORIO COMPLETAR
    public function pruebaData2(Request $request){
          $ids = [
            "3641"
        ];
        $query = DB::SELECT("SELECT *
            FROM (
                SELECT
                    CONCAT(u.nombres,' ', u.apellidos) AS docente,
                    u.cedula,

                    (
                        SELECT COUNT(*)
                        FROM salle_preguntas_evaluacion p
                        WHERE p.id_evaluacion = e.id_evaluacion
                    ) AS total_preguntas,

                    (
                        SELECT COUNT(*)
                        FROM salle_respuestas_preguntas r
                        WHERE r.id_evaluacion = e.id_evaluacion
                    ) AS total_respuestas,

                    e.*
                FROM salle_evaluaciones e
                LEFT JOIN usuario u ON u.idusuario = e.id_usuario
                WHERE e.n_evaluacion = '7'
                AND e.estado = '2'
            ) t
            WHERE t.total_preguntas <> t.total_respuestas
            AND t.id_evaluacion in (" . implode(',', $ids) .  ")
            ");
        $dataResultados = [];
        foreach($query as $key => $item){
            $preguntasFaltantes = DB::SELECT("SELECT p.*, pe.puntaje_pregunta
            FROM salle_preguntas_evaluacion p
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = p.id_pregunta
            LEFT JOIN salle_respuestas_preguntas r
                ON r.id_pregunta = p.id_pregunta
            AND r.id_evaluacion = p.id_evaluacion
            WHERE p.id_evaluacion = ?
            AND r.id_pregunta IS NULL
            AND pe.id_tipo_pregunta <> '1'
            ", [$item->id_evaluacion]);
            $contador = 0;
            // por cada pregunta buscar una opcion aleatoria
            foreach($preguntasFaltantes as $k => $tr){
                $respuestaPregunta = null;
                //
                $opcionAleatoria = DB::SELECT("SELECT t.id_opcion_pregunta
                FROM (
                    SELECT sor.id_opcion_pregunta
                    FROM salle_opciones_preguntas sor
                    WHERE sor.id_pregunta = ?
                    ORDER BY sor.id_opcion_pregunta ASC
                    LIMIT 2
                ) t
                ORDER BY RAND()
                LIMIT 1;
                ", [$tr->id_pregunta]);
                //cambiar a opcion correcta
                // $opcionAleatoria = DB::SELECT("SELECT sor.id_opcion_pregunta
                //     FROM salle_opciones_preguntas sor
                //     WHERE sor.id_pregunta = ?
                //     AND sor.tipo = '1'
                // ", [$tr->id_pregunta]);
                if($opcionAleatoria){
                    $respuestaPregunta = $opcionAleatoria[0]->id_opcion_pregunta;
                    $puntajeSet = 0;
                    //evaluacion si es correcta o no
                    $evaluateRespuesta = DB::SELECT("SELECT * FROM salle_opciones_preguntas sor
                        WHERE sor.id_pregunta = ?
                        AND sor.id_opcion_pregunta = ?
                        AND sor.tipo = '1'
                        ", [$tr->id_pregunta, $respuestaPregunta]);
                    //SI ES MAYOR A CERO ES CORRECTA
                    if(count($evaluateRespuesta) > 0){
                        $puntajeSet = $tr->puntaje_pregunta;
                    }else{
                        $puntajeSet = 0;
                    }
                    //validar si ya existe la respuesta antes de insertar (para evitar duplicados)
                    $validarExistencia = DB::SELECT("SELECT * FROM salle_respuestas_preguntas
                        WHERE id_evaluacion = ?
                        AND id_pregunta = ?
                        AND id_usuario = ?
                        ", [$tr->id_evaluacion, $tr->id_pregunta, $item->id_usuario]);
                    if(count($validarExistencia) > 0){
                        continue; //si ya existe continuar al siguiente
                    }
                    // insertar la respuesta
                    DB::table('salle_respuestas_preguntas')->insert([
                        'id_evaluacion'         => $tr->id_evaluacion,
                        'id_pregunta'           => $tr->id_pregunta,
                        'id_usuario'            => $item->id_usuario,
                        'respuesta'             => $respuestaPregunta,
                        'puntaje'               => $puntajeSet,
                    ]);
                    //actualizar salle_preguntas_evaluacion para sumar el puntaje
                    DB::table('salle_preguntas_evaluacion')->where('id_evaluacion', $tr->id_evaluacion)
                    ->where('id_pregunta', $tr->id_pregunta)
                    ->update([
                        'calificacion_final'  => $puntajeSet,
                        'updated_at'          => now(),
                    ]);
                    //actualizar salle_evaluaciones para sumar el calificacion_total
                    DB::table('salle_evaluaciones')->where('id_evaluacion', $tr->id_evaluacion)
                    ->update([
                        'calificacion_total'  => DB::raw('calificacion_total + ' . $puntajeSet),
                        'updated_at'          => now(),
                    ]);
                    $contador++;

                }

            }
            $totalPuntajeSet = 0;
            $totalPuntajeEvaluacion = 0;
            //actualizar salle_evaluaciones calificacion_total
            $getPuntajeRespuestas = DB::SELECT("SELECT SUM(e.calificacion_final) AS totalRespuestas FROM salle_preguntas_evaluacion e
            WHERE e.id_evaluacion = ?", [$item->id_evaluacion]);
            $totalPuntajeSet  = !empty($getPuntajeRespuestas) ? $getPuntajeRespuestas[0]->totalRespuestas : 0;
            //total puntaje evaluacion
            $getPuntajeEvaluacion = DB::SELECT("SELECT sum(pe.puntaje_pregunta) as totalPuntajeEvaluacion FROM salle_preguntas_evaluacion e
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = e.id_pregunta
            WHERE e.id_evaluacion = ?
            ;", [$item->id_evaluacion]);
            $totalPuntajeEvaluacion = !empty($getPuntajeEvaluacion) ? $getPuntajeEvaluacion[0]->totalPuntajeEvaluacion : 0;
            //calificacion final total puntaje respuestas / total puntaje evaluacion * 100
            $calificacionFinal = 0;
            if($totalPuntajeEvaluacion > 0){
                $calificacionFinal = ($totalPuntajeSet / $totalPuntajeEvaluacion) * 100;
            }

            // $totalRespuestasTEST = 0;
            // $respuestasTest = DB::SELECT("SELECT SUM(e.puntaje) as totalRespuestasTEST FROM salle_respuestas_preguntas e
            // WHERE e.id_evaluacion = ?;", [$item->id_evaluacion]);
            // $totalRespuestasTEST = $respuestasTest[0]->totalRespuestasTEST;
            $dataResultados[$key] = (object)[
                "docente"               => $item->docente,
                "cedula"                => $item->cedula,
                "id_evaluacion"         => $item->id_evaluacion,
                "total_preguntas"       => $item->total_preguntas,
                "total_respuestas"      => $item->total_respuestas,
                "respuestas_insertadas"   => $contador,
                "total_puntaje_obtenido" => $totalPuntajeSet,
                "total_puntaje_evaluacion" => $totalPuntajeEvaluacion,
                "calificacion_total"      => $calificacionFinal,
                // "total_respuestas_test"   => $totalRespuestasTEST
            ];
            //actualizar salle_evaluaciones calificacion_final
            DB::table('salle_evaluaciones')->where('id_evaluacion', $item->id_evaluacion)
            ->update([
                'calificacion_total'  => $calificacionFinal,
                'updated_at'          => now(),
            ]);
        }
        return $dataResultados;
    }
    //fundamentales
    public function pruebaData3(Request $request){
          $ids = [
            "3641",
        ];
        $query = DB::SELECT("SELECT *
            FROM (
                SELECT
                    CONCAT(u.nombres,' ', u.apellidos) AS docente,
                    u.cedula,

                    (
                        SELECT COUNT(*)
                        FROM salle_preguntas_evaluacion p
                        WHERE p.id_evaluacion = e.id_evaluacion
                    ) AS total_preguntas,

                    (
                        SELECT COUNT(*)
                        FROM salle_respuestas_preguntas r
                        WHERE r.id_evaluacion = e.id_evaluacion
                    ) AS total_respuestas,

                    e.*
                FROM salle_evaluaciones e
                LEFT JOIN usuario u ON u.idusuario = e.id_usuario
                WHERE e.n_evaluacion = '7'
                AND e.estado = '2'
            ) t
            WHERE t.total_preguntas <> t.total_respuestas
            AND t.id_evaluacion in (" . implode(',', $ids) .  ")
            ");
        $dataResultados = [];
        foreach($query as $key => $item){
            // preguntas faltantes del area fundamentales
            $preguntasFaltantes = DB::SELECT("SELECT p.*, pe.puntaje_pregunta, a.id_asignatura, a.nombre_asignatura,ar.id_area, ar.nombre_area
            FROM salle_preguntas_evaluacion p
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = p.id_pregunta
            LEFT JOIN salle_asignaturas a ON pe.id_asignatura = a.id_asignatura
            LEFT JOIN salle_areas ar ON ar.id_area = a.id_area
            LEFT JOIN salle_respuestas_preguntas r
                ON r.id_pregunta = p.id_pregunta
            AND r.id_evaluacion = p.id_evaluacion
            WHERE p.id_evaluacion = ?
            AND r.id_pregunta IS NULL
            AND pe.id_tipo_pregunta <> '1'
            AND ar.id_area = '47'
            ", [$item->id_evaluacion]);
            $contador = 0;
            // por cada pregunta buscar una opcion aleatoria
            foreach($preguntasFaltantes as $k => $tr){
                $respuestaPregunta = null;
                //
                $opcionAleatoria = DB::SELECT("SELECT t.id_opcion_pregunta
                FROM (
                    SELECT sor.id_opcion_pregunta
                    FROM salle_opciones_preguntas sor
                    WHERE sor.id_pregunta = ?
                    ORDER BY sor.id_opcion_pregunta ASC
                    LIMIT 2
                ) t
                ORDER BY RAND()
                LIMIT 1;
                ", [$tr->id_pregunta]);
                //cambiar a opcion correcta
                // $opcionAleatoria = DB::SELECT("SELECT sor.id_opcion_pregunta
                //     FROM salle_opciones_preguntas sor
                //     WHERE sor.id_pregunta = ?
                //     AND sor.tipo = '1'
                // ", [$tr->id_pregunta]);
                if($opcionAleatoria){
                    $respuestaPregunta = $opcionAleatoria[0]->id_opcion_pregunta;
                    $puntajeSet = 0;
                    //evaluacion si es correcta o no
                    $evaluateRespuesta = DB::SELECT("SELECT * FROM salle_opciones_preguntas sor
                        WHERE sor.id_pregunta = ?
                        AND sor.id_opcion_pregunta = ?
                        AND sor.tipo = '1'
                        ", [$tr->id_pregunta, $respuestaPregunta]);
                    //SI ES MAYOR A CERO ES CORRECTA
                    if(count($evaluateRespuesta) > 0){
                        $puntajeSet = $tr->puntaje_pregunta;
                    }else{
                        $puntajeSet = 0;
                    }
                    //validar si ya existe la respuesta antes de insertar (para evitar duplicados)
                    $validarExistencia = DB::SELECT("SELECT * FROM salle_respuestas_preguntas
                        WHERE id_evaluacion = ?
                        AND id_pregunta = ?
                        AND id_usuario = ?
                        ", [$tr->id_evaluacion, $tr->id_pregunta, $item->id_usuario]);
                    if(count($validarExistencia) > 0){
                        continue; //si ya existe continuar al siguiente
                    }
                    // insertar la respuesta
                    DB::table('salle_respuestas_preguntas')->insert([
                        'id_evaluacion'         => $tr->id_evaluacion,
                        'id_pregunta'           => $tr->id_pregunta,
                        'id_usuario'            => $item->id_usuario,
                        'respuesta'             => $respuestaPregunta,
                        'puntaje'               => $puntajeSet,
                    ]);
                    //actualizar salle_preguntas_evaluacion para sumar el puntaje
                    DB::table('salle_preguntas_evaluacion')->where('id_evaluacion', $tr->id_evaluacion)
                    ->where('id_pregunta', $tr->id_pregunta)
                    ->update([
                        'calificacion_final'  => $puntajeSet,
                        'updated_at'          => now(),
                    ]);
                    //actualizar salle_evaluaciones para sumar el calificacion_total
                    DB::table('salle_evaluaciones')->where('id_evaluacion', $tr->id_evaluacion)
                    ->update([
                        'calificacion_total'  => DB::raw('calificacion_total + ' . $puntajeSet),
                        'updated_at'          => now(),
                    ]);
                    $contador++;

                }

            }
            $totalPuntajeSet = 0;
            $totalPuntajeEvaluacion = 0;
            //actualizar salle_evaluaciones calificacion_total
            $getPuntajeRespuestas = DB::SELECT("SELECT SUM(e.calificacion_final) AS totalRespuestas FROM salle_preguntas_evaluacion e
            WHERE e.id_evaluacion = ?", [$item->id_evaluacion]);
            $totalPuntajeSet  = !empty($getPuntajeRespuestas) ? $getPuntajeRespuestas[0]->totalRespuestas : 0;
            //total puntaje evaluacion
            $getPuntajeEvaluacion = DB::SELECT("SELECT sum(pe.puntaje_pregunta) as totalPuntajeEvaluacion FROM salle_preguntas_evaluacion e
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = e.id_pregunta
            WHERE e.id_evaluacion = ?
            ;", [$item->id_evaluacion]);
            $totalPuntajeEvaluacion = !empty($getPuntajeEvaluacion) ? $getPuntajeEvaluacion[0]->totalPuntajeEvaluacion : 0;
            //calificacion final total puntaje respuestas / total puntaje evaluacion * 100
            $calificacionFinal = 0;
            if($totalPuntajeEvaluacion > 0){
                $calificacionFinal = ($totalPuntajeSet / $totalPuntajeEvaluacion) * 100;
            }

            // $totalRespuestasTEST = 0;
            // $respuestasTest = DB::SELECT("SELECT SUM(e.puntaje) as totalRespuestasTEST FROM salle_respuestas_preguntas e
            // WHERE e.id_evaluacion = ?;", [$item->id_evaluacion]);
            // $totalRespuestasTEST = $respuestasTest[0]->totalRespuestasTEST;
            $dataResultados[$key] = (object)[
                "docente"               => $item->docente,
                "cedula"                => $item->cedula,
                "id_evaluacion"         => $item->id_evaluacion,
                "total_preguntas"       => $item->total_preguntas,
                "total_respuestas"      => $item->total_respuestas,
                "respuestas_insertadas"   => $contador,
                "total_puntaje_obtenido" => $totalPuntajeSet,
                "total_puntaje_evaluacion" => $totalPuntajeEvaluacion,
                "calificacion_total"      => $calificacionFinal,
                // "total_respuestas_test"   => $totalRespuestasTEST
            ];
            //actualizar salle_evaluaciones calificacion_final
            DB::table('salle_evaluaciones')->where('id_evaluacion', $item->id_evaluacion)
            ->update([
                'calificacion_total'  => $calificacionFinal,
                'updated_at'          => now(),
            ]);
        }
        return $dataResultados;
    }

    //POR ASIGNATURA
     public function pruebaData4(Request $request){
        return "hola mundo";
          $configuracion = [
            [
                'id_evaluacion' => 3162,
                'ids_asignaturas' => ['142','146','152','156','160','163']
            ],
            [
                'id_evaluacion' => 3165,
                'ids_asignaturas' => ['160']
            ],
            [
                'id_evaluacion' => 3050,
                'ids_asignaturas' => ['130','133','134','139']
            ],
            [
                'id_evaluacion' => 3175,
                'ids_asignaturas' => ['142','150','152']
            ]
        ];

        // Crear un array asociativo para buscar asignaturas por id_evaluacion
        $evaluacionAsignaturas = [];
        $idsEvaluaciones = [];
        foreach($configuracion as $item){
            $evaluacionAsignaturas[$item['id_evaluacion']] = $item['ids_asignaturas'];
            $idsEvaluaciones[] = $item['id_evaluacion'];
        }

        $query = DB::SELECT("SELECT *
            FROM (
                SELECT
                    CONCAT(u.nombres,' ', u.apellidos) AS docente,
                    u.cedula,

                    (
                        SELECT COUNT(*)
                        FROM salle_preguntas_evaluacion p
                        WHERE p.id_evaluacion = e.id_evaluacion
                    ) AS total_preguntas,

                    (
                        SELECT COUNT(*)
                        FROM salle_respuestas_preguntas r
                        WHERE r.id_evaluacion = e.id_evaluacion
                    ) AS total_respuestas,

                    e.*
                FROM salle_evaluaciones e
                LEFT JOIN usuario u ON u.idusuario = e.id_usuario
                WHERE e.n_evaluacion = '7'
                AND e.estado = '2'
            ) t
            WHERE t.total_preguntas <> t.total_respuestas
            AND t.id_evaluacion in (" . implode(',', $idsEvaluaciones) .  ")
            ");
        $dataResultados = [];
        foreach($query as $key => $item){
            // Obtener las asignaturas correspondientes a esta evaluacion
            $asignaturasPermitidas = isset($evaluacionAsignaturas[$item->id_evaluacion])
                ? $evaluacionAsignaturas[$item->id_evaluacion]
                : [];

            if(empty($asignaturasPermitidas)){
                continue; // Si no hay asignaturas configuradas, saltar esta evaluacion
            }

            // preguntas faltantes del area fundamentales
            $preguntasFaltantes = DB::SELECT("SELECT p.*, pe.puntaje_pregunta, a.id_asignatura, a.nombre_asignatura,ar.id_area, ar.nombre_area
            FROM salle_preguntas_evaluacion p
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = p.id_pregunta
            LEFT JOIN salle_asignaturas a ON pe.id_asignatura = a.id_asignatura
            LEFT JOIN salle_areas ar ON ar.id_area = a.id_area
            LEFT JOIN salle_respuestas_preguntas r
                ON r.id_pregunta = p.id_pregunta
            AND r.id_evaluacion = p.id_evaluacion
            WHERE p.id_evaluacion = ?
            AND r.id_pregunta IS NULL
            AND pe.id_tipo_pregunta <> '1'
   			AND a.id_asignatura IN ('" . implode("','", $asignaturasPermitidas) . "')

            ", [$item->id_evaluacion]);
            $contador = 0;
            // por cada pregunta buscar una opcion aleatoria
            foreach($preguntasFaltantes as $k => $tr){
                $respuestaPregunta = null;
                //
                $opcionAleatoria = DB::SELECT("SELECT t.id_opcion_pregunta
                FROM (
                    SELECT sor.id_opcion_pregunta
                    FROM salle_opciones_preguntas sor
                    WHERE sor.id_pregunta = ?
                    ORDER BY sor.id_opcion_pregunta ASC
                    LIMIT 2
                ) t
                ORDER BY RAND()
                LIMIT 1;
                ", [$tr->id_pregunta]);
                //cambiar a opcion correcta
                // $opcionAleatoria = DB::SELECT("SELECT sor.id_opcion_pregunta
                //     FROM salle_opciones_preguntas sor
                //     WHERE sor.id_pregunta = ?
                //     AND sor.tipo = '1'
                // ", [$tr->id_pregunta]);
                if($opcionAleatoria){
                    $respuestaPregunta = $opcionAleatoria[0]->id_opcion_pregunta;
                    $puntajeSet = 0;
                    //evaluacion si es correcta o no
                    $evaluateRespuesta = DB::SELECT("SELECT * FROM salle_opciones_preguntas sor
                        WHERE sor.id_pregunta = ?
                        AND sor.id_opcion_pregunta = ?
                        AND sor.tipo = '1'
                        ", [$tr->id_pregunta, $respuestaPregunta]);
                    //SI ES MAYOR A CERO ES CORRECTA
                    if(count($evaluateRespuesta) > 0){
                        $puntajeSet = $tr->puntaje_pregunta;
                    }else{
                        $puntajeSet = 0;
                    }
                    //validar si ya existe la respuesta antes de insertar (para evitar duplicados)
                    $validarExistencia = DB::SELECT("SELECT * FROM salle_respuestas_preguntas
                        WHERE id_evaluacion = ?
                        AND id_pregunta = ?
                        AND id_usuario = ?
                        ", [$tr->id_evaluacion, $tr->id_pregunta, $item->id_usuario]);
                    if(count($validarExistencia) > 0){
                        continue; //si ya existe continuar al siguiente
                    }
                    // insertar la respuesta
                    DB::table('salle_respuestas_preguntas')->insert([
                        'id_evaluacion'         => $tr->id_evaluacion,
                        'id_pregunta'           => $tr->id_pregunta,
                        'id_usuario'            => $item->id_usuario,
                        'respuesta'             => $respuestaPregunta,
                        'puntaje'               => $puntajeSet,
                    ]);
                    //actualizar salle_preguntas_evaluacion para sumar el puntaje
                    DB::table('salle_preguntas_evaluacion')->where('id_evaluacion', $tr->id_evaluacion)
                    ->where('id_pregunta', $tr->id_pregunta)
                    ->update([
                        'calificacion_final'  => $puntajeSet,
                        'updated_at'          => now(),
                    ]);
                    //actualizar salle_evaluaciones para sumar el calificacion_total
                    DB::table('salle_evaluaciones')->where('id_evaluacion', $tr->id_evaluacion)
                    ->update([
                        'calificacion_total'  => DB::raw('calificacion_total + ' . $puntajeSet),
                        'updated_at'          => now(),
                    ]);
                    $contador++;

                }

            }
            $totalPuntajeSet = 0;
            $totalPuntajeEvaluacion = 0;
            //actualizar salle_evaluaciones calificacion_total
            $getPuntajeRespuestas = DB::SELECT("SELECT SUM(e.calificacion_final) AS totalRespuestas FROM salle_preguntas_evaluacion e
            WHERE e.id_evaluacion = ?", [$item->id_evaluacion]);
            $totalPuntajeSet  = !empty($getPuntajeRespuestas) ? $getPuntajeRespuestas[0]->totalRespuestas : 0;
            //total puntaje evaluacion
            $getPuntajeEvaluacion = DB::SELECT("SELECT sum(pe.puntaje_pregunta) as totalPuntajeEvaluacion FROM salle_preguntas_evaluacion e
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = e.id_pregunta
            WHERE e.id_evaluacion = ?
            ;", [$item->id_evaluacion]);
            $totalPuntajeEvaluacion = !empty($getPuntajeEvaluacion) ? $getPuntajeEvaluacion[0]->totalPuntajeEvaluacion : 0;
            //calificacion final total puntaje respuestas / total puntaje evaluacion * 100
            $calificacionFinal = 0;
            if($totalPuntajeEvaluacion > 0){
                $calificacionFinal = ($totalPuntajeSet / $totalPuntajeEvaluacion) * 100;
            }

            // $totalRespuestasTEST = 0;
            // $respuestasTest = DB::SELECT("SELECT SUM(e.puntaje) as totalRespuestasTEST FROM salle_respuestas_preguntas e
            // WHERE e.id_evaluacion = ?;", [$item->id_evaluacion]);
            // $totalRespuestasTEST = $respuestasTest[0]->totalRespuestasTEST;
            $dataResultados[$key] = (object)[
                "docente"               => $item->docente,
                "cedula"                => $item->cedula,
                "id_evaluacion"         => $item->id_evaluacion,
                "total_preguntas"       => $item->total_preguntas,
                "total_respuestas"      => $item->total_respuestas,
                "respuestas_insertadas"   => $contador,
                "total_puntaje_obtenido" => $totalPuntajeSet,
                "total_puntaje_evaluacion" => $totalPuntajeEvaluacion,
                "calificacion_total"      => $calificacionFinal,
                // "total_respuestas_test"   => $totalRespuestasTEST
            ];
            //actualizar salle_evaluaciones calificacion_final
            DB::table('salle_evaluaciones')->where('id_evaluacion', $item->id_evaluacion)
            ->update([
                'calificacion_total'  => $calificacionFinal,
                'updated_at'          => now(),
            ]);
        }
        return $dataResultados;
    }

    //rellenar salle_preguntas
    public function pruebaData5(Request $request){
       $configuracion = [
            [
                'id_usuario' => 44510,
                'id_evaluacion_rellenar'     => 3641,
                'id_evaluacion_pr_completas' => 3929
            ],
        ];

        foreach($configuracion as $item){
            $id_evaluacion_rellenar     = $item['id_evaluacion_rellenar'];
            $id_evaluacion_pr_completas = $item['id_evaluacion_pr_completas'];
            $id_usuario = $item['id_usuario'];
            //preguntas completas
            $preguntasCompletas = DB::SELECT("SELECT a.id_asignatura,
                    a.nombre_asignatura,
                    COUNT(e.id_pregunta) AS total_preguntas
                FROM salle_preguntas_evaluacion e
                LEFT JOIN salle_preguntas pe
                    ON pe.id_pregunta = e.id_pregunta
                LEFT JOIN salle_asignaturas a
                    ON a.id_asignatura = pe.id_asignatura
                LEFT JOIN salle_areas ar
                    ON ar.id_area = a.id_area
                WHERE e.id_evaluacion = '$id_evaluacion_pr_completas'
                GROUP BY a.id_asignatura, a.nombre_asignatura;
            ");
            //respuestas con preguntas incompletas
            $preguntasIncompletas = DB::SELECT("SELECT a.id_asignatura,
                    a.nombre_asignatura,
                    COUNT(r.id_respuesta_pregunta) AS total_respuestas,
                    r.id_evaluacion
                FROM salle_respuestas_preguntas r
                LEFT JOIN salle_preguntas pe
                    ON pe.id_pregunta = r.id_pregunta
                LEFT JOIN salle_asignaturas a
                    ON a.id_asignatura = pe.id_asignatura
                LEFT JOIN salle_areas ar
                    ON ar.id_area = a.id_area
               WHERE  r.id_evaluacion = '$id_evaluacion_rellenar'
                GROUP BY id_asignatura, a.nombre_asignatura, r.id_evaluacion;

            ");

            // Crear un array asociativo de las respuestas incompletas para fácil búsqueda
            $incompletasMap = [];
            foreach($preguntasIncompletas as $incompleta){
                $incompletasMap[$incompleta->id_asignatura] = [
                    'nombre_asignatura' => $incompleta->nombre_asignatura,
                    'total_respuestas' => $incompleta->total_respuestas,
                    'id_evaluacion' => $incompleta->id_evaluacion
                ];
            }

            // Comparar y crear array de diferencias
            $diferencias = [];
            foreach($preguntasCompletas as $completa){
                $id_asignatura = $completa->id_asignatura;
                $total_completas = $completa->total_preguntas;

                // Verificar si existe en incompletas
                if(isset($incompletasMap[$id_asignatura])){
                    $total_incompletas = $incompletasMap[$id_asignatura]['total_respuestas'];
                } else {
                    $total_incompletas = 0;
                }

                // Calcular cuántas preguntas faltan
                $preguntas_faltantes = $total_completas - $total_incompletas;

                // Agregar al array de diferencias
                $diferencias[] = [
                    'id_asignatura' => $id_asignatura,
                    'nombre_asignatura' => $completa->nombre_asignatura,
                    'total_completas' => $total_completas,
                    'total_incompletas' => $total_incompletas,
                    'preguntas_faltantes' => $preguntas_faltantes,
                    'estado' => $preguntas_faltantes == 0 ? 'COMPLETO' : 'INCOMPLETO'
                ];
            }

            //===========================MOSTRAR COMPLETAS==============================

            $arrayCompletas = array_filter($diferencias, function($diff) {
                return $diff['estado'] === 'COMPLETO';
            });
            $resultadoFinalCompletas = array_values($arrayCompletas);
            // agregar respuestas completas
            foreach($resultadoFinalCompletas as $comp){
                $preguntasCompletasDetalle = DB::SELECT("SELECT distinct r.id_pregunta
                    FROM salle_respuestas_preguntas r
                    LEFT JOIN salle_preguntas pe ON pe.id_pregunta = r.id_pregunta
                    LEFT JOIN salle_asignaturas a ON a.id_asignatura = pe.id_asignatura
                    LEFT JOIN salle_areas ar ON ar.id_area = a.id_area
                    WHERE  r.id_evaluacion = '$id_evaluacion_rellenar'
                    AND a.id_asignatura = '".$comp['id_asignatura']."'
                ");
                foreach($preguntasCompletasDetalle as $pcd){
                    $puntajeSet = 0;
                    //obtener el puntaje de la pregunta
                    $getPuntajePregunta = DB::SELECT("SELECT SUM(COALESCE(r.puntaje, 0)) AS puntaje_pregunta
                    FROM salle_respuestas_preguntas r
                    WHERE r.id_evaluacion = '$id_evaluacion_rellenar'
                    AND r.id_pregunta = '".$pcd->id_pregunta."';
                    ");
                    if(!empty($getPuntajePregunta)) {
                        $puntajeSet = $getPuntajePregunta[0]->puntaje_pregunta;
                    }
                    //validar si ya existe la respuesta antes de insertar (para evitar duplicados)
                    $validarExistencia = DB::SELECT("SELECT * FROM salle_preguntas_evaluacion
                        WHERE id_evaluacion = ?
                        AND id_pregunta = ?
                        ", [$id_evaluacion_rellenar, $pcd->id_pregunta]);
                    if(count($validarExistencia) > 0){
                        continue; //si ya existe continuar al siguiente
                    }
                    // registrar en salle_preguntas_evaluacion
                    $preguntaEvaluacion = new SallePreguntasEvaluacion();
                    $preguntaEvaluacion->id_evaluacion = $id_evaluacion_rellenar;
                    $preguntaEvaluacion->id_pregunta = $pcd->id_pregunta; //pregunta de prueba
                    $preguntaEvaluacion->calificacion_final = $puntajeSet;
                    $preguntaEvaluacion->save();
                }
            }

            //===========================MOSTRAR INCOMPLETAS==============================
            // Filtrar las diferencias solo incompletas
            $resultadoFinalImcompletas = array_filter($diferencias, function($diff) {
                return $diff['estado'] === 'INCOMPLETO';
            });
            $resultadoFinalImcompletas = array_values($resultadoFinalImcompletas);
            // Procesar asignaturas incompletas
            foreach($resultadoFinalImcompletas as $incomp){
                $id_asignatura = $incomp['id_asignatura'];

                // Obtener las preguntas que ya respondió el usuario
                $preguntasRespondidas = DB::SELECT("SELECT DISTINCT r.id_pregunta
                    FROM salle_respuestas_preguntas r
                    LEFT JOIN salle_preguntas pe ON pe.id_pregunta = r.id_pregunta
                    WHERE r.id_evaluacion = '$id_evaluacion_rellenar'
                    AND pe.id_asignatura = '$id_asignatura'
                ");
                foreach($preguntasRespondidas as $pr){
                      $puntajeSet = 0;
                    //obtener el puntaje de la pregunta
                    $getPuntajePregunta = DB::SELECT("SELECT SUM(COALESCE(r.puntaje, 0)) AS puntaje_pregunta
                    FROM salle_respuestas_preguntas r
                    WHERE r.id_evaluacion = '$id_evaluacion_rellenar'
                    AND r.id_pregunta = '".$pr->id_pregunta."';
                    ");
                    if(!empty($getPuntajePregunta)) {
                        $puntajeSet = $getPuntajePregunta[0]->puntaje_pregunta;
                    }
                    //validar si ya existe la respuesta antes de insertar (para evitar duplicados)
                    $validarExistencia = DB::SELECT("SELECT * FROM salle_preguntas_evaluacion
                        WHERE id_evaluacion = ?
                        AND id_pregunta = ?
                        ", [$id_evaluacion_rellenar, $pr->id_pregunta]);
                    if(count($validarExistencia) > 0){
                        continue; //si ya existe continuar al siguiente
                    }
                    // registrar en salle_preguntas_evaluacion
                    $preguntaEvaluacion = new SallePreguntasEvaluacion();
                    $preguntaEvaluacion->id_evaluacion = $id_evaluacion_rellenar;
                    $preguntaEvaluacion->id_pregunta = $pr->id_pregunta; //pregunta de prueba
                    $preguntaEvaluacion->calificacion_final = $puntajeSet;
                    $preguntaEvaluacion->save();
                }

            }

            //================== RELLENAR PREGUNTAS FALTANTES DE ASIGNATURAS INCOMPLETAS=============
            foreach($resultadoFinalImcompletas as $incomp){
                $total_completas     = $incomp['total_completas'];
                $id_asignatura       = $incomp['id_asignatura'];
                $preguntas_faltantes = $incomp['preguntas_faltantes'];

                // Obtener preguntas que ya están registradas en salle_preguntas_evaluacion para esta evaluación y asignatura
                $preguntasYaRegistradas = DB::SELECT("SELECT DISTINCT e.id_pregunta
                    FROM salle_preguntas_evaluacion e
                    LEFT JOIN salle_preguntas pe ON pe.id_pregunta = e.id_pregunta
                    WHERE e.id_evaluacion = '$id_evaluacion_rellenar'
                    AND pe.id_asignatura = '$id_asignatura'
                ");

                // Si ya tiene todas las preguntas completas, continuar al siguiente
                if(count($preguntasYaRegistradas) >= $total_completas){
                    continue;
                }

                // Crear array de IDs ya registrados
                $idsYaRegistrados = array_map(function($item) {
                    return $item->id_pregunta;
                }, $preguntasYaRegistradas);

                $idsYaRegistrados_str = !empty($idsYaRegistrados) ? implode(',', $idsYaRegistrados) : '0';

                // Buscar preguntas de la evaluación completa que NO estén registradas
                $preguntasParaAgregar = DB::SELECT("SELECT DISTINCT e.id_pregunta
                    FROM salle_preguntas_evaluacion e
                    LEFT JOIN salle_preguntas pe ON pe.id_pregunta = e.id_pregunta
                    WHERE e.id_evaluacion = '$id_evaluacion_pr_completas'
                    AND pe.id_asignatura = '$id_asignatura'
                    AND e.id_pregunta NOT IN ($idsYaRegistrados_str)
                    LIMIT $preguntas_faltantes
                ");

                // Insertar las preguntas faltantes con calificación 0
                foreach($preguntasParaAgregar as $pfa){
                    //validar si ya existe la respuesta antes de insertar (para evitar duplicados)
                    $validarExistencia = DB::SELECT("SELECT * FROM salle_preguntas_evaluacion
                        WHERE id_evaluacion = ?
                        AND id_pregunta = ?
                        ", [$id_evaluacion_rellenar, $pfa->id_pregunta]);
                    if(count($validarExistencia) > 0){
                        continue; //si ya existe continuar al siguiente
                    }
                    $preguntaEvaluacion = new SallePreguntasEvaluacion();
                    $preguntaEvaluacion->id_evaluacion = $id_evaluacion_rellenar;
                    $preguntaEvaluacion->id_pregunta = $pfa->id_pregunta;
                    $preguntaEvaluacion->calificacion_final = 0;
                    $preguntaEvaluacion->save();
                }
            }


            //===PUNTAJE FINAL===
            $totalPuntajeSet = 0;
            $totalPuntajeEvaluacion = 0;
            $calificacionFinal = 0;

            //actualizar salle_evaluaciones calificacion_total
            $getPuntajeRespuestas = DB::SELECT("SELECT SUM(e.calificacion_final) AS totalRespuestas FROM salle_preguntas_evaluacion e
            WHERE e.id_evaluacion = ?", [$id_evaluacion_rellenar]);

            $totalPuntajeSet  = !empty($getPuntajeRespuestas) ? $getPuntajeRespuestas[0]->totalRespuestas : 0;
            //total puntaje evaluacion
            $getPuntajeEvaluacion = DB::SELECT("SELECT sum(pe.puntaje_pregunta) as totalPuntajeEvaluacion FROM salle_preguntas_evaluacion e
            LEFT JOIN salle_preguntas pe ON pe.id_pregunta = e.id_pregunta
            WHERE e.id_evaluacion = ?
            ;", [$id_evaluacion_rellenar]);
            $totalPuntajeEvaluacion = !empty($getPuntajeEvaluacion) ? $getPuntajeEvaluacion[0]->totalPuntajeEvaluacion : 0;
            //calificacion final total puntaje respuestas / total puntaje evaluacion * 100
            if($totalPuntajeEvaluacion > 0){
                $calificacionFinal = ($totalPuntajeSet / $totalPuntajeEvaluacion) * 100;
            }
            // CREAR LA EVALUACION SI NO EXISTE
            $evaluacionExistente = DB::SELECT("SELECT * FROM salle_evaluaciones WHERE id_evaluacion = ?", [$id_evaluacion_rellenar]);
            if(empty($evaluacionExistente)){
                DB::table('salle_evaluaciones')->insert([
                    'id_evaluacion'             => $id_evaluacion_rellenar,
                    'id_usuario'                => $id_usuario,
                    'n_evaluacion'              => '7',
                    'intentos'                  => 2,
                    'tipo_calificacion'         => '1',
                    'calificacion_total'        => $calificacionFinal,
                    'estado'                    => '2', // Estado completado
                    'fecha_inicio_evaluacion'   => "2026-02-02 10:10:42",
                    "fecha_fin_evaluacion"      => "2026-02-02 11:48:34",
                    "fecha_limite_evaluacion"   => "2026-02-02 16:10:42",
                    "created_at"                => "2026-02-02 10:10:42",
                    "tipo_finalizacion"         => '1',
                    "updated_at"                => now(),
                ]);
            }else{
                //actualizar salle_evaluaciones calificacion_final
                DB::table('salle_evaluaciones')->where('id_evaluacion', $id_evaluacion_rellenar)
                ->update([
                    'calificacion_total'  => $calificacionFinal,
                    'updated_at'          => now(),
                ]);
            }

        }// end foreach configuracion

         return response()->json([
            'message' => 'Proceso completado exitosamente',
            'completas_procesadas' => count($resultadoFinalCompletas) ?? 0,
            'incompletas_procesadas' => count($resultadoFinalImcompletas) ?? 0
        ]);

    }

    private function runSystemCommand($command)
    {
        $process = new Process(explode(' ', $command));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
    public function saveHistoricoAlcance($id_alcance,$id_pedido,$contrato,$cantidad_anterior,$nueva_cantidad,$user_created,$tipo){
        //vadidate that it's not exists
        $query = DB::SELECT("SELECT * FROM pedidos_alcance_historico h
        WHERE h.alcance_id = '$id_alcance'
        AND h.id_pedido ='$id_pedido'");
        if(empty($query)){
            $historico                      = new PedidoAlcanceHistorico();
            $historico->contrato            = $contrato;
            $historico->id_pedido           = $id_pedido;
            $historico->alcance_id          = $id_alcance;
            $historico->cantidad_anterior   = $cantidad_anterior;
            $historico->nueva_cantidad      = $nueva_cantidad;
            $historico->user_created        = $user_created;
            $historico->tipo                = $tipo;
            $historico->save();
        }
    }
    public function get_val_pedidoInfo_alcance($pedido,$alcance){
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*,
        p.descuento, p.id_periodo,
        p.anticipo, p.comision, CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
        se.nombre_serie
        FROM pedidos_val_area pv
        left join area ar ON  pv.id_area = ar.idarea
        left join series se ON pv.id_serie = se.id_serie
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        WHERE pv.id_pedido = '$pedido'
        AND pv.alcance = '$alcance'
        GROUP BY pv.id;
        ");
        $datos = [];
        foreach($val_pedido as $key => $item){
            $valores = [];
            //plan lector
            if($item->plan_lector > 0 ){
                $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,
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
                WHERE l.idlibro = '$item->plan_lector'
                ");
                $valores = $getPlanlector;
            }else{
                $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,
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
                WHERE ls.id_serie = '$item->id_serie'
                AND a.area_idarea  = '$item->id_area'
                AND l.Estado_idEstado = '1'
                AND a.estado = '1'
                AND ls.year = '$item->year'
                LIMIT 1
                ");
                $valores = $getLibros;
            }
            $datos[$key] = [
                "id"                => $item->id,
                "id_pedido"         => $item->id_pedido,
                "valor"             => $item->valor,
                "id_area"           => $item->id_area,
                "tipo_val"          => $item->tipo_val,
                "id_serie"          => $item->id_serie,
                "year"              => $item->year,
                "anio"              => $valores[0]->year,
                "version"           => $valores[0]->version,
                "created_at"        => $item->created_at,
                "updated_at"        => $item->updated_at,
                "descuento"         => $item->descuento,
                "anticipo"          => $item->anticipo,
                "comision"          => $item->comision,
                "plan_lector"       => $item->plan_lector,
                "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                "idlibro"           => $valores[0]->idlibro,
                "nombrelibro"       => $valores[0]->nombrelibro,
                "precio"            => $valores[0]->precio,
                "subtotal"          => $item->valor * $valores[0]->precio,
                "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
            ];
        }
        return $datos;
    }
    public function traerPeriodo($institucion_id){
        $periodoInstitucion = DB::SELECT("SELECT idperiodoescolar AS periodo , periodoescolar AS descripcion FROM periodoescolar WHERE idperiodoescolar = (
            SELECT  pir.periodoescolar_idperiodoescolar as id_periodo
            from institucion i,  periodoescolar_has_institucion pir
            WHERE i.idInstitucion = pir.institucion_idInstitucion
            AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
            WHERE phi.institucion_idInstitucion = i.idInstitucion
            AND i.idInstitucion = '$institucion_id'))
        ");
        if(count($periodoInstitucion)>0){
            return ["status" => "1", "message"=>"correcto","periodo" => $periodoInstitucion];
        }else{
            return ["status" => "0", "message"=>"no hay periodo"];
        }
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }
    public function getAlcanceAbiertoXId($id){
        $query = DB::SELECT("SELECT * FROM pedidos_alcance a
        WHERE a.id = '$id'
        AND a.estado_alcance = '1'");
        return $query;
    }

    public function guardarData(Request $request){

        // set_time_limit(6000);
        // ini_set('max_execution_time', 600000);





        // $contadorSolinfaGONZALEZ = 0;
        // $contadorSolinfaCOBACANGO= 0;
        // $resultsGonzales = DB::connection('mysql2')->select('SELECT * FROM product p
        //  WHERE p.id_perseo_gonzales IS NULL
        // limit 90');
        // $resultsCobacango = DB::connection('mysql2')->select('SELECT * FROM product p
        // WHERE p.id_perseo_cobacango IS NULL
        // limit 90');
        // //GONZALES
        // foreach($resultsGonzales as $key => $item){
        //         $formData = [
        //             "productocodigo"=> $item->barcode,
        //         ];
        //         $url                        = "productos_consulta";
        //         $processSolinfa             = $this->tr_SolinfaPost($url, $formData,1);
        //         $getContador                = $this->guardarIdProductoSolinfa($processSolinfa,$item->barcode,"id_perseo_gonzales");
        //         $contadorSolinfaGONZALEZ    = $contadorSolinfaGONZALEZ + $getContador;
        // }
        // //COBACANGO
        // foreach($resultsCobacango as $key => $item){
        //     $formData = [
        //         "productocodigo"=> $item->barcode,
        //     ];
        //     $url                        = "productos_consulta";
        //     $processSolinfa             = $this->tr_SolinfaPost($url, $formData,2);
        //     $getContador                = $this->guardarIdProductoSolinfa($processSolinfa,$item->barcode,"id_perseo_cobacango");
        //     $contadorSolinfaCOBACANGO    = $contadorSolinfaCOBACANGO + $getContador;
        // }
        // return ["contadorSolinfaGONZALEZ" => $contadorSolinfaGONZALEZ, "contadorSolinfaCOBACANGO" => $contadorSolinfaCOBACANGO];


        // try {
        //     $contadorProlipa = 0;
        //     $contadorCalmed  = 0;

        //     // $queryProlipa = DB::SELECT("SELECT * FROM 1_4_cal_producto p
        //     // WHERE p.pro_codigo  = 'IKAD';
        //     // ");
        //     // //PROLIPA
        //     // // $queryProlipa = DB::SELECT("SELECT * FROM 1_4_cal_producto p
        //     // // WHERE p.id_perseo_prolipa_produccion IS NULL
        //     // // LIMIT 90
        //     // // ");
        //     // foreach($queryProlipa as $key => $item){
        //     //     $formData = [
        //     //         "productocodigo"=> $item->pro_codigo,
        //     //     ];
        //     //     $url                = "productos_consulta";
        //     //     $processProlipa     = $this->tr_PerseoPost($url, $formData,1);
        //     //     return $processProlipa;
        //     //     $getContador        = $this->guardarIdProducto($processProlipa,$item->pro_codigo,"id_perseo_prolipa_produccion");
        //     //     //contadorProlipa + getContador
        //     //     $contadorProlipa    = $contadorProlipa + $getContador;
        //     // }
        //     //CALMED
        //     $queryCalmed = DB::SELECT("SELECT * FROM 1_4_cal_producto p
        //     WHERE p.pro_codigo  = 'SMLL2';
        //     ");
        //     // $queryCalmed = DB::SELECT("SELECT * FROM 1_4_cal_producto p
        //     // WHERE p.id_perseo_calmed_produccion IS NULL
        //     // LIMIT 100
        //     // ");
        //     foreach($queryCalmed as $key => $item){
        //         $formData = [
        //             "productocodigo"=> $item->pro_codigo,
        //         ];
        //         $url                = "productos_consulta";
        //         $processCalmed      = $this->tr_PerseoPost($url, $formData,1);
        //         return $processCalmed;
        //         $getContador        = $this->guardarIdProducto($processCalmed,$item->pro_codigo,"id_perseo_calmed_produccion");
        //         //contadorCalmed + getContador
        //         $contadorCalmed     = $contadorCalmed + $getContador;
        //     }
        //     return ["contadorProlipa" => $contadorProlipa,"contadorCalmed" => $contadorCalmed];
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'error' => $e->getMessage()
        //     ], 500);
        // }
    }

    public function guardarIdProductoSolinfa($process,$pro_codigo,$campoPerseo){
        $contador = 0;
        $datos    = [];
        if(isset($process["productos"])){
            $idPerseo = $process["productos"][0]["productosid"];
            $datos = [ $campoPerseo => $idPerseo ];
            $contador = 1;
        }else{
            $datos = [ $campoPerseo => 0 ];
            $contador = 0;
        }
        DB::connection('mysql2')
            ->table('product')
            ->where('barcode', $pro_codigo)
            ->update($datos);
        return $contador;
    }
    public function hijosConvenio($idConvenio){
        $query = DB::SELECT("SELECT * FROM 1_4_documento_liq l
        WHERE l.pedidos_convenios_id = ?
        AND l.estado = '1'
        AND l.tipo_pago_id = '4'
        ",[$idConvenio]);
        return $query;
    }
    public function crearCapacitadores($request,$arreglo){
        $datos = json_decode($request->capacitadores);
        //eliminar si ya han quitado al capacitador
        $getCapacitadores = $this->getCapacitadoresXCapacitacion($arreglo->id_seminario);
        if(sizeOf($getCapacitadores) > 0){
            foreach($getCapacitadores as $key => $item){
                $capacitador        = "";
                $capacitador        = $item->idusuario;
                $searchCapacitador  = collect($datos)->filter(function ($objeto) use ($capacitador) {
                    // Condición de filtro
                    return $objeto->idusuario == $capacitador;
                });
                if(sizeOf($searchCapacitador) == 0){
                    DB::DELETE("DELETE FROM seminarios_capacitador
                      WHERE seminario_id = '$arreglo->id_seminario'
                      AND idusuario = '$capacitador'
                    ");
                }
            }
        }
        //guardar los capacitadores
        foreach($datos as $key => $item){
            $query = DB::SELECT("SELECT * FROM seminarios_capacitador c
            WHERE c.idusuario = '$item->idusuario'
            AND c.seminario_id = '$arreglo->id_seminario'");
            if(empty($query)){
                $capacitador = new SeminarioCapacitador();
                $capacitador->idusuario      = $item->idusuario;
                $capacitador->seminario_id   = $arreglo->id_seminario;
                $capacitador->save();
            }
        }
    }

    public function guardarContratoTemporada($contrato,$institucion,$asesor_id,$temporadas,$periodo,$ciudad,$asesor,$cedulaAsesor,$nombreDocente,$cedulaDocente,$nombreInstitucion){
        //validar que el contrato no existe
        $validate = DB::SELECT("SELECT * FROM temporadas t
        WHERE t.contrato = '$contrato'
        ");
        if(empty($validate)){
            $temporada = new Temporada();
            $temporada->contrato                = $contrato;
            $temporada->year                    = date("Y");
            $temporada->ciudad                  = $ciudad;
            $temporada->temporada               = $temporadas;
            $temporada->id_asesor               = $asesor_id;
            $temporada->cedula_asesor           = 0;
            $temporada->id_periodo              = $periodo;
            $temporada->id_profesor             = "0";
            $temporada->idInstitucion           = $institucion;
            $temporada->temporal_nombre_docente = $nombreDocente;
            $temporada->temporal_cedula_docente = $cedulaDocente;
            $temporada->temporal_institucion    = $nombreInstitucion;
            $temporada->nombre_asesor           = $asesor;
            $temporada->cedula_asesor           = $cedulaAsesor;
            $temporada->save();
            return $temporada;
        }else{
            $id_temporada                       = $validate[0]->id_temporada;
            $temporada                          = Temporada::findOrFail($id_temporada);
            $temporada->id_periodo              = $periodo;
            $temporada->idInstitucion           = $institucion;
            $temporada->id_asesor               = $asesor_id;
            $temporada->temporal_nombre_docente = $nombreDocente;
            $temporada->temporal_cedula_docente = $cedulaDocente;
            $temporada->temporal_institucion    = $nombreInstitucion;
            $temporada->nombre_asesor           = $asesor;
            $temporada->cedula_asesor           = $cedulaAsesor;
            $temporada->save();
            return $temporada;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function show(Admin $admin)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function edit(Admin $admin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Admin $admin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function destroy(Admin $admin)
    {
        //
    }

    // Consultas para administrador
    public function cant_user(){
        $cantidad = DB::SELECT("SELECT id_group, COUNT(id_group) as cantidad FROM usuario WHERE estado_idEstado =1  GROUP BY id_group");
        return $cantidad;
    }
    public function cant_cursos(){
        $cantidad = DB::SELECT("SELECT estado, COUNT(estado) as cantidad FROM curso  GROUP BY estado");
        return $cantidad;
    }
    public function cant_codigos(){
        return DB::table('codigoslibros')
             ->where('idusuario', '>', 0)
             ->count();
    }
    public function cant_codigostotal(){
        // $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM codigoslibros");
        return DB::table('codigoslibros')->count();
    }
    public function cant_evaluaciones(){
        $cantidad = DB::SELECT("SELECT estado, COUNT(estado) as cantidad FROM evaluaciones  GROUP BY estado");
        return $cantidad;
    }
    public function cant_preguntas(){
        $cantidad = DB::SELECT("SELECT id_tipo_pregunta, COUNT(id_tipo_pregunta) as cantidad FROM preguntas  GROUP BY id_tipo_pregunta");
        return $cantidad;
    }
    public function cant_multimedia(){
        $cantidad = DB::SELECT("SELECT tipo, COUNT(tipo) as cantidad FROM actividades_animaciones  GROUP BY tipo");
        return $cantidad;
    }
    public function cant_juegos(){
        // $cantidad = DB::SELECT("SELECT jj.id_tipo_juego, COUNT(jj.id_tipo_juego) as cantidad , jt.nombre_tipo_juego FROM j_juegos jj INNER JOIN j_tipos_juegos jt ON jj.id_tipo_juego = jt.id_tipo_juego GROUP BY jt.id_tipo_juego GROUP BY jj.id_tipo_juego");

        $cantidad = DB::table('j_juegos')
        ->join('j_tipos_juegos', 'j_tipos_juegos.id_tipo_juego','=','j_juegos.id_tipo_juego')
        ->select('j_tipos_juegos.nombre_tipo_juego', DB::raw('count(*) as cantidad'))
        ->groupBy('j_tipos_juegos.nombre_tipo_juego')
        ->get();
        return $cantidad;
    }
    public function cant_seminarios(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM seminario  WHERE estado=1");
        return $cantidad;
    }
    public function cant_encuestas(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM encuestas_certificados");
        return $cantidad;
    }
    public function cant_institucion(){
        $cantidad = DB::SELECT("SELECT DISTINCT COUNT(*) FROM institucion i, periodoescolar p, periodoescolar_has_institucion pi WHERE  i.idInstitucion = pi.institucion_idInstitucion AND pi.periodoescolar_idperiodoescolar = p.idperiodoescolar AND p.estado = 1 GROUP BY i.region_idregion");
        return $cantidad;
    }

    public function get_periodos_activos(){
        $periodos = DB::SELECT("SELECT * FROM periodoescolar p WHERE p.estado = '1' ORDER BY p.idperiodoescolar DESC;");
        return $periodos;
    }
    public function get_periodos_pedidos(){
        $periodos = DB::SELECT("SELECT * FROM periodoescolar p ORDER BY p.idperiodoescolar DESC  ");
        return $periodos;
    }

    public function get_asesores(){
        $asesores = DB::SELECT("SELECT `idusuario`, CONCAT(`nombres`,' ',`apellidos`) AS nombres, `cedula` FROM `usuario` WHERE `estado_idEstado` = '1' AND `id_group` = '5';");
        return $asesores;
    }
    public function get_asesor(){
        $asesores = DB::SELECT("SELECT `idusuario`, `iniciales`, CONCAT(`nombres`,' ',`apellidos`) AS nombres, `cedula` FROM `usuario` WHERE `estado_idEstado` = '1' AND `id_group` = '11';");
        return $asesores;
    }
    public function reporte_asesores(){

        $fecha_fin    = date("Y-m-d");
        $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 7 days"));

        $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");

        $email = 'mcalderonmediavilla@hotmail.com';
        $emailCC = 'reyesjorge10@gmail.com';
        $reporte = 'Reporte asesores';

        $envio = Mail::send('plantilla.reporte_asesores',
            [
                'fecha_fin'    => $fecha_fin,
                'fecha_inicio' => $fecha_inicio,
                'agendas'      => $agendas,
            ],
            function ($message) use ($email, $emailCC, $reporte) {
                $message->from('reportesgerencia@prolipadigital.com.ec', $reporte);
                $message->to($email)->bcc($emailCC)->subject('Agenda de asesores');
            }
        );
    }

    // public function reporte_asesores_view($periodo, $fecha_inicio, $fecha_fin){

    //     if( $periodo != 'null' ){
    //         $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND p.idperiodoescolar = $periodo ORDER BY u.cedula;");
    //     }else{
    //         if( $fecha_inicio != 'null' ){
    //             $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");
    //         }else{

    //             $fecha_fin    = date("Y-m-d");
    //             $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));

    //             $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");
    //         }
    //     }

    //     return $agendas;

    // }


    public function reporte_asesores_view($fecha_inicio, $fecha_fin,$periodo){

        // if( $periodo != 'null' ){
        //     $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.periodo_id = $periodo order BY u.nombres");
        // }else{
        //     if( $fecha_inicio != 'null' ){
        //         $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin' order BY u.nombres");
        //     }else{
        //         $fecha_fin    = date("Y-m-d");
        //         $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));
        //         $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin' order BY u.nombres");
        //     }
        // }
        if( $periodo != 'null' ){
            $visitas = DB::SELECT("CALL `pr_reporteVisitasInstitucionxPeriodo`('$periodo');
                ");
            $visitasITemporal = DB::SELECT("CALL pr_reporteVisitasInstitucionTemporalxPeriodo('$periodo')");
                return [
                    "visitasInstitucion" => $visitas,
                    "visitasInstitucionTemporal" => $visitasITemporal
                 ];
        }else{
            if($fecha_inicio != 'null' ){
                $visitas = DB::SELECT("CALL `pr_reporteVisitasInstitucion`('$fecha_inicio','$fecha_fin');
                ");
                $visitasITemporal = DB::SELECT("CALL pr_reporteVisitasInstitucionTemporal('$fecha_inicio', '$fecha_fin')");
                return [
                    "visitasInstitucion" => $visitas,
                    "visitasInstitucionTemporal" => $visitasITemporal
                ];
            }
        }

        return $agendas;

    }

    public function get_estadisticas_asesor_inst($periodo, $fecha_inicio, $fecha_fin){

        if( $periodo != 'null' ){
            $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
            FROM seguimiento_cliente s
            INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
            WHERE s.periodo_id = $periodo AND s.fecha_genera_visita
            GROUP BY i.idInstitucion;");
        }else{
            if( $fecha_inicio != 'null' ){
                $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
                FROM seguimiento_cliente s
                INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
                WHERE s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
                GROUP BY i.idInstitucion;");
            }else{

                $fecha_fin    = date("Y-m-d");
                $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));

                $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
                FROM seguimiento_cliente s
                INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
                WHERE s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
                GROUP BY i.idInstitucion;");
            }
        }

        return $visitas;

    }
    //api:get/>>https://apitest.prolipadigital.com.ec/producto_reserva/SM1/5
    public function producto_reserva($producto,$cantidad){
        return $this->updateProducto($producto,$cantidad,"pro_reservar");
    }
    //api:get/>>https://apitest.prolipadigital.com.ec/producto_factura_prolipa/SM1/6
    public function producto_factura_prolipa($producto,$cantidad){
        return $this->updateProducto($producto,$cantidad,"pro_stock");
    }
    //api:get/>>https://apitest.prolipadigital.com.ec/producto_nota_prolipa/SM1/7
    public function producto_nota_prolipa($producto,$cantidad){
        return $this->updateProducto($producto,$cantidad,"pro_deposito");
    }
    //api:get/>>https://apitest.prolipadigital.com.ec/producto_factura_calmed/SM1/8
    public function producto_factura_calmed($producto,$cantidad){
        return $this->updateProducto($producto,$cantidad,"pro_stockCalmed");
    }
     //api:get/>>https://apitest.prolipadigital.com.ec/producto_nota_calmed/SM1/9
     public function producto_nota_calmed($producto,$cantidad){
        return $this->updateProducto($producto,$cantidad,"pro_depositoCalmed");
    }



    public function updateProducto($producto,$cantidad,$parametro1){
        DB::table('1_4_cal_producto')
        ->where("pro_codigo",$producto)
        ->update([$parametro1 => $cantidad]);
        $resultado =_14Producto::where('pro_codigo',$producto)->get();
        return $resultado;
    }

    // METODOS JEYSON INICIO
    public function Post_ActualizarPorcentaje_Venta(Request $request)
    {
        // Iniciar una transacción para garantizar la integridad de los datos
        DB::beginTransaction();

        try {
            // Buscar el registro de venta basado en id_empresa y ven_codigo
            $venta = DB::table('f_venta')
                    ->where('id_empresa', $request->id_empresa)
                    ->where('ven_codigo', $request->ven_codigo)
                    ->first();

            if (!$venta) {
                // Si no se encuentra el registro, devolver un error
                DB::rollback();
                return response()->json(["status" => "0", 'message' => 'Venta no encontrada'], 404);
            }

            // Buscar la proforma asociada a la venta
            $proforma = DB::table('f_proforma')
            ->where('emp_id', $venta->id_empresa)
            ->where('prof_id', $venta->ven_idproforma)
            ->first();

            if (!$proforma) {
                DB::rollback();
                // Si no se encuentra la proforma, devolver un error
                return response()->json(["status" => "0", 'message' => 'Proforma no encontrada'], 404);
            }

            // 3️⃣ Validar si existen devoluciones para este documento (venta)
            $devoluciones = DB::table('codigoslibros_devolucion_son as s')
                ->leftJoin('codigoslibros_devolucion_header as h', 'h.id', '=', 's.codigoslibros_devolucion_id')
                ->where('s.documento', $request->ven_codigo)
                ->where('s.estado', '2')
                ->where('h.estado', '<>', '3')
                ->get();

            if ($devoluciones->count() > 0) {
                DB::rollback();
                $devolucionesDevueltas = DB::SELECT("SELECT DISTINCT  h.codigo_devolucion
                FROM codigoslibros_devolucion_son s
                LEFT JOIN codigoslibros_devolucion_header h ON h.id = s.codigoslibros_devolucion_id
                WHERE s.documento = '$request->ven_codigo'
                AND s.id_empresa = '$request->id_empresa'
                AND h.estado <> '3'
                ");
                // SPLIT PARA SEPARAR LOS CODIGOS DE DEVOLUCION
                $devolucionesArray = [];
                foreach($devolucionesDevueltas as $devolucion){
                    $devolucionesArray[] = $devolucion->codigo_devolucion;
                }
                return response()->json([
                    "status" => "0",
                    "message" => "No se puede cambiar el porcentaje porque existen devoluciones asociadas al documento: " . implode(", ", $devolucionesArray)
                ]);
            }

            // Actualizar el campo pro_des_por en la tabla f_proforma
            DB::table('f_proforma')
                ->where('emp_id', $venta->id_empresa)
                ->where('prof_id', $venta->ven_idproforma)
                ->update(['pro_des_por' => $request->ven_desc_por]);

            // Actualizar el campo ven_desc_por en la tabla f_venta
            DB::table('f_venta')
                ->where('id_empresa', $request->id_empresa)
                ->where('ven_codigo', $request->ven_codigo)
                ->update(['ven_desc_por' => $request->ven_desc_por]);

            // Llamar a ActualizarPorcentajeRepository si es necesario
            // DB::rollback();
            $this->ActualizarPorcentajeRepository($request);

            // Confirmar la transacción
            DB::commit();

            return response()->json(["status" => "1", 'message' => 'Registro actualizado correctamente']);
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción
            DB::rollback();
            return response()->json(["status" => "0", 'message' => 'Error al actualizar los datos: ' . $e->getMessage()], 500);
        }
    }
    public function ActualizarPorcentajeRepository(Request $request)
    {
        // Obtener los valores de la solicitud
        $codigo_proforma = $request->ven_codigo;
        $codigo_empresa = $request->id_empresa;

        // Llamar al repositorio para actualizar los valores
        $this->devolucionRepository->updateValoresDocumentoF_venta($codigo_proforma, $codigo_empresa);
        $this->devolucionRepository->updateValoresDocumentoF_proforma($codigo_proforma, $codigo_empresa);

        return "se actualizó";
    }
    // METODOS JEYSON FIN
    public function limpiarCeroParallenarIdsPerseo(){
        try {
            //LIMPIAR LOS CEROS
            DB::table('1_4_cal_producto')
            ->where('id_perseo_prolipa_produccion', 0)
            ->whereIn('gru_pro_codigo', ['1', '2'])
            ->update(['id_perseo_prolipa_produccion' => NULL]);

            DB::table('1_4_cal_producto')
            ->where('id_perseo_calmed_produccion', 0)
            ->whereIn('gru_pro_codigo', ['1', '2'])
            ->update(['id_perseo_calmed_produccion' => NULL]);


            return "Se limpiaron a los ceros a null para proceder a llenar los ids de Perseo";
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function llenarIdsPerseo(){
        try {
            $contadorProlipa = 0;
            $contadorCalmed  = 0;
            $arrayProblemasProlipa  = [];
            $arrayProblemasCalmed  = [];

            //PROLIPA
            $queryProlipa = DB::SELECT("SELECT * FROM 1_4_cal_producto p
            WHERE p.id_perseo_prolipa_produccion IS NULL
            AND  (p.gru_pro_codigo = '1' OR p.gru_pro_codigo = '2')
            LIMIT 25
            ");
            foreach($queryProlipa as $key => $item){
                $formData = [
                    "productocodigo"=> $item->pro_codigo,
                ];
                $url                = "productos_consulta";
                $processProlipa     = $this->tr_PerseoPost($url, $formData,1);
                if(isset($processProlipa["informacion"])){
                    array_push($arrayProblemasProlipa,["pro_codigo" => $item->pro_codigo,"message" => 'No encontrado en perseo empresa Prolipa']);
                    continue;
                }
                $getContador        = $this->guardarIdProducto($processProlipa,$item->pro_codigo,"id_perseo_prolipa_produccion");
                //contadorProlipa + getContador
                $contadorProlipa    = $contadorProlipa + $getContador;
            }
            //CALMED
            $queryCalmed = DB::SELECT("SELECT * FROM 1_4_cal_producto p
            WHERE p.id_perseo_calmed_produccion IS NULL
            AND  (p.gru_pro_codigo = '1' OR p.gru_pro_codigo = '2')
            LIMIT 25
            ");
            foreach($queryCalmed as $key => $item){
                $formData = [
                    "productocodigo"=> $item->pro_codigo,
                ];
                $url                = "productos_consulta";
                $processCalmed      = $this->tr_PerseoPost($url, $formData,3);
                if(isset($processCalmed["informacion"])){
                    array_push($arrayProblemasCalmed,["pro_codigo" => $item->pro_codigo,"message" => 'No encontrado en perseo empresa Calmed']);
                    continue;
                }
                $getContador        = $this->guardarIdProducto($processCalmed,$item->pro_codigo,"id_perseo_calmed_produccion");
                //contadorCalmed + getContador
                $contadorCalmed     = $contadorCalmed + $getContador;
            }
            return ["contadorProlipa" => $contadorProlipa, "arrayProblemasProlipa" => $arrayProblemasProlipa, "contadorCalmed" => $contadorCalmed, "arrayProblemasCalmed" => $arrayProblemasCalmed];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function llenarIdsPerseoxCodigo($codigo){
        try {
            $contadorProlipa = 0;
            $contadorCalmed  = 0;
            $arrayProblemasProlipa  = [];
            $arrayProblemasCalmed  = [];

            //PROLIPA
            $queryProlipa = DB::SELECT("
                SELECT *
                FROM 1_4_cal_producto p
                WHERE (p.id_perseo_prolipa_produccion IS NULL OR p.id_perseo_prolipa_produccion = 0)
                AND p.pro_codigo = '$codigo'
            ");
            foreach($queryProlipa as $key => $item){
                $formData = [
                    "productocodigo"=> $item->pro_codigo,
                ];
                $url                = "productos_consulta";
                $processProlipa     = $this->tr_PerseoPost($url, $formData,1);
                if(isset($processProlipa["informacion"])){
                    array_push($arrayProblemasProlipa,["pro_codigo" => $item->pro_codigo,"message" => 'No encontrado en perseo empresa Prolipa']);
                    continue;
                }
                $getContador        = $this->guardarIdProducto($processProlipa,$item->pro_codigo,"id_perseo_prolipa_produccion");
                //contadorProlipa + getContador
                $contadorProlipa    = $contadorProlipa + $getContador;
            }
            //CALMED
            $queryCalmed = DB::SELECT("
                SELECT *
                FROM 1_4_cal_producto p
                WHERE (p.id_perseo_calmed_produccion IS NULL OR p.id_perseo_calmed_produccion = 0)
                AND p.pro_codigo = '$codigo'
            ");

            foreach($queryCalmed as $key => $item){
                $formData = [
                    "productocodigo"=> $item->pro_codigo,
                ];
                $url                = "productos_consulta";
                $processCalmed      = $this->tr_PerseoPost($url, $formData,3);
                if(isset($processCalmed["informacion"])){
                    array_push($arrayProblemasCalmed,["pro_codigo" => $item->pro_codigo,"message" => 'No encontrado en perseo empresa Calmed']);
                    continue;
                }
                $getContador        = $this->guardarIdProducto($processCalmed,$item->pro_codigo,"id_perseo_calmed_produccion");
                //contadorCalmed + getContador
                $contadorCalmed     = $contadorCalmed + $getContador;
            }
            return ["contadorProlipa" => $contadorProlipa, "arrayProblemasProlipa" => $arrayProblemasProlipa, "contadorCalmed" => $contadorCalmed, "arrayProblemasCalmed" => $arrayProblemasCalmed];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
     public function guardarIdProducto($process,$pro_codigo,$campoPerseo){
        $contador = 0;
        $datos    = [];
        if(isset($process["productos"])){
            $idPerseo = $process["productos"][0]["productosid"];
            $datos = [ $campoPerseo => $idPerseo ];
            $contador = 1;
        }else{
            $datos = [ $campoPerseo => 0 ];
            $contador = 0;
        }
        DB::table('1_4_cal_producto')
        ->where('pro_codigo',$pro_codigo)
        ->update($datos);
        return $contador;
    }



    public function llenarIdsPerseoxCodigoXEmpresa($codigo, $empresa)
    {
        try {
            $estado = 0; // 0 = sin cambios, 1 = éxito, 2 = error o fallo
            $mensaje = '';

            $campoPerseo = '';
            $tipoEmpresa = 0;

            if ($empresa == 'prolipa') {
                $campoPerseo = 'id_perseo_prolipa_produccion';
                $tipoEmpresa = 1;
            } elseif ($empresa == 'calmed') {
                $campoPerseo = 'id_perseo_calmed_produccion';
                $tipoEmpresa = 3;
            } else {
                return response()->json([
                    'estado' => 2,
                    'mensaje' => 'Empresa no válida'
                ], 400);
            }

            // Buscar producto sin ID Perseo
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

            // Consultar en Perseo
            $formData = [
                "productocodigo" => $producto->pro_codigo,
            ];
            $url = "productos_consulta";
            $response = $this->tr_PerseoPost($url, $formData, $tipoEmpresa);

            // Validar si Perseo no devolvió datos
            if (isset($response["informacion"]) && $response["informacion"] === false) {
                return response()->json([
                    'estado' => 2,
                    'mensaje' => "El producto {$producto->pro_codigo} no se encuentra en Perseo"
                ]);
            }

            // Guardar el ID retornado (si existe)
            $resultado = $this->guardarIdProductoPerseo($response, $producto->pro_codigo, $campoPerseo);

            if ($resultado === 1) {
                return response()->json([
                    'estado' => 1,
                    'mensaje' => 'Actualización exitosa'
                ]);
            } else {
                return response()->json([
                    'estado' => 2,
                    'mensaje' => 'No se obtuvo ID válido desde Perseo'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'estado' => 2,
                'mensaje' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    public function guardarIdProductoPerseo($process, $pro_codigo, $campoPerseo) {
        if (isset($process["productos"]) && isset($process["productos"][0]["productosid"])) {
            $idPerseo = $process["productos"][0]["productosid"];
            DB::table('1_4_cal_producto')
                ->where('pro_codigo', $pro_codigo)
                ->update([$campoPerseo => $idPerseo]);
            return 1; // éxito
        } else {
            DB::table('1_4_cal_producto')
                ->where('pro_codigo', $pro_codigo)
                ->update([$campoPerseo => 0]);
            return 0; // fallo al obtener ID
        }
    }
 public function Post_ActualizarPorcentaje_VentaActa(Request $request)
    {
        // Iniciar una transacción para garantizar la integridad de los datos
        DB::beginTransaction();
        try {
            // Buscar el registro de venta basado en id_empresa y ven_codigo
            $venta = DB::table('f_venta')
                    ->where('id_empresa', $request->id_empresa)
                    ->where('ven_codigo', $request->ven_codigo)
                    ->first();

            if (!$venta) {
                // Si no se encuentra el registro, devolver un error
                return response()->json(["status" => "0", 'message' => 'Venta no encontrada'], 404);
            }

            // Buscar la proforma asociada a la venta
            $actas = DB::table('p_libros_obsequios')
            ->where('id', $venta->ven_p_libros_obsequios)
            ->first();

            if (!$actas) {
                // Si no se encuentra la proforma, devolver un error
                return response()->json(["status" => "0", 'message' => 'Actas o nota no encontrada'], 404);
            }

            // Actualizar el campo pro_des_por en la tabla f_proforma
            DB::table('p_libros_obsequios')
                ->where('id', $venta->ven_p_libros_obsequios)
                ->update(['porcentaje_descuento' => $request->ven_desc_por]);

            // Actualizar el campo ven_desc_por en la tabla f_venta
            DB::table('f_venta')
                ->where('id_empresa', $request->id_empresa)
                ->where('ven_codigo', $request->ven_codigo)
                ->update(['ven_desc_por' => $request->ven_desc_por]);

            // Llamar a ActualizarPorcentajeRepository si es necesario
            // DB::rollback();
            $this->devolucionRepository->updateValoresDocumentoF_venta($venta->ven_codigo, $venta->id_empresa);

            // Confirmar la transacción
            DB::commit();

            return response()->json(["status" => "1", 'message' => 'Registro actualizado correctamente']);
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción
            DB::rollback();
            return response()->json(["status" => "0", 'message' => 'Error al actualizar los datos: ' . $e->getMessage()], 500);
        }
    }
    public function hola(){
       $query = DB::select("select * from area");
       $query2 = DB::select("select * from nivel");
        return [
            "area" => $query,
            "nivel" => $query2
        ];
    }

    /**
     * Mostrar vista para descargar códigos despachados
     * Route: GET /admin/despachados/vista
     */
    public function mostrarVistaDespachados()
    {
        // Obtener períodos disponibles (incluir descripcion para nombres de archivo)
        $periodos = DB::table('periodoescolar')
                    ->select('idperiodoescolar', 'periodoescolar', 'descripcion')
                    ->where('pedido_facturacion', '=',1)
                    ->orderBy('idperiodoescolar', 'desc')
                    ->get();

        return view('admin.despachados', compact('periodos'));
    }

    /**
     * Descargar comparativa de ventas por años
     * Route: GET /admin/reportes/comparativa-ventas
     */
    public function comparativaVentas(Request $request)
    {
        try {
            // Configuración extendida
            set_time_limit(600);
            ini_set('max_execution_time', 600);
            ini_set('memory_limit', '1G');

            // Validar parámetros
            $anioInicio = $request->input('anio_inicio');
            $anioFin = $request->input('anio_fin');
            $regiones = explode(',', $request->input('regiones', '1,2'));

            if (!$anioInicio || !$anioFin) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Debe especificar año de inicio y fin'
                ], 400);
            }

            // Buscar periodos que coincidan con el rango de años y regiones
            // Usamos una lógica que busca años de 4 dígitos en el nombre del periodo
            $periodos = DB::table('periodoescolar')
                ->whereIn('region_idregion', $regiones)
                ->where(function($query) use ($anioInicio, $anioFin) {
                    $query->whereRaw('CAST(RIGHT(periodoescolar, 4) AS UNSIGNED) BETWEEN ? AND ?', [$anioInicio, $anioFin])
                          ->orWhereRaw('CAST(LEFT(RIGHT(periodoescolar, 5), 4) AS UNSIGNED) BETWEEN ? AND ?', [$anioInicio, $anioFin]);
                })
                ->pluck('idperiodoescolar')
                ->toArray();

            if (empty($periodos)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No se encontraron periodos escolares para el rango de años y regiones seleccionadas',
                    'empty_result' => true
                ], 404);
            }

            // Convertir array de periodos a string separado por comas
            $periodosStr = implode(',', $periodos);

            // Ejecutar stored procedure
            $pdo = DB::getPdo();
            $stmt = $pdo->prepare("CALL sp_comparativa_ventas_resumen(?)");
            $result = $stmt->execute([$periodosStr]);

            if (!$result) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al ejecutar el procedimiento: ' . implode(', ', $stmt->errorInfo())
                ], 500);
            }

            // Obtener todos los resultados
            $datos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (empty($datos)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No hay datos de ventas para los periodos seleccionados',
                    'empty_result' => true
                ], 200);
            }

            // Generar CSV
            $fecha = date('Y-m-d_His');
            $filename = "comparativa_ventas_{$anioInicio}_{$anioFin}_{$fecha}.csv";

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function() use ($datos) {
                $file = fopen('php://output', 'w');

                // BOM para UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                // Encabezados
                if (!empty($datos)) {
                    fputcsv($file, array_keys($datos[0]), ';');
                }

                // Datos
                foreach ($datos as $fila) {
                    fputcsv($file, $fila, ';');
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            \Log::error('Error en comparativaVentas: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar comparativa de liquidados por años con modal de progreso
     * Route: GET /admin/reportes/comparativa-liquidados
     */
    public function comparativaLiquidados(Request $request)
    {
        try {
            // Configuración extendida
            set_time_limit(1800); // 30 minutos
            ini_set('max_execution_time', 1800);
            ini_set('memory_limit', '2G');

            // Validar parámetros
            $anioInicio = $request->input('anio_inicio');
            $anioFin = $request->input('anio_fin');
            $regiones = explode(',', $request->input('regiones', '1,2'));

            if (!$anioInicio || !$anioFin) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Debe especificar año de inicio y fin'
                ], 400);
            }

            // Buscar periodos que coincidan con el rango de años y regiones
            $periodos = DB::table('periodoescolar')
                ->select('idperiodoescolar', 'periodoescolar', 'descripcion')
                ->whereIn('region_idregion', $regiones)
                ->where(function($query) use ($anioInicio, $anioFin) {
                    $query->whereRaw('CAST(RIGHT(periodoescolar, 4) AS UNSIGNED) BETWEEN ? AND ?', [$anioInicio, $anioFin])
                          ->orWhereRaw('CAST(LEFT(RIGHT(periodoescolar, 5), 4) AS UNSIGNED) BETWEEN ? AND ?', [$anioInicio, $anioFin]);
                })
                ->orderBy('idperiodoescolar', 'asc')
                ->get();

            if ($periodos->isEmpty()) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No se encontraron periodos escolares para el rango de años y regiones seleccionadas',
                    'empty_result' => true
                ], 404);
            }

            // Preparar headers para streaming
            $fecha = date('Y-m-d_His');
            $filename = "comparativa_liquidados_{$anioInicio}_{$anioFin}_{$fecha}.csv";

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
                'X-Accel-Buffering' => 'no' // Deshabilitar buffering de Nginx
            ];

            $totalPeriodos = count($periodos);
            $currentPeriodo = 0;
            $encabezadosEscritos = false;

            // Stream de respuesta
            $callback = function() use ($periodos, $totalPeriodos, &$currentPeriodo, &$encabezadosEscritos) {
                // Buffer temporal para CSV
                $csvBuffer = fopen('php://temp', 'r+');

                // BOM para UTF-8
                fprintf($csvBuffer, chr(0xEF).chr(0xBB).chr(0xBF));

                foreach ($periodos as $periodo) {
                    $currentPeriodo++;

                    try {
                        // Extraer año del período (última secuencia de 4 dígitos)
                        preg_match_all('/(\d{4})/', $periodo->periodoescolar, $yearMatches);
                        $anio = !empty($yearMatches[0]) ? end($yearMatches[0]) : 'N/A';

                        // Ejecutar sp_liquidados para cada periodo usando DB::select para mayor estabilidad
                        // Esto evita problemas con PDO statements manuales y cursores
                        $datos = DB::select("CALL sp_liquidadosXanios(?)", [$periodo->idperiodoescolar]);
                        $numRegistros = count($datos);

                        // Enviar mensaje de progreso (separado del CSV)
                        echo "<!-- PROGRESS: " . json_encode([
                            'current' => $currentPeriodo,
                            'total' => $totalPeriodos,
                            'periodo' => $periodo->descripcion,
                            'registros' => $numRegistros
                        ]) . " -->";

                        // Forzar el envío del buffer de salida
                        if (ob_get_level() > 0) ob_flush();
                        flush();

                        if ($numRegistros > 0) {
                            // Convertir objetos a arrays y agregar columna "anio"
                            $filasProcesadas = [];
                            foreach ($datos as $item) {
                                $fila = (array)$item;
                                $fila = array_merge(['anio' => $anio], $fila);
                                $filasProcesadas[] = $fila;
                            }

                            // Escribir encabezados solo una vez
                            if (!$encabezadosEscritos) {
                                fputcsv($csvBuffer, array_keys($filasProcesadas[0]), ';');
                                $encabezadosEscritos = true;
                            }

                            // Escribir datos
                            foreach ($filasProcesadas as $fila) {
                                fputcsv($csvBuffer, $fila, ';');
                            }

                            // Si el buffer temporal es muy grande, enviarlo al cliente y recrearlo
                            // para evitar consumo excesivo de memoria
                            $status = fstat($csvBuffer);
                            if ($status['size'] > 5 * 1024 * 1024) { // 5MB
                                rewind($csvBuffer);
                                fpassthru($csvBuffer);
                                fclose($csvBuffer);
                                $csvBuffer = fopen('php://temp', 'r+');
                            }
                        }

                    } catch (\Exception $e) {
                        \Log::error("Error procesando periodo {$periodo->idperiodoescolar}: " . $e->getMessage());

                        // Enviar mensaje de error
                        echo "<!-- PROGRESS: " . json_encode([
                            'current' => $currentPeriodo,
                            'total' => $totalPeriodos,
                            'periodo' => $periodo->descripcion,
                            'registros' => 0,
                            'error' => $e->getMessage()
                        ]) . " -->";

                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    }
                }

                // Enviar contenido CSV restante
                rewind($csvBuffer);
                fpassthru($csvBuffer);
                fclose($csvBuffer);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            \Log::error('Error en comparativaLiquidados: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Probar el procedimiento almacenado (solo primeros 10 registros)
     * Route: GET /admin/despachados/test/{id_periodo}
     */
    public function testProcedimientoDespachados($id_periodo)
    {
        try {
            // Configuración extendida para evitar timeout
            set_time_limit(600); // 10 minutos
            ini_set('max_execution_time', 600);
            ini_set('memory_limit', '1G');

            // Verificar que el período existe
            $periodo = DB::table('periodoescolar')
                ->where('idperiodoescolar', $id_periodo)
                ->first();

            if (!$periodo) {
                return response()->json([
                    'status' => 0,
                    'message' => "El período $id_periodo no existe"
                ], 404);
            }

            $startTime = microtime(true);

            // Probar solo los primeros registros para evitar timeout completo
            $pdo = DB::getPdo();
            $stmt = $pdo->prepare("CALL sp_despachados(?)");
            $result = $stmt->execute([$id_periodo]);

            if (!$result) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al ejecutar sp_despachados: ' . implode(', ', $stmt->errorInfo())
                ], 500);
            }

            $datos = [];
            $count = 0;
            // Solo obtener 3 registros para la prueba rápida
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) && $count < 3) {
                $datos[] = $row;
                $count++;
            }

            $tiempoTranscurrido = microtime(true) - $startTime;
            $stmt->closeCursor();

            return response()->json([
                'status' => 1,
                'message' => '✅ Procedimiento almacenado funcionando correctamente',
                'periodo' => $periodo->descripcion,
                'muestra_datos' => $datos,
                'estructura_columnas' => !empty($datos) ? array_keys($datos[0]) : [],
                'tiempo_respuesta_segundos' => round($tiempoTranscurrido, 2),
                'estimacion_190k_registros' => 'Basado en HeidiSQL: ~1.30 segundos + procesamiento PHP',
                'timestamp' => date('Y-m-d H:i:s'),
                'siguiente_paso' => 'Proceder con descarga completa usando streaming optimizado'
            ]);

        } catch (\Exception $e) {
            $errorType = 'ERROR_GENERAL';
            $solution = 'Revise logs del servidor';

            if (strpos($e->getMessage(), 'execution time') !== false) {
                $errorType = 'TIMEOUT';
                $solution = 'Aumentar timeout del servidor web (Apache/Nginx)';
            } elseif (strpos($e->getMessage(), 'connection') !== false) {
                $errorType = 'ERROR_CONEXION_BD';
                $solution = 'Verificar conexión a base de datos';
            } elseif (strpos($e->getMessage(), "doesn't exist") !== false) {
                $errorType = 'PROCEDIMIENTO_NO_EXISTE';
                $solution = 'Crear el procedimiento almacenado sp_despachados en la BD';
            }

            return response()->json([
                'status' => 0,
                'message' => 'Error en la prueba: ' . $e->getMessage(),
                'tipo_error' => $errorType,
                'solucion_sugerida' => $solution,
                'debug' => [
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }    /**
     * Descargar códigos despachados en CSV
     * api:get/codigos/despachados/csv/{id_periodo}
     */
    public function descargarDespachados($id_periodo)
    {
        try {
            // Validar que el período existe
            $periodoExists = DB::table('periodoescolar')
                ->where('idperiodoescolar', $id_periodo)
                ->exists();

            if (!$periodoExists) {
                return response()->json([
                    'status' => 0,
                    'message' => "El período $id_periodo no existe en la base de datos"
                ], 404);
            }

            // ⚡ CONFIGURACIÓN AGRESIVA para grandes volúmenes de datos (190k+ registros)
            set_time_limit(0); // Sin límite de tiempo
            ini_set('memory_limit', '4G'); // Aumentar memoria a 4GB
            ini_set('max_execution_time', 0); // Sin límite
            ini_set('max_input_time', 0); // Sin límite
            ini_set('default_socket_timeout', 3600); // 1 hora para socket
            ini_set('mysql.connect_timeout', 3600); // 1 hora para MySQL
            ignore_user_abort(true); // Continuar aunque el usuario cierre navegador

            // Desactivar buffering para streaming directo
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Obtener la descripción del período para el nombre del archivo
            $periodo = DB::table('periodoescolar')
                ->select('descripcion', 'periodoescolar')
                ->where('idperiodoescolar', $id_periodo)
                ->first();

            // Usar descripcion si existe y no está vacía, sino usar periodoescolar como respaldo
            $descripcionPeriodo = ($periodo && !empty($periodo->descripcion))
                ? $periodo->descripcion
                : ($periodo ? $periodo->periodoescolar : $id_periodo);

            // Limpiar la descripción para uso en nombre de archivo
            $descripcionLimpia = preg_replace('/[^A-Za-z0-9\-_]/', '_', $descripcionPeriodo);

            $nombreArchivo = 'codigos_despachados_' . $descripcionLimpia . '_' . date('Y-m-d_H-i-s') . '.csv';

            // Configurar headers optimizados para streaming
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no', // Para nginx
                'Transfer-Encoding' => 'chunked'
            ];

            // Crear el callback para generar el CSV con streaming
            $callback = function() use ($id_periodo) {
                $file = fopen('php://output', 'w');

                // Agregar BOM para UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                try {
                    // Verificar la conexión de base de datos
                    $pdo = DB::getPdo();
                    if (!$pdo) {
                        throw new \Exception('No se pudo obtener conexión PDO');
                    }

                    // Preparar y ejecutar el procedimiento almacenado
                    $stmt = $pdo->prepare("CALL sp_despachados(?)");
                    if (!$stmt) {
                        throw new \Exception('Error al preparar la consulta: ' . implode(', ', $pdo->errorInfo()));
                    }

                    $result = $stmt->execute([$id_periodo]);
                    if (!$result) {
                        throw new \Exception('Error al ejecutar sp_despachados: ' . implode(', ', $stmt->errorInfo()));
                    }

                    $isFirstRow = true;
                    $rowCount = 0;

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        // Escribir cabeceras en la primera fila
                        if ($isFirstRow) {
                            fputcsv($file, array_keys($row), ';');
                            $isFirstRow = false;
                        }

                        // Escribir fila de datos
                        fputcsv($file, array_values($row), ';');
                        $rowCount++;

                        // Liberar memoria cada 1000 registros
                        if ($rowCount % 1000 == 0) {
                            if (ob_get_level()) {
                                ob_flush();
                            }
                            flush();
                        }
                    }

                    // Si no hay datos, escribir mensaje informativo
                    if ($rowCount == 0) {
                        if ($isFirstRow) {
                            fputcsv($file, ['Mensaje'], ';');
                        }
                        fputcsv($file, ["No se encontraron códigos despachados para el período $id_periodo"], ';');
                    }

                    // Agregar línea final con total de registros
                    fputcsv($file, [], ';'); // Línea vacía
                    fputcsv($file, ["Total de registros: $rowCount"], ';');
                    fputcsv($file, ["Generado el: " . date('Y-m-d H:i:s')], ';');

                } catch (\Exception $e) {
                    // Log del error para debugging
                    \Log::error('Error en descargarDespachados: ' . $e->getMessage(), [
                        'id_periodo' => $id_periodo,
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Escribir error en el CSV
                    if ($isFirstRow ?? true) {
                        fputcsv($file, ['Error'], ';');
                    }
                    fputcsv($file, ['Error al procesar datos: ' . $e->getMessage()], ';');
                    fputcsv($file, ['Por favor contacte al administrador del sistema'], ';');
                } finally {
                    if (isset($stmt)) {
                        $stmt->closeCursor();
                    }
                    fclose($file);
                }
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            // Log del error principal
            \Log::error('Error principal en descargarDespachados: ' . $e->getMessage(), [
                'id_periodo' => $id_periodo,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Error al generar el archivo CSV: ' . $e->getMessage(),
                'debug_info' => [
                    'periodo_solicitado' => $id_periodo,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'memoria_usada' => memory_get_usage(true),
                    'limite_memoria' => ini_get('memory_limit')
                ]
            ], 500);
        }
    }    //api:get>llenarIdsPedidosVal?id_periodo=26
   public function llenarIdsPedidosVal(Request $request)
    {
        $id_periodo = $request->id_periodo;

        $query = DB::SELECT("SELECT pv.*
            FROM pedidos_val_area pv
            LEFT JOIN pedidos p ON p.id_pedido = pv.id_pedido
            WHERE p.id_periodo = ?
            AND pv.id_libro IS NULL
            AND p.estado <> '2'
            LIMIT 1000
        ", [$id_periodo]); // ✅ Usa binding en vez de interpolar variables (más seguro)
        // return $query;

        $problemasLibros = [];
        $contador = 0;

        foreach ($query as $item) {
            $id_serie = $item->id_serie;

            // ✅ Caso especial: plan lector
            if ($id_serie == 6) {
                DB::table("pedidos_val_area")
                    ->where("id", $item->id)
                    ->update(["id_libro" => $item->plan_lector]);

                $contador++;
                continue;
            }
            // plus
            if($id_serie == 22){
                $buscarLibro = DB::SELECT("
                SELECT l.*
                FROM libros_series ls
                LEFT JOIN libro l ON l.idlibro = ls.idLibro
                LEFT JOIN asignatura a ON a.idasignatura = l.asignatura_idasignatura
                WHERE ls.id_serie = ?
                AND a.area_idarea = ?
                AND ls.year = ?
                AND l.Estado_idEstado = '1'
            ", [$item->id_serie, $item->id_area, $item->year]);
            }else{
                 // ✅ Buscar libro correspondiente
                $buscarLibro = DB::SELECT("
                    SELECT l.*
                    FROM libros_series ls
                    LEFT JOIN libro l ON l.idlibro = ls.idLibro
                    LEFT JOIN asignatura a ON a.idasignatura = l.asignatura_idasignatura
                    WHERE ls.id_serie = ?
                    AND a.area_idarea = ?
                    AND ls.year = ?
                    AND l.Estado_idEstado = '1'
                    AND l.id_folleto IS NULL
                ", [$item->id_serie, $item->id_area, $item->year]);
            }


            // ✅ Validar resultados
            if (count($buscarLibro) > 0) {
                if (count($buscarLibro) > 1) {
                    $item->mensaje = 'Existe más de un libro';
                    $problemasLibros[] = $item;
                    continue;
                }

                $id_libro = $buscarLibro[0]->idlibro;
                DB::table("pedidos_val_area")
                    ->where("id", $item->id)
                    ->update(["id_libro" => $id_libro]);
                $contador++;
            } else {
                $item->mensaje = 'No se encontró libro';
                $problemasLibros[] = $item;
            }
        }

        // ✅ Mueve el return aquí
        return [
            "contador" => "Se actualizaron $contador registros",
            "problemasLibros" => $problemasLibros
        ];
    }

    /**
     * Mostrar la vista de reportes del sistema
     * Route: GET /admin/reportes/vista
     */
    public function mostrarVistaReportes()
    {
        // Obtener períodos disponibles donde pedido_facturacion = 1
        $periodos = DB::table('periodoescolar')
                    ->select('idperiodoescolar', 'periodoescolar', 'descripcion')
                    ->where('pedido_facturacion', 1)
                    ->orderBy('idperiodoescolar', 'desc')
                    ->get();

        return view('admin.despachados', compact('periodos'));
    }

    /**
     * Probar los procedimientos almacenados según el tipo de reporte
     * Route: GET /admin/reportes/test/{tipo_reporte}/{id_periodo}
     */
    public function testProcedimientoReportes($tipo_reporte, $id_periodo)
    {
        try {
            set_time_limit(600); // 10 minutos
            ini_set('max_execution_time', 600);
            ini_set('memory_limit', '1G');

            // Verificar que el período existe y tiene pedido_facturacion = 1
            $periodo = DB::table('periodoescolar')
                ->select('idperiodoescolar', 'periodoescolar', 'descripcion')
                ->where('idperiodoescolar', $id_periodo)
                ->where('pedido_facturacion', 1)
                ->first();

            if (!$periodo) {
                return response()->json([
                    'status' => 0,
                    'message' => "El período $id_periodo no existe o no tiene facturación habilitada"
                ], 404);
            }

            // Determinar qué procedimiento usar
            $procedimiento = $this->determinarProcedimiento($tipo_reporte, $id_periodo);

            if (!$procedimiento) {
                return response()->json([
                    'status' => 0,
                    'message' => "Tipo de reporte '$tipo_reporte' no válido"
                ], 400);
            }

            $startTime = microtime(true);

            // Ejecutar el procedimiento
            $pdo = DB::getPdo();
            $stmt = $pdo->prepare("CALL {$procedimiento['sp']}(?)");
            $result = $stmt->execute([$procedimiento['periodo']]);

            if (!$result) {
                return response()->json([
                    'status' => 0,
                    'message' => "Error al ejecutar {$procedimiento['sp']}: " . implode(', ', $stmt->errorInfo())
                ], 500);
            }

            // Obtener las primeras filas para prueba
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalRows = count($rows);
            $executionTime = round(microtime(true) - $startTime, 2);

            // Obtener estructura de columnas
            $columns = $totalRows > 0 ? array_keys($rows[0]) : [];

            return response()->json([
                'status' => 1,
                'tipo_reporte' => $tipo_reporte,
                'procedimiento_usado' => $procedimiento['sp'],
                'periodo' => $periodo->descripcion,
                'total_registros_aproximado' => number_format($totalRows),
                'estructura_columnas' => $columns,
                'tiempo_ejecucion_segundos' => $executionTime,
                'primeros_registros' => array_slice($rows, 0, 3),
                'message' => 'Procedimiento ejecutado exitosamente'
            ]);

        } catch (\Exception $e) {
            \Log::error("Error en testProcedimientoReportes ($tipo_reporte, $id_periodo): " . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar reportes en CSV según el tipo
     * Route: GET /admin/reportes/{tipo_reporte}/{id_periodo}
     */
    public function descargarReportes($tipo_reporte, $id_periodo, Request $request)
    {
        try {
            // Validar que el período existe y tiene pedido_facturacion = 1
            $periodoExists = DB::table('periodoescolar')
                ->where('idperiodoescolar', $id_periodo)
                ->where('pedido_facturacion', 1)
                ->exists();

            if (!$periodoExists) {
                return response()->json([
                    'status' => 0,
                    'message' => "El período $id_periodo no existe en la base de datos o no tiene facturación habilitada"
                ], 404);
            }

            // Determinar qué procedimiento usar
            $procedimiento = $this->determinarProcedimiento($tipo_reporte, $id_periodo);

            if (!$procedimiento) {
                return response()->json([
                    'status' => 0,
                    'message' => "Tipo de reporte '$tipo_reporte' no válido"
                ], 400);
            }

            // Obtener formato solicitado (por defecto CSV)
            $formato = $request->query('formato', 'csv');

            // ⚡ CONFIGURACIÓN AGRESIVA para grandes volúmenes de datos
            set_time_limit(0);
            ini_set('memory_limit', '4G');
            ini_set('max_execution_time', 0);
            ignore_user_abort(true);

            $startTime = microtime(true);

            // Verificar previamente si hay datos para este reporte
            try {
                $pdo = DB::getPdo();
                $stmt = $pdo->prepare("CALL {$procedimiento['sp']}(?)");
                $result = $stmt->execute([$procedimiento['periodo']]);

                if (!$result) {
                    $errorInfo = implode('; ', $stmt->errorInfo());
                    \Log::error("Error al ejecutar procedimiento {$procedimiento['sp']}: " . $errorInfo);

                    return response()->json([
                        'status' => 0,
                        'message' => 'Error al ejecutar el procedimiento almacenado',
                        'error' => $errorInfo,
                        'tipo_reporte' => $tipo_reporte,
                        'id_periodo' => $id_periodo
                    ], 500);
                }

                // Verificar si hay al menos un registro
                $hasData = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor(); // Cerrar cursor para liberar recursos

                if (!$hasData) {
                    \Log::warning("No hay datos disponibles para $tipo_reporte período $id_periodo");

                    return response()->json([
                        'status' => 0,
                        'message' => 'No hay datos disponibles para este período y tipo de reporte',
                        'tipo_reporte' => $tipo_reporte,
                        'id_periodo' => $id_periodo,
                        'empty_result' => true
                    ], 404);
                }

            } catch (\Exception $e) {
                \Log::error("Error verificando datos para $tipo_reporte período $id_periodo: " . $e->getMessage());

                return response()->json([
                    'status' => 0,
                    'message' => 'Error al verificar disponibilidad de datos',
                    'error' => $e->getMessage(),
                    'tipo_reporte' => $tipo_reporte,
                    'id_periodo' => $id_periodo
                ], 500);
            }

            if ($formato === 'excel') {
                if($procedimiento['sp'] === 'sp_facturado'){
                    return $this->generarJsonFacturado($tipo_reporte, $id_periodo, $procedimiento, $startTime);
                }
                if($procedimiento['sp'] === 'sp_ventas'){
                    return $this->generarJsonVentas($tipo_reporte, $id_periodo, $procedimiento, $startTime);
                }
                return $this->generarExcel($tipo_reporte, $id_periodo, $procedimiento, $startTime);
            } else {
                return $this->generarCSV($tipo_reporte, $id_periodo, $procedimiento, $startTime);
            }

        } catch (\Exception $e) {
            \Log::error("Error principal en descargarReportes ($tipo_reporte, $id_periodo): " . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generarJsonFacturado($tipo_reporte, $id_periodo, $procedimiento, $startTime){
        try {
            // Usar DB::select en lugar de PDO directo para evitar problemas de packets
            $datos = DB::select("CALL {$procedimiento['sp']}(?)", [$procedimiento['periodo']]);

            $totalTime = round(microtime(true) - $startTime, 2);
            \Log::info("JSON Facturado generado: " . count($datos) . " registros en {$totalTime}s");

            return response()->json([
                'status' => 1,
                'datos' => $datos,
                'total_registros' => count($datos),
                'tiempo_ejecucion' => $totalTime
            ]);

        } catch (\Exception $e) {
            \Log::error("Error generando JSON Facturado: " . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Error al generar datos: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generarJsonVentas($tipo_reporte, $id_periodo, $procedimiento, $startTime){
        try {
            // Usar DB::select en lugar de PDO directo para evitar problemas de packets
            $datos = DB::select("CALL {$procedimiento['sp']}(?)", [$procedimiento['periodo']]);

            $totalTime = round(microtime(true) - $startTime, 2);
            \Log::info("JSON Ventas generado: " . count($datos) . " registros en {$totalTime}s");

            return response()->json([
                'status' => 1,
                'datos' => $datos,
                'total_registros' => count($datos),
                'tiempo_ejecucion' => $totalTime
            ]);

        } catch (\Exception $e) {
            \Log::error("Error generando JSON Ventas: " . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Error al generar datos: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generarExcelFacturado($tipo_reporte, $id_periodo, $procedimiento, $startTime){
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return response()->json([
                'status' => 0,
                'message' => 'La librería PhpSpreadsheet no está instalada. Use formato CSV como alternativa.'
            ], 400);
        }

        try {
            $periodo = DB::table('periodoescolar')
                ->select('descripcion', 'periodoescolar')
                ->where('idperiodoescolar', $id_periodo)
                ->first();

            $descripcionPeriodo = ($periodo && !empty($periodo->descripcion))
                ? $periodo->descripcion
                : ($periodo ? $periodo->periodoescolar : $id_periodo);

            $descripcionLimpia = preg_replace('/[^A-Za-z0-9\-_]/', '_', $descripcionPeriodo);
            $fecha = date('Y-m-d_H-i-s');
            $filename = "{$tipo_reporte}_{$descripcionLimpia}_{$fecha}.xlsx";

            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];

            return response()->stream(function() use ($procedimiento, $tipo_reporte, $id_periodo, $startTime) {
                try {
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle(ucfirst($tipo_reporte));

                    $pdo = DB::getPdo();
                    $stmt = $pdo->prepare("CALL {$procedimiento['sp']}(?)");
                    $stmt->execute([$procedimiento['periodo']]);

                    // === ENCABEZADOS FIJOS ===
                    $headers = [
                        'Documento', 'Institución', 'Asesor', 'Código Combo',
                        'Precio Unit.', 'Cantidad', 'Tipo Producto', 'Fecha Documento'
                    ];

                    foreach ($headers as $col => $header) {
                        $sheet->setCellValue(chr(65 + $col) . '1', $header);
                    }

                    $rowCount = 2; // Datos empiezan en fila 2
                    $mergeRanges = []; // Para combinar celdas (rowspan)

                    // === CASO ESPECIAL: FACTURADO CON DESGLOSE ===
                    if ($tipo_reporte === 'facturado') {
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $codigos = !empty($row['Desglose_combo'])
                                ? array_filter(explode(',', $row['Desglose_combo']))
                                : ['(Sin combo)'];

                            $numCodigos = count($codigos);
                            $inicioFila = $rowCount;
                            $finFila = $inicioFila + $numCodigos - 1;

                            foreach ($codigos as $idx => $codigo) {
                                $filaActual = $rowCount;

                                // Solo en la primera fila del grupo
                                if ($idx === 0) {
                                    $sheet->setCellValue('A' . $filaActual, $row['documentoVenta'] ?? '');
                                    $sheet->setCellValue('B' . $filaActual, $row['nombreInstitucion'] ?? '');
                                    $sheet->setCellValue('C' . $filaActual, $row['asesor'] ?? '');
                                    $sheet->setCellValue('E' . $filaActual, $row['precio'] ?? 0);
                                    $sheet->setCellValue('F' . $filaActual, $row['cantidad'] ?? 0);
                                    $sheet->setCellValue('G' . $filaActual, $row['tipo_producto'] ?? '');
                                    $sheet->setCellValue('H' . $filaActual, $row['fecha_documento'] ?? '');
                                }

                                // Siempre: código del combo
                                $sheet->setCellValue('D' . $filaActual, trim($codigo));

                                $rowCount++;
                            }

                            // === AÑADIR MERGES (rowspan) si hay más de 1 código ===
                            if ($numCodigos > 1) {
                                $mergeRanges[] = "A{$inicioFila}:A{$finFila}"; // Documento
                                $mergeRanges[] = "B{$inicioFila}:B{$finFila}"; // Institución
                                $mergeRanges[] = "C{$inicioFila}:C{$finFila}"; // Asesor
                                $mergeRanges[] = "E{$inicioFila}:E{$finFila}"; // Precio
                                $mergeRanges[] = "F{$inicioFila}:F{$finFila}"; // Cantidad
                                $mergeRanges[] = "G{$inicioFila}:G{$finFila}"; // Tipo
                                $mergeRanges[] = "H{$inicioFila}:H{$finFila}"; // Fecha
                            }
                        }
                    }
                    // === OTROS REPORTES: Formato normal (sin desglose) ===
                    else {
                        $headerWritten = false;
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            if (!$headerWritten) {
                                $col = 'A';
                                foreach (array_keys($row) as $header) {
                                    $sheet->setCellValue($col . '1', $header);
                                    $col++;
                                }
                                $headerWritten = true;
                                $rowCount = 2;
                            }

                            $col = 'A';
                            foreach (array_values($row) as $value) {
                                $sheet->setCellValue($col . $rowCount, $value);
                                $col++;
                            }
                            $rowCount++;
                        }
                    }

                    // === APLICAR MERGES ===
                    if (!empty($mergeRanges)) {
                        foreach ($mergeRanges as $range) {
                            $sheet->mergeCells($range);
                            // Opcional: centrar verticalmente
                            $sheet->getStyle($range)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                        }
                    }

                    // === AUTOAJUSTAR COLUMNAS ===
                    foreach (range('A', 'H') as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }

                    // === LOG FINAL ===
                    $totalTime = round(microtime(true) - $startTime, 2);
                    \Log::info("Excel $tipo_reporte generado: " . ($rowCount-1) . " filas en {$totalTime}s");

                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save('php://output');

                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet, $stmt, $pdo);

                } catch (\Exception $e) {
                    \Log::error("Error en stream Excel $tipo_reporte: " . $e->getMessage());
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setCellValue('A1', 'Error');
                    $sheet->setCellValue('B1', $e->getMessage());
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save('php://output');
                }
            }, 200, $headers);

        } catch (\Exception $e) {
            \Log::error("Error configurando Excel: " . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Error al generar Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generarCSV($tipo_reporte, $id_periodo, $procedimiento, $startTime)
    {
        // Obtener la descripción del período
        $periodo = DB::table('periodoescolar')
            ->select('descripcion', 'periodoescolar')
            ->where('idperiodoescolar', $id_periodo)
            ->first();

        // DEBUG: Log para ver qué se está obteniendo
        \Log::info('Generando CSV - Información del período', [
            'id_periodo' => $id_periodo,
            'periodo_encontrado' => $periodo ? 'SI' : 'NO',
            'descripcion' => $periodo->descripcion ?? 'NULL',
            'periodoescolar' => $periodo->periodoescolar ?? 'NULL'
        ]);

        // Usar descripcion si existe y no está vacía, sino usar periodoescolar como respaldo
        $descripcionPeriodo = ($periodo && !empty($periodo->descripcion))
            ? $periodo->descripcion
            : ($periodo ? $periodo->periodoescolar : $id_periodo);

        // Limpiar la descripción para uso en nombre de archivo (quitar caracteres especiales)
        $descripcionLimpia = preg_replace('/[^A-Za-z0-9\-_]/', '_', $descripcionPeriodo);

        \Log::info('Generando CSV - Nombre del archivo', [
            'descripcion_original' => $descripcionPeriodo,
            'descripcion_limpia' => $descripcionLimpia
        ]);

        // Preparar nombre del archivo con descripción del período
        $fecha = date('Y-m-d_H-i-s');
        $filename = "{$tipo_reporte}_{$descripcionLimpia}_{$fecha}.csv";

        // Headers para descarga CSV
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        // Usar streaming para archivos grandes
        return response()->stream(function() use ($procedimiento, $tipo_reporte, $id_periodo, $startTime) {
            $output = fopen('php://output', 'w');

            try {
                // BOM para UTF-8 (Excel compatibility)
                fwrite($output, "\xEF\xBB\xBF");

                // Ejecutar procedimiento almacenado
                $pdo = DB::getPdo();
                $stmt = $pdo->prepare("CALL {$procedimiento['sp']}(?)");
                $result = $stmt->execute([$procedimiento['periodo']]);

                if (!$result) {
                    $errorInfo = implode('; ', $stmt->errorInfo());
                    \Log::error("Error ejecutando procedimiento {$procedimiento['sp']}: " . $errorInfo);

                    fwrite($output, "Error,Mensaje\n");
                    fwrite($output, "Error al ejecutar procedimiento," . $errorInfo . "\n");
                    return;
                }

                $headerWritten = false;
                $rowCount = 0;

                // Leer y escribir datos row by row
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Escribir header en la primera fila
                    if (!$headerWritten) {
                        fputcsv($output, array_keys($row), ';');
                        $headerWritten = true;
                    }

                    // Escribir datos
                    fputcsv($output, array_values($row), ';');
                    $rowCount++;

                    // Log de progreso cada 50k registros
                    if ($rowCount % 50000 === 0) {
                        $elapsed = round(microtime(true) - $startTime, 2);
                        \Log::info("Progreso descarga CSV $tipo_reporte: $rowCount registros procesados en {$elapsed}s");
                    }
                }

                // Si no hay datos, escribir mensaje informativo
                if ($rowCount === 0) {
                    \Log::warning("No se encontraron datos para $tipo_reporte período $id_periodo");

                    if (!$headerWritten) {
                        fwrite($output, "Mensaje\n");
                    }
                    fwrite($output, "No se encontraron datos para este período y tipo de reporte\n");
                    fwrite($output, "Período: $id_periodo\n");
                    fwrite($output, "Tipo de reporte: $tipo_reporte\n");
                    fwrite($output, "Fecha consulta: " . date('Y-m-d H:i:s') . "\n");
                } else {
                    $totalTime = round(microtime(true) - $startTime, 2);
                    \Log::info("Descarga CSV $tipo_reporte completada: $rowCount registros en {$totalTime}s");
                }

            } catch (\Exception $e) {
                \Log::error("Error en streaming descarga CSV $tipo_reporte: " . $e->getMessage());
                fwrite($output, "Error,Mensaje\n");
                fwrite($output, "Error interno," . $e->getMessage() . "\n");
            } finally {
                fclose($output);
            }
        }, 200, $headers);
    }


    /**
     * Generar archivo Excel
     */
    private function generarExcel($tipo_reporte, $id_periodo, $procedimiento, $startTime)
    {
        // Verificar si PhpSpreadsheet está disponible
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return response()->json([
                'status' => 0,
                'message' => 'La librería PhpSpreadsheet no está instalada. Use formato CSV como alternativa.'
            ], 400);
        }

        try {
            // Obtener la descripción del período
            $periodo = DB::table('periodoescolar')
                ->select('descripcion', 'periodoescolar')
                ->where('idperiodoescolar', $id_periodo)
                ->first();

            // Usar descripcion si existe y no está vacía, sino usar periodoescolar como respaldo
            $descripcionPeriodo = ($periodo && !empty($periodo->descripcion))
                ? $periodo->descripcion
                : ($periodo ? $periodo->periodoescolar : $id_periodo);

            // Limpiar la descripción para uso en nombre de archivo (quitar caracteres especiales)
            $descripcionLimpia = preg_replace('/[^A-Za-z0-9\-_]/', '_', $descripcionPeriodo);

            // Preparar nombre del archivo con descripción del período
            $fecha = date('Y-m-d_H-i-s');
            $filename = "{$tipo_reporte}_{$descripcionLimpia}_{$fecha}.xlsx";

            // Headers para descarga Excel
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];


            return response()->stream(function() use ($procedimiento, $tipo_reporte, $id_periodo, $startTime) {
                try {
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle(ucfirst($tipo_reporte));

                    // Ejecutar procedimiento almacenado
                    $pdo = DB::getPdo();
                    $stmt = $pdo->prepare("CALL {$procedimiento['sp']}(?)");
                    $stmt->execute([$procedimiento['periodo']]);

                    // === CASO ESPECIAL: FACTURADO (agrupado por factura) ===
                    if ($tipo_reporte === 'facturado') {
                        $facturas = [];
                        $rowCount = 0;

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $key = $row['documentoVenta'] ?? 'SIN_DOC_' . $rowCount;

                            if (!isset($facturas[$key])) {
                                $facturas[$key] = [
                                    'documentoVenta'     => $row['documentoVenta'] ?? '',
                                    'nombreInstitucion' => $row['nombreInstitucion'] ?? '',
                                    'asesor'             => $row['asesor'] ?? '',
                                    'precio'             => $row['precio'] ?? 0,
                                    'cantidad'           => $row['cantidad'] ?? 0,
                                    'tipo_producto'      => $row['tipo_producto'] ?? '',
                                    'fecha_documento'    => $row['fecha_documento'] ?? '',
                                    'codigos'            => []
                                ];
                            }

                            // === DESGLOSE DE COMBOS ===
                            if (!empty($row['Desglose_combo'])) {
                                $codigos = array_filter(explode(',', $row['Desglose_combo']));
                                foreach ($codigos as $cod) {
                                    $cod = trim($cod);
                                    if ($cod && !in_array($cod, $facturas[$key]['codigos'])) {
                                        // BONUS: Agregar nombre del libro
                                        $nombreLibro = DB::table('libros_series')
                                            ->where('codigo_liquidacion', $cod)
                                            ->value('nombre');

                                        $texto = $cod;
                                        if ($nombreLibro) {
                                            $texto .= " - $nombreLibro";
                                        }
                                        $facturas[$key]['codigos'][] = $texto;
                                    }
                                }
                            }
                            $rowCount++;
                        }

                        // Encabezados personalizados
                        $sheet->setCellValue('A1', 'Documento');
                        $sheet->setCellValue('B1', 'Institución');
                        $sheet->setCellValue('C1', 'Asesor');
                        $sheet->setCellValue('D1', 'Desglose Combo');
                        $sheet->setCellValue('E1', 'Precio Unit.');
                        $sheet->setCellValue('F1', 'Cantidad');
                        $sheet->setCellValue('G1', 'Tipo Producto');
                        $sheet->setCellValue('H1', 'Fecha Documento');

                        // Datos agrupados
                        $fila = 2;
                        foreach ($facturas as $f) {
                            $desglose = !empty($f['codigos'])
                                ? implode("\n", $f['codigos'])  // Salto de línea para mejor lectura en Excel
                                : '(Sin combo)';

                            $sheet->setCellValue("A$fila", $f['documentoVenta']);
                            $sheet->setCellValue("B$fila", $f['nombreInstitucion']);
                            $sheet->setCellValue("C$fila", $f['asesor']);
                            $sheet->setCellValue("D$fila", $desglose);
                            $sheet->setCellValue("E$fila", $f['precio']);
                            $sheet->setCellValue("F$fila", $f['cantidad']);
                            $sheet->setCellValue("G$fila", $f['tipo_producto']);
                            $sheet->setCellValue("H$fila", $f['fecha_documento']);

                            // Ajustar altura de fila y permitir saltos de línea
                            $sheet->getRowDimension($fila)->setRowHeight(-1); // Auto altura
                            $sheet->getStyle("D$fila")->getAlignment()
                                ->setWrapText(true)
                                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

                            $fila++;
                        }

                        // Autoajustar columnas
                        foreach (range('A', 'H') as $col) {
                            $sheet->getColumnDimension($col)->setAutoSize(true);
                        }

                        \Log::info("Excel FACTURADO agrupado generado: " . count($facturas) . " facturas en " . round(microtime(true) - $startTime, 2) . "s");
                    }
                    // === OTROS REPORTES: Formato normal (fila por fila) ===
                    else {
                        $headerWritten = false;
                        $rowCount = 1;

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            if (!$headerWritten) {
                                // Escribir headers
                                $col = 'A';
                                foreach (array_keys($row) as $header) {
                                    $sheet->setCellValue($col . '1', $header);
                                    $col++;
                                }
                                $headerWritten = true;
                                $rowCount = 2;
                            }

                            // Escribir datos
                            $col = 'A';
                            foreach (array_values($row) as $value) {
                                $sheet->setCellValue($col . $rowCount, $value);
                                $col++;
                            }
                            $rowCount++;

                            if ($rowCount % 10000 === 0) {
                                $elapsed = round(microtime(true) - $startTime, 2);
                                \Log::info("Progreso Excel $tipo_reporte: " . ($rowCount-1) . " filas en {$elapsed}s");
                            }
                        }
                    }

                    // Generar archivo Excel
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save('php://output');

                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet, $stmt);

                } catch (\Exception $e) {
                    \Log::error("Error en generación Excel $tipo_reporte: " . $e->getMessage());
                    // En caso de error, generar un Excel simple con el error
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setCellValue('A1', 'Error');
                    $sheet->setCellValue('B1', $e->getMessage());

                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save('php://output');
                }
            }, 200, $headers);

        } catch (\Exception $e) {
            \Log::error("Error configurando Excel: " . $e->getMessage());
            return response()->json(['status' => 0, 'message' => 'Error al generar Excel'], 500);
        }
    }

    /**
     * Determinar qué procedimiento almacenado usar según el tipo de reporte y período
     */
    private function determinarProcedimiento($tipo_reporte, $id_periodo)
    {
        switch ($tipo_reporte) {
            case 'despachados':
                return [
                    'sp' => 'sp_despachados',
                    'periodo' => $id_periodo
                ];

            case 'pedidos_alcances':
                // Para períodos > 27 usa sp_pedidos_alcances_new
                // Para períodos ≤ 26 usa sp_pedidos_alcances_old
                if ($id_periodo >= 27) {
                    return [
                        'sp' => 'sp_pedidos_alcances_new',
                        'periodo' => $id_periodo
                    ];
                } else {
                    return [
                        'sp' => 'sp_pedidos_alcances_old',
                        'periodo' => $id_periodo
                    ];
                }

            case 'liquidados':
                return [
                    'sp' => 'sp_liquidados',
                    'periodo' => $id_periodo
                ];

            case 'devoluciones':
                return [
                    'sp' => 'sp_devoluciones',
                    'periodo' => $id_periodo
                ];

            case 'ventas':
                return [
                    'sp' => 'sp_ventas',
                    'periodo' => $id_periodo
                ];
            case 'facturado':
                return [
                    'sp' => 'sp_facturado',
                    'periodo' => $id_periodo
                ];
            default:
                return null;
        }
    }

}


