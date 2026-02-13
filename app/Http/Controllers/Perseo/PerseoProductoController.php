<?php

namespace App\Http\Controllers\Perseo;

use App\Http\Controllers\Controller;
use App\Traits\Pedidos\TraitPedidosGeneral;
use Illuminate\Http\Request;

class PerseoProductoController extends Controller
{
    use TraitPedidosGeneral;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //api:post/perseo/productos/productos_crear
    public function productos_crear(Request $request)
    {
        try {
            $formData = [
                "registros" => [
                    [
                        "productos" => [
                            "productocodigo" => "TEST 2 OBJ",
                            "descripcion" => "test MARCATEST MODEL TEST",
                            "sri_tipos_ivas_codigo" => 2,
                            "productos_lineasid" => 300,
                            "productos_categoriasid" => 1,
                            "sri_impuestosid" => 21,
                            "productos_subcategoriasid" => 1,
                            "productos_subgruposid" => 1,
                            "estado" => true,
                            "venta" => true,
                            "existenciastotales" => 0,
                            "controlnegativos" => true,
                            "controlprecios" => true,
                            "servicio" => false,
                            "bien" => false,
                            "series" => false,
                            "vehiculos" => false,
                            "fichatecnica" => "",
                            "costoactual" => 0,
                            "costoestandar" => 0,
                            "costoultimacompra" => 0,
                            "fechaultimacompra" => "",
                            "observaciones" => "",
                            "unidadinterna" => 1,
                            "unidadsuperior" => 0,
                            "unidadinferior" => 0,
                            "unidadcompra" => 1,
                            "unidadventa" => 1,
                            "factorsuperior" => 0,
                            "factorinferior" => 0,
                            "usuariocreacion" => "CYUNGA",
                            "fechacreacion" => "20240411100330",
                            "tarifas" => [
                                [
                                    "tarifasid" => 1,
                                    "medidasid" => 100,
                                    "medidas" => "Unidad",
                                    "precioiva" => 23,
                                    "precio" => 20,
                                    "margen" => 0,
                                    "utilidad" => 0,
                                    "descuento" => 0,
                                    "escala" => 0
                                ],
                                [
                                    "tarifasid" => 2,
                                    "medidasid" => 100,
                                    "medidas" => "Unidad",
                                    "precioiva" => 24.15,
                                    "precio" => 21,
                                    "margen" => 0,
                                    "utilidad" => 0,
                                    "descuento" => 0,
                                    "escala" => 0
                                ]
                            ]
                        ]
                    ]
                ]
            ];


            // $formData = [
            //     "registros" => [
            //         [
            //             "productos" => [
            //                 "productocodigo"                        => $request->productocodigo,
            //                 "descripcion"                           => $request->descripcion,
            //                 "sri_tipos_ivas_codigo"                 => $request->sri_tipos_ivas_codigo,
            //                 "productos_lineasid"                    => $request->productos_lineasid,
            //                 "productos_categoriasid"                => $request->productos_categoriasid,
            //                 "sri_impuestosid"                       => $request->sri_impuestosid,
            //                 "productos_subcategoriasid"             => $request->productos_subcategoriasid,
            //                 "productos_subgruposid"                 => $request->productos_subgruposid,
            //                 "estado"                                => $request->estado,
            //                 "venta"                                 => $request->venta,
            //                 "existenciastotales"                    => $request->existenciastotales,
            //                 "controlnegativos"                      => $request->controlnegativos,
            //                 "controlprecios"                        => $request->controlprecios,
            //                 "servicio"                              => $request->servicio,
            //                 "bien"                                  => $request->bien,
            //                 "series"                                => $request->series,
            //                 "vehiculos"                             => $request->vehiculos,
            //                 "fichatecnica"                          => $request->fichatecnica,
            //                 "costoactual"                           => $request->costoactual,
            //                 "costoestandar"                         => $request->costoestandar,
            //                 "costoultimacompra"                     => $request->costoultimacompra,
            //                 "fechaultimacompra"                     => $request->fechaultimacompra,
            //                 "observaciones"                         => $request->observaciones,
            //                 "unidadinterna"                         => $request->unidadinterna,
            //                 "unidadsuperior"                        => $request->unidadsuperior,
            //                 "unidadinferior"                        => $request->unidadinferior,
            //                 "unidadcompra"                          => $request->unidadcompra,
            //                 "unidadventa"                           => $request->unidadventa,
            //                 "factorsuperior"                        => $request->factorsuperior,
            //                 "factorinferior"                        => $request->factorinferior,
            //                 "usuariocreacion"                       => $request->usuariocreacion,
            //                 "fechacreacion"                         => $request->fechacreacion,
            //                 "tarifas"                               => json_decode($request->tarifas)
            //             ]
            //         ]
            //     ]
            // ];
            $url        = "productos_crear";
            $process    = $this->tr_PerseoPost($url, $formData);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //api:post/perseo/productos/productos_editar
    public function productos_editar(Request $request){
        try{
            $formData = [
                "registros" => [
                    [
                        "productos" => [
                            "productosid" => 7,
                            "productocodigo" => "P000000007",
                            "descripcion" => "productos de prueba modificado",
                            "sri_codigo_impuestos" => "312",
                            "barras" => "7301122384577",
                            "sri_tipos_ivas_codigo" => "2",
                            "productos_lineasid" => 1,
                            "productos_categoriasid" => 1,
                            "productos_subcategoriasid" => 1,
                            "productos_subgruposid" => 1,
                            "estado" => true,
                            "venta" => true,
                            "existenciastotales" => 0,
                            "controlnegativos" => true,
                            "controlprecios" => true,
                            "servicio" => false,
                            "bien" => false,
                            "series" => false,
                            "vehiculos" => false,
                            "fichatecnica" => "",
                            "costoactual" => 0.75,
                            "costoestandar" => 0.75,
                            "costoultimacompra" => 0.75,
                            "fechaultimacompra" => "",
                            "observaciones" => "",
                            "unidadinterna" => 1,
                            "unidadsuperior" => 0,
                            "unidadinferior" => 0,
                            "unidadcompra" => 1,
                            "unidadventa" => 1,
                            "factorsuperior" => 0,
                            "factorinferior" => 0,
                            "usuariocreacion" => "PERSEO",
                            "fechacreacion" => "20190730112354",
                            "tarifas" => [
                                [
                                    "tarifasid" => 1,
                                    "medidasid" => 1,
                                    "medidas" => "Unidad",
                                    "precioiva" => 33.6,
                                    "precio" => 30,
                                    "margen" => 0,
                                    "utilidad" => 0,
                                    "descuento" => 0,
                                    "factor" => 1,
                                    "escala" => 0
                                ],
                                [
                                    "tarifasid" => 2,
                                    "medidasid" => 1,
                                    "medidas" => "Unidad",
                                    "precioiva" => 40,
                                    "precio" => 35.714286,
                                    "margen" => 0,
                                    "utilidad" => 0,
                                    "descuento" => 0,
                                    "factor" => 1,
                                    "escala" => 0
                                ],
                                [
                                    "tarifasid" => 3,
                                    "medidasid" => 1,
                                    "medidas" => "Unidad",
                                    "precioiva" => 39.2,
                                    "precio" => 35,
                                    "margen" => 0,
                                    "utilidad" => 0,
                                    "descuento" => 0,
                                    "factor" => 1,
                                    "escala" => 0
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            // $formData = [
            //     "registros" => [
            //         [
            //             "productos" => [
            //                 "productosid"                           => $request->productosid,
            //                 "productocodigo"                        => $request->productocodigo,
            //                 "descripcion"                           => $request->descripcion,
            //                 "sri_codigo_impuestos"                  => $request->sri_codigo_impuestos,
            //                 "barras"                                => $request->barras,
            //                 "sri_tipos_ivas_codigo"                 => $request->sri_tipos_ivas_codigo,
            //                 "productos_lineasid"                    => $request->productos_lineasid,
            //                 "productos_categoriasid"                => $request->productos_categoriasid,
            //                 "productos_subcategoriasid"             => $request->productos_subcategoriasid,
            //                 "productos_subgruposid"                 => $request->productos_subgruposid,
            //                 "estado"                                => $request->estado,
            //                 "venta"                                 => $request->venta,
            //                 "existenciastotales"                    => $request->existenciastotales,
            //                 "controlnegativos"                      => $request->controlnegativos,
            //                 "controlprecios"                        => $request->controlprecios,
            //                 "servicio"                              => $request->servicio,
            //                 "bien"                                  => $request->bien,
            //                 "series"                                => $request->series,
            //                 "vehiculos"                             => $request->vehiculos,
            //                 "fichatecnica"                          => $request->fichatecnica,
            //                 "costoactual"                           => $request->costoactual,
            //                 "costoestandar"                         => $request->costoestandar,
            //                 "costoultimacompra"                     => $request->costoultimacompra,
            //                 "fechaultimacompra"                     => $request->fechaultimacompra,
            //                 "observaciones"                         => $request->observaciones,
            //                 "unidadinterna"                         => $request->unidadinterna,
            //                 "unidadsuperior"                        => $request->unidadsuperior,
            //                 "unidadinferior"                        => $request->unidadinferior,
            //                 "unidadcompra"                          => $request->unidadcompra,
            //                 "unidadventa"                           => $request->unidadventa,
            //                 "factorsuperior"                        => $request->factorsuperior,
            //                 "factorinferior"                        => $request->factorinferior,
            //                 "usuariocreacion"                       => $request->usuariocreacion,
            //                 "fechacreacion"                         => $request->fechacreacion,
            //                 "tarifas"                               => json_decode($request->tarifas)
            //             ]
            //         ]
            //     ]
            // ];
            $url        = "productos_editar";
            $process    = $this->tr_PerseoPost($url, $formData);
            return $process;
        }catch(\Exception $e){
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /*
        Nota: Antes de realizar el proceso de eliminación, se verifique que el producto no tenga documentos asociados, si es así envía un mensaje de error en formato JSON, caso contrario retorna un texto fijo con el siguiente enunciado: Registro eliminado correctamente en el sistema.
    */
    //api:post/perseo/productos/productos_eliminar
    /*
        productosid: id del producto a eliminar.
        empresaid: Opcional, id de la empresa en la que se esta trabajando.
        usuarioid: Opcional, id del usuario con que accedió al sistema.
        Nota: Los dos parámetros opcionales, son utilizados para registrar la auditoria en el sistema contable.
    */
    public function productos_eliminar(Request $request){
        try {
            $formData = [
                "productosid"   => 9,
                "empresaid"     => 2,
                "usuarioid"     => 2
            ];
            // $formData = [
            //     "productosid"   => $request->productosid,
            //     "empresaid"     => $request->empresaid,
            //     "usuarioid"     => $request->usuarioid
            // ];
            $url        = "productos_eliminar";
            $process    = $this->tr_PerseoPost($url, $formData);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /*
    Devuelve todos los productos almacenados en el sistema central, debe estar configurado en la opcion de estado ecommerce: como Disponible para ambos o Solo en venta publico y que su estado sea activo , de acuerdo con el parámetro que se haya enviado.
    Tomar en cuenta que al realizar una búsqueda personalizada, se debe enviar solo 1 de los 4 parámetros solicitados; es decir, que realiza la busque por:
    */
    //api:post/perseo/productos/productos_consulta
    /*
        productosid: Si se conoce el id del producto..
        productocodigo: Código generado al crear el cliente.
        barras: Código de barra del producto.
        contenido: Descripción del producto. Se puede enviar el nombre completo o una parte para buscar coincidencia.
    */
    public function productos_consulta(Request $request){
        try {
            $id_empresa = $request->input('id_empresa', 1);
            $formData = [
                // "productosid"=>"67",
                // "productocodigo"=>"SMLL2",
                // "barras"=>"6181220422071",
                // "contenido"=>"producto"

                // "productosid"=>24,
                // "productos_codigo"=>"SMLL2",
                // "barras"=>"6181220422071"
            ];
            // $formData = [
            //     "productosid"    => $request->productosid,
            //     "productocodigo" => $request->productocodigo,
            //     "barras"         => $request->barras,
            //     "contenido"      => $request->contenido
            // ];
            $url        = "productos_consulta";
            $process    = $this->tr_PerseoPost($url, $formData,1);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function productos_consulta_x_id(Request $request){
        try {
            $id_empresa = $request->input('id_empresa');
            $codigos = $request->input('codigos', []); // Array de códigos de productos
            
            // Si no se envían códigos, retornar error
            if (empty($codigos)) {
                return response()->json([
                    'error' => 'Debe enviar al menos un código de producto'
                ], 400);
            }
            
            // OPTIMIZACIÓN: Aumentar tiempo límite para consultas grandes
            set_time_limit(300); // 5 minutos
            
            $productos_resultado = [];
            $productos_no_encontrados = [];
            $total_codigos = count($codigos);
            
            // PROCESAMIENTO POR LOTES para evitar timeout
            $tamano_lote = 20; // Procesar 20 productos a la vez
            $lotes = array_chunk($codigos, $tamano_lote);
            $productos_procesados = 0;
            
            foreach ($lotes as $indice_lote => $lote) {
                // Consultar cada producto del lote
                foreach ($lote as $codigo) {
                    try {
                        $formData = [
                            "productocodigo" => $codigo
                        ];
                        
                        $url = "productos_consulta";
                        $process = $this->tr_PerseoPost($url, $formData, $id_empresa);
                        
                        // Agregar el resultado si se encontró el producto
                        if (isset($process['productos']) && !empty($process['productos'])) {
                            $productos_resultado[] = $process['productos'][0];
                        } else {
                            $productos_no_encontrados[] = $codigo;
                        }
                        
                        $productos_procesados++;
                        
                    } catch (\Exception $e) {
                        // Si falla uno, continuar con los demás
                        $productos_no_encontrados[] = $codigo;
                        \Log::error("Error consultando producto {$codigo}: " . $e->getMessage());
                    }
                }
                
                // Pequeña pausa entre lotes para no saturar la API
                if ($indice_lote < count($lotes) - 1) {
                    usleep(100000); // 0.1 segundos
                }
            }
            
            return response()->json([
                'productos' => $productos_resultado,
                'total_solicitados' => $total_codigos,
                'total_encontrados' => count($productos_resultado),
                'total_no_encontrados' => count($productos_no_encontrados),
                'codigos_no_encontrados' => $productos_no_encontrados
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
        Nota: Devuelve todas las imágenes de los productos almacenados que exista. Se puede realizar la busque por el parámetro id del producto.
        *Para agregar las imágenes en el Json, se envían codificadas en formato encodeBASE64noCR
    */
    //api:post/perseo/productos/productos_imagenes_consulta
    public function productos_imagenes_consulta(Request $request){
        try {
            $formData = [
                "productosid" => 1
            ];
            // $formData = [
            //     "productosid" => $request->productosid
            // ];
            $url        = "productos_imagenes_consulta";
            $process    = $this->tr_PerseoPost($url, $formData);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /*
        Este procedimiento se utiliza para extraer el listado de las líneas creadas para clasificar a los productos.
    */
    //api:post/perseo/productos/productos_lineas_consulta
    public function productos_lineas_consulta(Request $request){
        try {
            $formData = [];
            $url        = "productos_lineas_consulta";
            $id_empresa = $request->input('id_empresa', 1);
            $process    = $this->tr_PerseoPost($url, $formData,$id_empresa);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /*
        Este procedimiento se utiliza para extraer el listado de las categorías creadas para clasificar a los productos. El formato con que devuelve el resultado es tipo JSON.
     */
    //api:post/perseo/productos/productos_categorias_consulta
    public function productos_categorias_consulta(Request $request){
        try {
            $formData = [];
            $url        = "productos_categorias_consulta";
            $id_empresa = $request->input('id_empresa', 1);
            $process    = $this->tr_PerseoPost($url, $formData,$id_empresa);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /*
        Este procedimiento se utiliza para extraer el listado de las subcategorías creadas para clasificar a los productos. El formato con que devuelve el resultado es tipo JSON.
    */
    //api:post/perseo/productos/productos_subcategorias_consulta
    public function productos_subcategorias_consulta(Request $request){
        try {
            $formData = [];
            $url        = "productos_subcategorias_consulta";
            $id_empresa = $request->input('id_empresa', 1);
            $process    = $this->tr_PerseoPost($url, $formData,$id_empresa);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /*
        Este procedimiento se utiliza para extraer el listado de los subgrupos creados para clasificar a los productos. El formato con que devuelve el resultado es tipo JSON.
    */
    //api:post/perseo/productos/productos_subgrupos_consulta
    public function productos_subgrupos_consulta(Request $request){
        try {
            $formData = [];
            $url        = "productos_subgrupos_consulta";
            $process    = $this->tr_PerseoPost($url, $formData);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /*
        *Consulta la existencia por almacenes de los productos.
        *Se puede utilizar para consultar un producto especifico, filtrando por id; ó de manera general para filtrar todos los productos
    */
    //api:post/perseo/productos/existencia_producto
    public function existencia_producto(Request $request){
        try {
            $formData = [
                "productosid" => 33
            ];
            // $formData = [
            //     "productosid" => $request->productosid
            // ];
            $url        = "existencia_producto";
            $process    = $this->tr_PerseoPost($url, $formData);
            return $process;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //api:get/perseo/productos/producto
    public function index()
    {
        //
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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

    //INVENTARIO PRODUCTOS INICIO
    public function Procesar_MetodosPost_Productos(Request $request)
    {
        $action = $request->input('action');
        switch ($action) {
            case 'productos_consulta_todo':
                return $this->productos_consulta_todo($request);
            case 'productos_consulta_x_productosid':
                return $this->productos_consulta_x_productosid($request);
            case 'productos_EditarCompleto':
                return $this->productos_EditarCompleto($request);
            default:
                return response()->json([
                    'error' => 'Acción no válida'
                ], 400);
        }
    }

    public function productos_consulta_todo(Request $request)
    {
        try {
            $id_empresa = $request->input('id_empresa');
            $formData = [];

            $ruta_productos = "productos_consulta";
            $ruta_productos_lineas = "productos_lineas_consulta";
            $ruta_productos_categorias = "productos_categorias_consulta";
            $ruta_productos_subcategorias = "productos_subcategorias_consulta";
            $ruta_productos_subgrupos = "productos_subgrupos_consulta";
            $ruta_almacenes = "almacenes_consulta";
            $ruta_existencias = "existencia_producto";

            // ============================
            // 1. CONSUMO DE APIS
            // ============================
            $todo_producto      = $this->tr_PerseoPost($ruta_productos, $formData, $id_empresa);
            $todo_lineas        = $this->tr_PerseoPost($ruta_productos_lineas, $formData, $id_empresa);
            $todo_categorias    = $this->tr_PerseoPost($ruta_productos_categorias, $formData, $id_empresa);
            $todo_subcategorias = $this->tr_PerseoPost($ruta_productos_subcategorias, $formData, $id_empresa);
            $todo_subgrupos     = $this->tr_PerseoPost($ruta_productos_subgrupos, $formData, $id_empresa);
            $todo_almacenes     = $this->tr_PerseoPost($ruta_almacenes, $formData, $id_empresa);
            $todo_existencias   = $this->tr_PerseoPost($ruta_existencias, $formData, $id_empresa);

            // ============================
            // 2. MAPAS
            // ============================
            $mapaLineas = [];
            foreach ($todo_lineas['lineas'] ?? [] as $l) {
                $mapaLineas[$l['productos_lineasid']] = $l['descripcion'];
            }

            $mapaCategoria = [];
            foreach ($todo_categorias['categorias'] ?? [] as $c) {
                $mapaCategoria[$c['productos_categoriasid']] = $c['descripcion'];
            }

            $mapaSubcategoria = [];
            foreach ($todo_subcategorias['subcategorias'] ?? [] as $s) {
                $mapaSubcategoria[$s['productos_subcategoriasid']] = $s['descripcion'];
            }

            $mapaSubgrupos = [];
            foreach ($todo_subgrupos['subgrupo'] ?? [] as $s) {
                $mapaSubgrupos[$s['productos_subgruposid']] = $s['descripcion'];
            }

            // ============================
            // 3. ALMACENES DISTINCT
            // ============================
            $mapaAlmacenes = [];
            $almacenesDistinct = [];

            foreach ($todo_existencias['existencias'] ?? [] as $ex) {
                if (!isset($mapaAlmacenes[$ex['almacenesid']])) {
                    $mapaAlmacenes[$ex['almacenesid']] = $ex['almacen'];
                    $almacenesDistinct[] = [
                        'almacen' => $ex['almacen']
                    ];
                }
            }

            // ============================
            // 4. MAPA EXISTENCIAS
            // ============================
            $mapaExistencias = [];
            foreach ($todo_existencias['existencias'] ?? [] as $ex) {
                $mapaExistencias[$ex['productosid']][$ex['almacenesid']] = $ex['existencias'];
            }

            // ============================
            // 5. PRODUCTOS (USAR VARIABLE LOCAL)
            // ============================
            $productos = $todo_producto['productos'] ?? [];

            foreach ($productos as $i => $producto) {

                $productos[$i]['descripcion_lineas'] =
                    $mapaLineas[$producto['productos_lineasid']] ?? null;

                $productos[$i]['descripcion_categoria'] =
                    $mapaCategoria[$producto['productos_categoriasid']] ?? null;

                $productos[$i]['descripcion_subcategoria'] =
                    $mapaSubcategoria[$producto['productos_subcategoriasid']] ?? null;

                $productos[$i]['descripcion_subgrupo'] =
                    $mapaSubgrupos[$producto['productos_subgruposid']] ?? null;

                // ============================
                // ALMACENES X PRODUCTO
                // ============================
                $almacenesProducto = [];

                foreach ($mapaAlmacenes as $almacenId => $nombreAlmacen) {
                    $almacenesProducto[] = [
                        'almacen' => $nombreAlmacen,
                        'existencias' =>
                            $mapaExistencias[$producto['productosid']][$almacenId] ?? 0
                    ];
                }

                $productos[$i]['almacenes_x_producto'] = $almacenesProducto;
            }

            // ============================
            // 6. RESPUESTA FINAL
            // ============================
            return response()->json([
                'productos'      => $productos,
                'lineas'         => $todo_lineas['lineas'],
                'categorias'     => $todo_categorias['categorias'],
                'subcategorias'  => $todo_subcategorias['subcategorias'],
                'subgrupos'      => $todo_subgrupos['subgrupo'],
                'almacenes'      => $almacenesDistinct,
                'existencias'    => $todo_existencias['existencias']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function productos_consulta_x_productosid(Request $request){
        try {
            $id_empresa = $request->input('id_empresa');
            $ruta_productos_lineas = "productos_lineas_consulta";
            $ruta_productos_categorias = "productos_categorias_consulta";
            $ruta_productos_subcategorias = "productos_subcategorias_consulta";
            $ruta_productos_subgrupos = "productos_subgrupos_consulta";
            $ruta_iva = "tipoiva_consulta";
            $formData = [
                "productosid" => $request->input('productosid'),
            ];
            $url        = "productos_consulta";
            $process    = $this->tr_PerseoPost($url, $formData,$id_empresa);
            $todo_lineas        = $this->tr_PerseoPost($ruta_productos_lineas, $formData, $id_empresa);
            $todo_categorias    = $this->tr_PerseoPost($ruta_productos_categorias, $formData, $id_empresa);
            $todo_subcategorias = $this->tr_PerseoPost($ruta_productos_subcategorias, $formData, $id_empresa);
            $todo_subgrupos     = $this->tr_PerseoPost($ruta_productos_subgrupos, $formData, $id_empresa);
            $todo_iva           = $this->tr_PerseoPost($ruta_iva, $formData, $id_empresa);
            return [
                'productos'      => $process['productos'],
                'lineas'         => $todo_lineas['lineas'],
                'categorias'     => $todo_categorias['categorias'],
                'subcategorias'  => $todo_subcategorias['subcategorias'],
                'subgrupos'      => $todo_subgrupos['subgrupo'],
                'iva'            => $todo_iva['iva']
            ];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function productos_EditarCompleto($request)
    {
        // return $request;
        try {
            $producto = $request->input('producto');
            if (!$producto) {
                return response()->json([
                    'error' => 'Producto no enviado'
                ], 400);
            }
            $formData = [
                "registros" => [
                    [
                        "productos" => [
                            "productosid" => $producto['productosid'] ?? null,
                            "productocodigo" => $producto['productocodigo'],
                            "descripcion" => $producto['descripcion'],
                            "descripcioncorta" => $producto['descripcioncorta'] ?? '',
                            "alterno1" => $producto['alterno1'] ?? '',
                            "barras" => $producto['barras'],
                            "sri_tipos_ivas_codigo" => $producto['sri_tipos_ivas_codigo'] ?? '2',
                            "productos_lineasid" => $producto['productos_lineasid'],
                            "productos_categoriasid" => $producto['productos_categoriasid'],
                            "productos_subcategoriasid" => $producto['productos_subcategoriasid'],
                            "productos_subgruposid" => $producto['productos_subgruposid'],
                            "estado" => $producto['estado'],
                            "venta" => $producto['venta'],
                            "existenciastotales" => $producto['existenciastotales'],
                            "controlnegativos" => $producto['controlnegativos'],
                            "controlprecios" => $producto['controlprecios'],
                            "servicio" => false,
                            "bien" => false,
                            "series" => $producto['series'],
                            "vehiculos" => $producto['vehiculos'],
                            "parametros_json" => json_encode(["combustible" => ($producto['combustible'] ?? false) ? 1 : 0]),
                            "fichatecnica" => '',
                            "costoactual" => $producto['costoactual'],
                            "costoestandar" => $producto['costoestandar'],
                            "costoultimacompra" => $producto['costoultimacompra'],
                            "unidadinterna" => 1,
                            "unidadcompra" => $producto['unidadcompra'],
                            "unidadventa" => $producto['unidadventa'],
                            "unidadsuperior" => $producto['unidadsuperior'],
                            "unidadinferior" => $producto['unidadinferior'],
                            "factorsuperior" => $producto['factorsuperior'],
                            "factorinferior" => $producto['factorinferior'],
                            "usuariocreacion" => "PERSEO",
                            "fechacreacion" => now()->format('YmdHis'),
                            "tarifas" => array_map(function ($tarifa) {
                                return [
                                    "tarifasid" => $tarifa['tarifasid'] ?? null,
                                    "medidasid" => $tarifa['medidasid'] ?? 1,
                                    "medidas" => "Unidad",
                                    "precioiva" => $tarifa['precioiva'],
                                    "precio" => $tarifa['precio'],
                                    "margen" => $tarifa['margen'] ?? 0,
                                    "utilidad" => $tarifa['utilidad'] ?? 0,
                                    "descuento" => 0,
                                    "factor" => 1,
                                    "escala" => $tarifa['escala'] ?? 0
                                ];
                            }, $producto['tarifas'])
                        ]
                    ]
                ]
            ];

            $url = "productos_editar";
            $result = $this->tr_PerseoPost($url, $formData);

            // Asegurar que la respuesta incluya status: 1 para el frontend
            if ($result && !isset($result['error'])) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Producto actualizado correctamente',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al actualizar producto',
                    'error' => $result['error'] ?? 'Error desconocido'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //INVENTARIO PRODUCTOS FIN
}
