<?php

namespace App\Http\Controllers;


use App\Models\Fichero_Mercado;
use App\Models\Fichero_Mercado_Autoridades;
use App\Models\Fichero_Mercado_Detalle;
use App\Models\Institucion;
use App\Models\Beneficiarios;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Repositories\pedidos\NotificacionRepository;
use App\Repositories\pedidos\VerificacionRepository;
use Illuminate\Http\Request;

class Fichero_MercadoController extends Controller
{
    public $verificacionRepository;
    protected $NotificacionRepository;
    public function __construct(VerificacionRepository $verificacionRepository, NotificacionRepository $NotificacionRepository)
     {
        $this->verificacionRepository   = $verificacionRepository;
        $this->NotificacionRepository   = $NotificacionRepository;
     }
    public function Procesar_MetodosGet_Fichero_MercadoController(Request $request)
    {
        $action = $request->query('action'); // Leer el parámetro `action` desde la URL
        switch ($action) {
            case 'institucionesxRegion':
                return $this->institucionesxRegion($request);
            case 'institucionesxRegion_y_Asesor':
                return $this->institucionesxRegion_y_Asesor($request);
            case 'Editoriales_Activas_Fichero':
                return $this->Editoriales_Activas_Fichero();
            case 'Editoriales_Todo_Fichero':
                return $this->Editoriales_Todo_Fichero();
            case 'Niveles_Fichero':
                return $this->Niveles_Fichero();
            case 'Fechas_Entrega_muestras_x_institucion':
                return $this->Fechas_Entrega_muestras_x_institucion($request);
            case 'Verificar_GetFicheroSeleccionado_Existencia':
                return $this->Verificar_GetFicheroSeleccionado_Existencia($request);
            case 'Busqueda_getTraer_Datos_Fichero_x_fmd_id':
                return $this->Busqueda_getTraer_Datos_Fichero_x_fmd_id($request);
            case 'Busqueda_getTraer_Datos_Fichero_Autoridades_x_fmd_id':
                return $this->Busqueda_getTraer_Datos_Fichero_Autoridades_x_fmd_id($request);
            case 'Busqueda_getTraer_Datos_Fichero_Detalle_x_fmd_id':
                return $this->Busqueda_getTraer_Datos_Fichero_Detalle_x_fmd_id($request);
            case 'Verificar_getVerificar_Aprobacion_Fichero':
                return $this->Verificar_getVerificar_Aprobacion_Fichero($request);
            case 'Busqueda_get_Fichero_Mercado_Pendiente_Aprobacion':
                return $this->Busqueda_get_Fichero_Mercado_Pendiente_Aprobacion($request);
            case 'Busqueda_get_Fichero_Mercado_Rechazados':
                return $this->Busqueda_get_Fichero_Mercado_Rechazados($request);
            case 'Busqueda_get_Fichero_Mercado_Aprobados':
                return $this->Busqueda_get_Fichero_Mercado_Aprobados($request);
            case 'CargosAutoridadesInstitucion_Activas_Fichero':
                return $this->CargosAutoridadesInstitucion_Activas_Fichero($request);
            case 'CargosAutoridadesInstitucion_Todo_Fichero':
                return $this->CargosAutoridadesInstitucion_Todo_Fichero();
            case 'Lista_Series_Fichero':
                return $this->Lista_Series_Fichero();
            case 'Lista_Areas_Fichero':
                return $this->Lista_Areas_Fichero($request);
            case 'Lista_Areas_X_nombre_serie_Fichero':
                return $this->Lista_Areas_X_nombre_serie_Fichero($request);
            case 'Busqueda_get_Fichero_Mercado_Todo_x_Insitucion_y_Asesor':
                return $this->Busqueda_get_Fichero_Mercado_Todo_x_Insitucion_y_Asesor($request);
            case 'Busqueda_get_Fichero_Mercado_Todo_Instituciones_Root':
                return $this->Busqueda_get_Fichero_Mercado_Todo_Instituciones_Root($request);
            case 'Busqueda_get_Fichero_Mercado_Todo_Export_Completo':
                return $this->Busqueda_get_Fichero_Mercado_Todo_Export_Completo($request);
            case 'Listar_Ficheros_Por_Institucion':
                return $this->Listar_Ficheros_Por_Institucion($request);
            case 'Listar_Beneficiarios_por_insitucion_y_periodo':
                return $this->Listar_Beneficiarios_por_insitucion_y_periodo($request);
            case 'Verificar_EstadoFichero':
                return $this->Verificar_EstadoFichero($request);
            default:
                return response()->json(['error' => 'Acción no válida'], 400);
        }
    }
    public function Procesar_MetodosPost_Fichero_MercadoController(Request $request)
    {
        $action = $request->input('action'); // Recibir el parámetro 'action'

        switch ($action) {
            case 'GuardarDatos_guardarFicheroCabecera':
                return $this->GuardarDatos_guardarFicheroCabecera($request);
            case 'GuardarDatos_guardarFicheroAutoridades':
                return $this->GuardarDatos_guardarFicheroAutoridades($request);
            case 'GuardarDatos_guardarFicheroDetalleEditoriales':
                return $this->GuardarDatos_guardarFicheroDetalleEditoriales($request);
            case 'GuardarDatos_EnviarParaAprobacion':
                return $this->GuardarDatos_EnviarParaAprobacion($request);
            case 'GuardarDatos_GuardarComoActivo_Fichero':
                return $this->GuardarDatos_GuardarComoActivo_Fichero($request);
            case 'GuardarDatos_FicheroAprobado':
                return $this->GuardarDatos_FicheroAprobado($request);
            case 'GuardarDatos_FicheroRechazado':
                return $this->GuardarDatos_FicheroRechazado($request);
            case 'Fechas_Entrega_muestras_masivo':
                return $this->Fechas_Entrega_muestras_masivo($request);
            case 'Copiar_Informacion_Fichero_Nuevo':
                return $this->Copiar_Informacion_Fichero_Nuevo($request);
            case 'Eliminar_FicheroMercado':
                return $this->Eliminar_FicheroMercado($request);
            default:
                return response()->json(['error' => 'Acción no válida'], 400);
        }
    }
    // METODOS GET INICIO
    public function institucionesxRegion(Request $request)
    {
        $busqueda = $request->busqueda;
        $regionperiodo = $request->regionperiodo;
        $periodoSelect = $request->periodoSelect;

        // 🔹 Buscamos instituciones y unimos con usuario (asesor)
        $instituciones = DB::table('institucion as i')
            ->leftJoin('usuario as u', 'i.asesor_id', '=', 'u.idusuario')
            ->select(
                'i.idInstitucion',
                'i.nombreInstitucion',
                DB::raw("CONCAT(u.nombres, ' ', u.apellidos) as asesor_nombre")
            )
            ->where('i.region_idregion', $regionperiodo)
            ->where('i.nombreInstitucion', 'LIKE', "%{$busqueda}%")
            ->get();

        // 🔹 Recorremos y verificamos si existe fichero en el periodo indicado
        foreach ($instituciones as $inst) {
            $fichero = DB::table('fichero_mercado')
                ->select('fm_estado')
                ->where('idInstitucion', $inst->idInstitucion)
                ->where('idperiodoescolar', $periodoSelect)
                ->first();

            if ($fichero) {
                $inst->existe_fichero = true;
                $inst->fm_estado = $fichero->fm_estado;
            } else {
                $inst->existe_fichero = false;
                $inst->fm_estado = null;
            }
        }

        return response()->json($instituciones);
    }

    public function institucionesxRegion_y_Asesor(Request $request)
    {
        $busqueda = $request->busqueda;
        $regionperiodo = $request->regionperiodo;
        $idusuario_asesor = $request->idusuario_asesor;
        $periodoSelect = $request->periodoSelect;
        // Buscamos instituciones filtradas por asesor, región y nombre
        $instituciones = DB::table('institucion as i')
            ->select('i.idInstitucion', 'i.nombreInstitucion')
            ->where('i.region_idregion', $regionperiodo)
            ->where('i.asesor_id', $idusuario_asesor)
            ->where('i.nombreInstitucion', 'LIKE', "%{$busqueda}%")
            ->get();
        // Recorremos y verificamos si existe fichero en el periodo indicado
        foreach ($instituciones as $inst) {
            $fichero = DB::table('fichero_mercado')
                ->select('fm_estado')
                ->where('idInstitucion', $inst->idInstitucion)
                ->where('idperiodoescolar', $periodoSelect)
                ->first();

            if ($fichero) {
                $inst->existe_fichero = true;
                $inst->fm_estado = $fichero->fm_estado;
            } else {
                $inst->existe_fichero = false;
                $inst->fm_estado = null;
            }
        }
        return response()->json($instituciones);
    }

    public function Editoriales_Activas_Fichero(){
        $query = DB::SELECT("SELECT * FROM editoriales ed WHERE ed.edi_estado = '1'");
        return $query;
    }
    public function Editoriales_Todo_Fichero(){
        $query = DB::SELECT("SELECT * FROM editoriales ed");
        return $query;
    }

    public function Niveles_Fichero(){
        $query = DB::SELECT("SELECT *
            FROM nivel ni
            WHERE ni.idnivel IN (18, 19, 4, 5, 6, 7, 8, 9, 11, 15, 16, 17, 20, 21, 22)
            ORDER BY FIELD(ni.idnivel, 18, 19, 4, 5, 6, 7, 8, 9, 11, 15, 16, 17, 20, 21, 22);
            ");
        return $query;
    }

    public function Fechas_Entrega_muestras_x_institucion($request)
    {
        // 1️⃣ Obtener fechas del periodo escolar
        $periodo = DB::table('periodoescolar')
            ->where('idperiodoescolar', $request->periodo_id)
            ->first();
        if (!$periodo) {
            return [
                'items' => [],
                'opciones_individuales_seleccionadas' => []
            ];
        }
        $fecha_inicio = $periodo->fecha_inicial;
        $fecha_fin    = $periodo->fecha_final;
        // 2️⃣ Query
        $query = DB::select("SELECT au.*,
                CONCAT(us_create.nombres,' ',us_create.apellidos,' (',us_create.cedula,')') AS us_create_planificacion,
                CONCAT(us_finalizo.nombres,' ',us_finalizo.apellidos,' (',us_finalizo.cedula,')') AS us_finalizo_planificacion
            FROM agenda_usuario au
            LEFT JOIN usuario us_create ON au.usuario_creador = us_create.idusuario
            LEFT JOIN usuario us_finalizo ON au.usuario_editor = us_finalizo.idusuario
            WHERE au.institucion_id = ?
            AND au.startDate BETWEEN ? AND ?
        ", [
            $request->institucion_id,
            $fecha_inicio,
            $fecha_fin
        ]);
        $todas_las_opciones = [];
        // 3️⃣ Procesar item por item
        foreach ($query as $item) {
            $op = json_decode($item->opciones, true);
            // ✅ VALIDACIÓN CLAVE
            if (!is_array($op)) {
                $op = [];
            }
            $seleccionadas = [];
            foreach ($op as $key => $value) {
                if ($value === true) {
                    $texto = preg_replace('/(?<!^)[A-Z]/', ' $0', $key);
                    $texto = str_replace('_', ' ', $texto);
                    $texto = ucwords($texto);
                    $seleccionadas[] = $texto;
                }
            }
            $item->opciones_seleccionadas = implode(', ', $seleccionadas);
            $todas_las_opciones = array_merge($todas_las_opciones, $seleccionadas);
        }
        return [
            'items' => $query,
            'opciones_individuales_seleccionadas' => array_values(array_unique($todas_las_opciones))
        ];
    }

    public function Fechas_Entrega_muestras_masivo(Request $request)
    {
        $ficheros = $request->ficheros;
        $resultado = [];
        foreach ($ficheros as $fichero) {
            // 🔹 Validación mínima
            if (!$fichero['idInstitucion'] || !$fichero['idperiodoescolar']) {
                $resultado[] = [
                    'idInstitucion'     => $fichero['idInstitucion'],
                    'idperiodoescolar'  => $fichero['idperiodoescolar'],
                    'entregas_muestras' => []
                ];
                continue;
            }
            // 1️⃣ Obtener fechas del periodo
            $periodo = DB::table('periodoescolar')
                ->where('idperiodoescolar', $fichero['idperiodoescolar'])
                ->first();
            $fecha_inicio = $periodo->fecha_inicial;
            $fecha_fin    = $periodo->fecha_final;
            // 2️⃣ Consulta principal
            $query = DB::select("
                SELECT
                    au.*,
                    CONCAT(us_create.nombres,' ',us_create.apellidos,' (',us_create.cedula,')') AS us_create_planificacion,
                    CONCAT(us_finalizo.nombres,' ',us_finalizo.apellidos,' (',us_finalizo.cedula,')') AS us_finalizo_planificacion
                FROM agenda_usuario au
                LEFT JOIN usuario us_create ON au.usuario_creador = us_create.idusuario
                LEFT JOIN usuario us_finalizo ON au.usuario_editor = us_finalizo.idusuario
                WHERE au.institucion_id = ?
                AND au.startDate BETWEEN ? AND ?
            ", [
                $fichero['idInstitucion'],
                $fecha_inicio,
                $fecha_fin
            ]);
            // 3️⃣ Procesar las opciones seleccionadas
            foreach ($query as $item) {
                // Intentar decodificar JSON
                $op = json_decode($item->opciones, true);
                // Asegurar que $op sea array para evitar error "Invalid argument supplied for foreach"
                $op = is_array($op) ? $op : [];
                $seleccionadas = [];
                foreach ($op as $key => $value) {
                    if ($value === true) {
                        // Insertar espacios antes de mayúsculas
                        $texto = preg_replace('/(?<!^)[A-Z]/', ' $0', $key);
                        // Reemplazar guiones bajos
                        $texto = str_replace('_', ' ', $texto);
                        // Capitalizar
                        $texto = ucwords($texto);
                        $seleccionadas[] = $texto;
                    }
                }
                // Devolver como string separado por comas
                $item->opciones_seleccionadas = implode(', ', $seleccionadas);
            }
            // 4️⃣ Agregar resultado final del fichero
            $resultado[] = [
                'idInstitucion'     => $fichero['idInstitucion'],
                'idperiodoescolar'  => $fichero['idperiodoescolar'],
                'entregas_muestras' => $query
            ];
        }
        return $resultado;
    }

    public function Verificar_GetFicheroSeleccionado_Existencia($request)
    {
        $query = DB::SELECT("SELECT
                fim.*,
                ins.nombreInstitucion,
                usercreated.nombres AS usercreated_nombres,
                usercreated.apellidos AS usercreated_apellidos,
                user_edit.nombres AS user_edit_nombres,
                user_edit.apellidos AS user_edit_apellidos,
                -- Campos del usuario que envía para aprobación
                user_envia.nombres AS user_envia_nombres,
                user_envia.apellidos AS user_envia_apellidos,
                JSON_UNQUOTE(JSON_EXTRACT(fim.info_enviar_para_aprobacion, '$.fecha_envia_para_aprobacion')) AS fecha_envio_aprobacion,
                -- Campos del usuario que aprueba
                user_aprob.nombres AS user_aprobacion_nombres,
                user_aprob.apellidos AS user_aprobacion_apellidos,
                JSON_UNQUOTE(JSON_EXTRACT(fim.info_aprobacion, '$.fecha_aprobacion')) AS fecha_aprobacion,
                -- Campos del usuario que rechaza
                user_rechazo.nombres AS user_rechazo_nombres,
                user_rechazo.apellidos AS user_rechazo_apellidos,
                JSON_UNQUOTE(JSON_EXTRACT(fim.info_rechazo, '$.fecha_rechazo')) AS fecha_rechazo,
                JSON_UNQUOTE(JSON_EXTRACT(fim.info_rechazo, '$.comentario_rechazo')) AS comentario_rechazo

            FROM fichero_mercado fim
            LEFT JOIN institucion ins on fim.idInstitucion = ins.idInstitucion
            LEFT JOIN usuario usercreated ON fim.user_created = usercreated.idusuario
            LEFT JOIN usuario user_edit ON fim.user_edit = user_edit.idusuario
            -- Extraemos el user_envia_para_aprobacion del JSON y hacemos join con usuario
            LEFT JOIN usuario user_envia ON user_envia.idusuario = JSON_UNQUOTE(JSON_EXTRACT(fim.info_enviar_para_aprobacion, '$.user_envia_para_aprobacion'))
            -- Extraemos el user_aprobacion del JSON y hacemos join con usuario
            LEFT JOIN usuario user_aprob ON user_aprob.idusuario = JSON_UNQUOTE(JSON_EXTRACT(fim.info_aprobacion, '$.user_aprobacion'))
            -- Extraemos el user_rechazo del JSON y hacemos join con usuario
            LEFT JOIN usuario user_rechazo ON user_rechazo.idusuario = JSON_UNQUOTE(JSON_EXTRACT(fim.info_rechazo, '$.user_rechazo'))

            WHERE fim.idInstitucion = ?
            AND fim.idperiodoescolar = ?
        ", [$request->idInstitucion, $request->periodo_id]);

        return $query;
    }

    public function Busqueda_getTraer_Datos_Fichero_x_fmd_id($request){
        $query = DB::SELECT("SELECT * FROM fichero_mercado fim
            WHERE fim.fm_id = $request->fm_id");
        return $query;
    }
    public function Busqueda_getTraer_Datos_Fichero_Autoridades_x_fmd_id($request){
        $query = DB::SELECT("SELECT fma.*, us.idusuario, us.cedula, CONCAT(us.nombres, ' ',us.apellidos) AS nombre, ina_nombre,
            us.nombres, us.apellidos, us.telefono, us.email, us.fecha_nacimiento
            FROM fichero_mercado fim
            INNER JOIN fichero_mercado_autoridades fma ON fim.fm_id = fma.fm_id
            LEFT JOIN institucion_autoridades ina ON fma.fma_cargo = ina.ina_id
            LEFT JOIN usuario us ON fma.usuario_cargo_asignado = us.idusuario
            WHERE fim.fm_id = $request->fm_id");
        return $query;
    }
    public function Busqueda_getTraer_Datos_Fichero_Detalle_x_fmd_id($request){
        $query = DB::SELECT("SELECT fmd.* FROM fichero_mercado fm
            INNER JOIN fichero_mercado_detalle fmd ON fm.fm_id = fmd.fm_id
            WHERE fm.fm_id = $request->fm_id");
        return $query;
    }
    public function Verificar_getVerificar_Aprobacion_Fichero(Request $request){
        $ids = $request->idTodas_Institucion_Asesor; // Array de ids
        $periodo_id = $request->periodo_id;
        $resultado = [];

        foreach ($ids as $idInst) {
            $fichero = DB::table('fichero_mercado as fm')
                ->where('fm.idinstitucion', $idInst)
                ->where('fm.idperiodoescolar', $periodo_id)
                ->first();

            $resultado[] = [
                'idInstitucion' => $idInst,
                'existe_fichero_aprobado' => $fichero && $fichero->fm_estado == 3 ? true : false
            ];
        }

        return response()->json($resultado);
    }
    public function Busqueda_get_Fichero_Mercado_Pendiente_Aprobacion($request){
        $query = DB::SELECT("SELECT fm.*, CONCAT(us.nombres , ' ' , us.apellidos) usuario_asesor, ins.nombreInstitucion, ins.direccionInstitucion,
            pro.nombreprovincia, ciu.nombre as nombre_ciudad, parr.parr_nombre
            FROM fichero_mercado fm
            LEFT JOIN institucion ins ON fm.idInstitucion = ins.idInstitucion
            LEFT JOIN provincia pro ON ins.idprovincia = pro.idprovincia
            LEFT JOIN ciudad ciu ON ins.ciudad_id = ciu.idciudad
            LEFT JOIN parroquia parr ON ins.parr_id = parr.parr_id
            LEFT JOIN usuario us ON ins.asesor_id = us.idusuario
            WHERE fm.fm_estado = '2'
            AND fm.idperiodoescolar = $request->idperiodoescolar");
        return $query;
    }
    public function Busqueda_get_Fichero_Mercado_Rechazados($request){
        $query = DB::SELECT("SELECT fm.*, CONCAT(us.nombres , ' ' , us.apellidos) usuario_asesor, ins.nombreInstitucion, ins.direccionInstitucion,
            pro.nombreprovincia, ciu.nombre as nombre_ciudad, parr.parr_nombre
            FROM fichero_mercado fm
            LEFT JOIN institucion ins ON fm.idInstitucion = ins.idInstitucion
            LEFT JOIN provincia pro ON ins.idprovincia = pro.idprovincia
            LEFT JOIN ciudad ciu ON ins.ciudad_id = ciu.idciudad
            LEFT JOIN parroquia parr ON ins.parr_id = parr.parr_id
            LEFT JOIN usuario us ON ins.asesor_id = us.idusuario
            WHERE fm.fm_estado = '4'
            AND fm.idperiodoescolar = $request->idperiodoescolar");
        return $query;
    }
    public function Busqueda_get_Fichero_Mercado_Aprobados($request){
        $query = DB::SELECT("SELECT fm.*, CONCAT(us.nombres , ' ' , us.apellidos) usuario_asesor, ins.nombreInstitucion, ins.direccionInstitucion,
            pro.nombreprovincia, ciu.nombre as nombre_ciudad, parr.parr_nombre
            FROM fichero_mercado fm
            LEFT JOIN institucion ins ON fm.idInstitucion = ins.idInstitucion
            LEFT JOIN provincia pro ON ins.idprovincia = pro.idprovincia
            LEFT JOIN ciudad ciu ON ins.ciudad_id = ciu.idciudad
            LEFT JOIN parroquia parr ON ins.parr_id = parr.parr_id
            LEFT JOIN usuario us ON ins.asesor_id = us.idusuario
            WHERE fm.fm_estado = '3'
            AND fm.idperiodoescolar = $request->idperiodoescolar");
        return $query;
    }
    public function CargosAutoridadesInstitucion_Activas_Fichero(){
        $query = DB::SELECT("SELECT * FROM institucion_autoridades ina WHERE ina.ina_estado = '1'");
        return $query;
    }

    public function CargosAutoridadesInstitucion_Todo_Fichero(){
        $query = DB::SELECT("SELECT * FROM institucion_autoridades ina");
        return $query;
    }

    public function Lista_Series_Fichero(){
        $query = DB::SELECT("SELECT *
            FROM series se
            WHERE se.nombre_serie NOT REGEXP '(competente|digital|prolipa|combos)'");
        return $query;
    }
    public function Lista_Areas_Fichero($request)
    {
        $idSerie = $request->id_serie;
        $sql = "SELECT DISTINCT ar.idarea, ar.nombrearea
            FROM libro li
            LEFT JOIN libros_series ls ON li.idlibro = ls.idLibro
            LEFT JOIN asignatura asi ON li.asignatura_idasignatura = asi.idasignatura
            LEFT JOIN area ar ON asi.area_idarea = ar.idarea
            LEFT JOIN series se ON ls.id_serie = se.id_serie
            WHERE ar.idarea IS NOT NULL
            AND se.id_serie = ?
        ";
        // Solo si la serie es 1, excluimos los idarea 17 y 18
        if ($idSerie == 1 || $idSerie == 3) {
            $sql .= " AND ar.idarea NOT IN (17, 18)";
        }
        $sql .= " ORDER BY ar.nombrearea ASC";
        $query = DB::select($sql, [$idSerie]);
        return $query;
    }
    public function Lista_Areas_X_nombre_serie_Fichero($request){
        $query = DB::SELECT("SELECT DISTINCT ar.idarea, ar.nombrearea
            FROM libro li
            LEFT JOIN libros_series ls ON li.idlibro = ls.idLibro
            LEFT JOIN asignatura asi ON li.asignatura_idasignatura = asi.idasignatura
            LEFT JOIN area ar ON asi.area_idarea = ar.idarea
            LEFT JOIN series se ON ls.id_serie = se.id_serie
            WHERE ar.idarea IS NOT NULL AND se.nombre_serie = '$request->nombre_Serie'
            ORDER BY ar.nombrearea ASC");
        return $query;
    }
    public function Busqueda_get_Fichero_Mercado_Todo_x_Insitucion_y_Asesor(Request $request)
    {
        $asesor_id = $request->asesor_id;
        $idperiodoescolar = $request->idperiodoescolar;
        $regionperiodo = $request->regionperiodo;
        $query = DB::table('institucion as ins')
            ->leftJoin('fichero_mercado as fm', function($join) use ($idperiodoescolar) {
                $join->on('fm.idInstitucion', '=', 'ins.idInstitucion')
                    ->where('fm.idperiodoescolar', $idperiodoescolar);
            })
            ->leftJoin('usuario as us', 'ins.asesor_id', '=', 'us.idusuario')
            ->leftJoin('provincia as pro', 'ins.idprovincia', '=', 'pro.idprovincia')
            ->leftJoin('ciudad as ciu', 'ins.ciudad_id', '=', 'ciu.idciudad')
            ->leftJoin('parroquia as parr', 'ins.parr_id', '=', 'parr.parr_id')
            ->leftJoin('periodoescolar as pe', 'fm.idperiodoescolar', '=', 'pe.idperiodoescolar')
            // LEFT JOIN de usuarios creador y editor
            ->leftJoin('usuario as usercreated', 'fm.user_created', '=', 'usercreated.idusuario')
            ->leftJoin('usuario as user_edit', 'fm.user_edit', '=', 'user_edit.idusuario')
            // LEFT JOIN verificar pedidos
            ->leftJoin('pedidos as ped', function ($join) {
                $join->on('ped.id_pedido', '=', DB::raw("(
                    SELECT p.id_pedido
                    FROM pedidos p
                    WHERE p.id_institucion = fm.idInstitucion
                    AND p.id_periodo = fm.idperiodoescolar
                    AND p.estado IN (0,1,2)
                    ORDER BY p.created_at DESC
                    LIMIT 1
                )"));
            })
            // LEFT JOIN a usuarios desde JSON usando DB::raw
            ->leftJoin('usuario as user_envia', DB::raw('user_envia.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_enviar_para_aprobacion, '$.user_envia_para_aprobacion'))"))
            ->leftJoin('usuario as user_aprob', DB::raw('user_aprob.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_aprobacion, '$.user_aprobacion'))"))
            ->leftJoin('usuario as user_rechazo', DB::raw('user_rechazo.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.user_rechazo'))"))
            ->select(
                'ins.idInstitucion',
                'ins.nombreInstitucion',
                'ins.direccionInstitucion',
                'ins.idprovincia',
                'ins.ciudad_id',
                'ins.parr_id',
                'fm.fm_id',
                'fm.fm_estado',
                'fm.fm_observacion',
                'fm.created_at',
                'fm.updated_at',
                'fm.idperiodoescolar',
                'pe.descripcion as descripcion_periodoescolar',
                DB::raw("CONCAT(us.nombres, ' ', us.apellidos) as usuario_asesor"),
                'pro.nombreprovincia',
                'ciu.nombre as nombre_ciudad',
                'parr.parr_nombre',
                // Campos de usuario creador y editor
                'usercreated.nombres as usercreated_nombres',
                'usercreated.apellidos as usercreated_apellidos',
                'user_edit.nombres as user_edit_nombres',
                'user_edit.apellidos as user_edit_apellidos',
                // Campos de pedidos
                'ped.id_pedido',
                'ped.estado as estado_pedido',
                // Campos de usuarios del JSON
                'user_envia.nombres as user_envia_nombres',
                'user_envia.apellidos as user_envia_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_enviar_para_aprobacion, '$.fecha_envia_para_aprobacion')) as fecha_envio_aprobacion"),
                'user_aprob.nombres as user_aprobacion_nombres',
                'user_aprob.apellidos as user_aprobacion_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_aprobacion, '$.fecha_aprobacion')) as fecha_aprobacion"),
                'user_rechazo.nombres as user_rechazo_nombres',
                'user_rechazo.apellidos as user_rechazo_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.fecha_rechazo')) as fecha_rechazo"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.comentario_rechazo')) as comentario_rechazo")
            )
            ->where('ins.asesor_id', $asesor_id)
            ->when($regionperiodo, function($query, $regionperiodo) {
                return $query->where('ins.region_idregion', $regionperiodo); // filtramos por región si viene
            })
            ->orderBy('fm.updated_at', 'asc') // 🔹 Orden descendente por fecha de actualización
            ->get();
        return $query;
    }
    public function Busqueda_get_Fichero_Mercado_Todo_Instituciones_Root(Request $request)
    {
        $query = DB::table('institucion as ins')
            ->join('fichero_mercado as fm', 'fm.idInstitucion', '=', 'ins.idInstitucion')
            ->leftJoin('usuario as us', 'ins.asesor_id', '=', 'us.idusuario')
            ->leftJoin('provincia as pro', 'ins.idprovincia', '=', 'pro.idprovincia')
            ->leftJoin('ciudad as ciu', 'ins.ciudad_id', '=', 'ciu.idciudad')
            ->leftJoin('parroquia as parr', 'ins.parr_id', '=', 'parr.parr_id')
            ->leftJoin('periodoescolar as pe', 'fm.idperiodoescolar', '=', 'pe.idperiodoescolar')
            // LEFT JOIN de usuarios creador y editor
            ->leftJoin('usuario as usercreated', 'fm.user_created', '=', 'usercreated.idusuario')
            ->leftJoin('usuario as user_edit', 'fm.user_edit', '=', 'user_edit.idusuario')
            // LEFT JOIN verificar pedidos
            ->leftJoin('pedidos as ped', function ($join) {
                $join->on('ped.id_pedido', '=', DB::raw("(
                    SELECT p.id_pedido
                    FROM pedidos p
                    WHERE p.id_institucion = fm.idInstitucion
                    AND p.id_periodo = fm.idperiodoescolar
                    AND p.estado IN (0,1,2)
                    ORDER BY p.created_at DESC
                    LIMIT 1
                )"));
            })
            // LEFT JOIN a usuarios desde JSON usando DB::raw
            ->leftJoin('usuario as user_envia', DB::raw('user_envia.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_enviar_para_aprobacion, '$.user_envia_para_aprobacion'))"))
            ->leftJoin('usuario as user_aprob', DB::raw('user_aprob.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_aprobacion, '$.user_aprobacion'))"))
            ->leftJoin('usuario as user_rechazo', DB::raw('user_rechazo.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.user_rechazo'))"))
            ->select(
                'ins.idInstitucion',
                'ins.nombreInstitucion',
                'ins.direccionInstitucion',
                'ins.idprovincia',
                'ins.ciudad_id',
                'ins.parr_id',
                'fm.fm_id',
                'fm.fm_estado',
                'fm.fm_observacion',
                'fm.created_at',
                'fm.updated_at',
                'fm.idperiodoescolar',
                'pe.descripcion as descripcion_periodoescolar',
                DB::raw("CONCAT(us.nombres, ' ', us.apellidos) as usuario_asesor"),
                'pro.nombreprovincia',
                'ciu.nombre as nombre_ciudad',
                'parr.parr_nombre',
                // Campos de usuario creador y editor
                'usercreated.nombres as usercreated_nombres',
                'usercreated.apellidos as usercreated_apellidos',
                'user_edit.nombres as user_edit_nombres',
                'user_edit.apellidos as user_edit_apellidos',
                // Campos de pedidos
                'ped.id_pedido',
                'ped.estado as estado_pedido',
                // Campos de usuarios del JSON
                'user_envia.nombres as user_envia_nombres',
                'user_envia.apellidos as user_envia_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_enviar_para_aprobacion, '$.fecha_envia_para_aprobacion')) as fecha_envio_aprobacion"),
                'user_aprob.nombres as user_aprobacion_nombres',
                'user_aprob.apellidos as user_aprobacion_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_aprobacion, '$.fecha_aprobacion')) as fecha_aprobacion"),
                'user_rechazo.nombres as user_rechazo_nombres',
                'user_rechazo.apellidos as user_rechazo_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.fecha_rechazo')) as fecha_rechazo"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.comentario_rechazo')) as comentario_rechazo")
            )
            ->whereNotNull('ins.asesor_id') // 🔹 Solo instituciones que tengan asesor
            ->orderBy('fm.updated_at', 'asc') // 🔹 Orden descendente por fecha de actualización
            ->get();

        return $query;
    }

    public function Busqueda_get_Fichero_Mercado_Todo_Export_Completo()
    {

        // 1️⃣ FICHEROS PRINCIPALES
        $ficheros = DB::table('fichero_mercado as fm')
            ->join('institucion as ins', 'fm.idInstitucion', '=', 'ins.idInstitucion')
            ->leftJoin('usuario as us_asesor_fichero', 'ins.asesor_id', '=', 'us_asesor_fichero.idusuario')
            ->leftJoin('usuario as us_asesor_ins', 'ins.asesor_id', '=', 'us_asesor_ins.idusuario')
            ->leftJoin('provincia as pro', 'ins.idprovincia', '=', 'pro.idprovincia')
            ->leftJoin('ciudad as ciu', 'ins.ciudad_id', '=', 'ciu.idciudad')
            ->leftJoin('parroquia as parr', 'ins.parr_id', '=', 'parr.parr_id')
            ->leftJoin('periodoescolar as pe', 'fm.idperiodoescolar', '=', 'pe.idperiodoescolar')
            ->leftJoin('usuario as usercreated', 'fm.user_created', '=', 'usercreated.idusuario')
            ->leftJoin('usuario as user_edit', 'fm.user_edit', '=', 'user_edit.idusuario')
            // ➤ LEFT JOIN usando valores dentro del JSON
            ->leftJoin('usuario as user_envia', DB::raw('user_envia.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_enviar_para_aprobacion, '$.user_envia_para_aprobacion'))"))
            ->leftJoin('usuario as user_aprob', DB::raw('user_aprob.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_aprobacion, '$.user_aprobacion'))"))
            ->leftJoin('usuario as user_rechazo', DB::raw('user_rechazo.idusuario'), '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.user_rechazo'))"))
            ->select(
                'fm.fm_id',
                'ins.idInstitucion',
                'ins.nombreInstitucion',
                'ins.direccionInstitucion',
                'ins.telefonoInstitucion',
                'ins.email',
                'ins.punto_venta',
                'ins.tipo_descripcion',
                'ins.fecha_fundacion',
                'fm.fm_trabaja_con_prolipa',
                'fm.fm_convenio',
                'fm.fm_cantidad_anios_trabaja_con_prolipa',
                'fm.fm_tipo_venta',
                'fm.idperiodoescolar',
                'fm.fm_decide_compra',
                'fm.fm_factores_inciden_en_compra',
                'fm.fm_niveles_educativos',
                'fm.fm_pensiones',
                'fm.fm_numero_aulas_completo',
                'fm.fm_cantidad_estudiantes_x_aula_completo',
                'fm.fm_SumaTotal_EstudiantesxAula',
                'fm.fm_cantidad_real_estudiantes',
                'fm.fm_observacion',
                'fm.fm_estado',
                'pe.descripcion as descripcion_periodoescolar',
                DB::raw("CONCAT(us_asesor_fichero.nombres, ' ', us_asesor_fichero.apellidos) as usuario_asesor_fichero"),
                DB::raw("CONCAT(us_asesor_ins.nombres, ' ', us_asesor_ins.apellidos) as asesor_institucion"),
                'pro.nombreprovincia',
                'ciu.nombre as nombre_ciudad',
                'parr.parr_nombre',
                // ➤ Campos del usuario que crea
                'usercreated.nombres as usercreated_nombres',
                'usercreated.apellidos as usercreated_apellidos',
                'fm.created_at',
                // ➤ Campos del usuario que edita
                'user_edit.nombres as user_edit_nombres',
                'user_edit.apellidos as user_edit_apellidos',
                'fm.updated_at',
                // ➤ Campos del usuario que envía para aprobación
                'user_envia.nombres as user_envia_nombres',
                'user_envia.apellidos as user_envia_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_enviar_para_aprobacion, '$.fecha_envia_para_aprobacion')) as fecha_envio_aprobacion"),
                // ➤ Campos del usuario que aprueba
                'user_aprob.nombres as user_aprobacion_nombres',
                'user_aprob.apellidos as user_aprobacion_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_aprobacion, '$.fecha_aprobacion')) as fecha_aprobacion"),
                // ➤ Campos del usuario que rechaza
                'user_rechazo.nombres as user_rechazo_nombres',
                'user_rechazo.apellidos as user_rechazo_apellidos',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.fecha_rechazo')) as fecha_rechazo"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fm.info_rechazo, '$.comentario_rechazo')) as comentario_rechazo"),
            )
            ->whereNotNull('ins.asesor_id')
            ->orderBy('fm.fm_id', 'asc')
            ->get();

        // 2️⃣ AÑADIR RELACIONES (autoridades y detalles)
        $ficheros->transform(function ($item) {
            // Autoridades completas
            $item->autoridades = DB::table('fichero_mercado_autoridades as fma')
                ->join('usuario as u', 'fma.usuario_cargo_asignado', '=', 'u.idusuario')
                ->join('institucion_autoridades as ina', 'fma.fma_cargo', '=', 'ina.ina_id')
                ->where('fma.fm_id', $item->fm_id)
                ->select(
                    'fma.fma_id',
                    'fma.fma_cargo',
                    'ina.ina_nombre as cargo',
                    'u.idusuario',
                    'u.nombres',
                    'u.apellidos',
                    'u.cedula',
                    'u.telefono',
                    'u.email',
                    'u.fecha_nacimiento',
                    'fma.created_at',
                    'fma.updated_at'
                )
                ->get();

            // Detalles (libros)
            $item->detalles = DB::table('fichero_mercado_detalle as fmd')
                ->where('fmd.fm_id', $item->fm_id)
                ->select(
                    'fmd.fmd_id',
                    'fmd.fmd_nombre_libro',
                    'fmd.fmd_niveles_editoriales',
                    'fmd.created_at',
                    'fmd.updated_at'
                )
                ->get();

            return $item;
        });

        // 3️⃣ Retornar en formato estándar
        return response()->json([
            'status' => 1,
            'total' => $ficheros->count(),
            'data' => $ficheros
        ]);
    }
    public function Listar_Ficheros_Por_Institucion($request){
        $idInstitucion = $request->idInstitucion;
        $query = DB::SELECT("SELECT fm.fm_id, fm.idperiodoescolar, fm.fm_estado, pe.descripcion AS descripcion_periodoescolar, fm.idInstitucion
            FROM fichero_mercado fm
            LEFT JOIN periodoescolar pe ON fm.idperiodoescolar = pe.idperiodoescolar
            WHERE fm.idInstitucion = $idInstitucion
            ORDER BY fm.idperiodoescolar ASC");
        return $query;
    }
    public function Listar_Beneficiarios_por_insitucion_y_periodo($request){
        $idInstitucion = $request->institucion_id;
        $idperiodoescolar = $request->periodo_id;
        $query = DB::SELECT("SELECT CONCAT(us.nombres,' ', us.apellidos) AS beneficiario_nombres, us.cedula, us.email, pb.comision,
            pb.banco, pb.num_cuenta, pb.tipo_cuenta
            FROM pedidos_beneficiarios pb
            LEFT JOIN usuario us ON pb.id_usuario = us.idusuario
            WHERE pb.idInstitucion = $idInstitucion
            AND pb.idperiodoescolar = $idperiodoescolar");
        return $query;
    }
    public function Verificar_EstadoFichero($request){
        $fm_id = $request->fm_id;
        $query = DB::SELECT("SELECT fm.fm_id, fm.idInstitucion, fm.asesor_id, fm.idperiodoescolar, fm.fm_estado,
            ins.nombreInstitucion, pe.id_pedido, pe.estado AS estado_pedido
            FROM fichero_mercado fm
            LEFT JOIN institucion ins ON ins.idInstitucion = fm.idInstitucion
            LEFT JOIN pedidos pe
            ON pe.id_pedido = (
                SELECT p.id_pedido
                FROM pedidos p
                WHERE p.id_institucion = fm.idInstitucion
                  AND p.id_periodo = fm.idperiodoescolar
                  AND p.estado IN (0,1,2)
                ORDER BY p.created_at DESC
                LIMIT 1
            )
            WHERE fm.fm_id = $fm_id");
        return $query;
    }
    // METODOS GET FIN
    // METODOS POST INICIO
    public function GuardarDatos_guardarFicheroCabecera(Request $request)
    {
        DB::beginTransaction();
        try {
            $fm_id = $request->input('fm_id');
            $usuarioRoot = filter_var($request->input('usuarioRoot'), FILTER_VALIDATE_BOOLEAN);

            // VALIDACIÓN: no permitir fm_tipo_venta vacío si ya tiene pedidos
            $tipoVenta = $request->input('fm_tipo_venta');
            $idPeriodo = $request->input('idperiodoescolar');
            $idInstitucion = $request->input('idInstitucion');
            $existePedido = DB::table('pedidos')
                ->where('id_periodo', $idPeriodo)
                ->where('id_institucion', $idInstitucion)
                ->whereIn('estado', [0, 1])
                ->exists();
            // Si tiene pedido y fm_tipo_venta viene vacío/null/undefined
            if ($existePedido && ($tipoVenta === null || $tipoVenta === "")) {
                DB::rollBack(); // opcional
                return response()->json([
                    'status' => 2,
                    'message' => 'No puede dejar vacío el tipo de venta porque la institución ya tiene un pedido registrado.'
                ]);
            }
            // *********************************************************

            // 🔒 Validación para usuarios NO root
            if (!$usuarioRoot && $fm_id) {
                $ficheroExistente = Fichero_Mercado::find($fm_id);

                if (!$ficheroExistente) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'No se encontró el fichero especificado.'
                    ]);
                }

                // Si el fichero no está en estado activo (1), no se puede editar
                if ($ficheroExistente->fm_estado != 1 && $ficheroExistente->fm_estado != 4) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'No se puede editar el fichero porque no está en estado activo o devuelto.'
                    ]);
                }
            }

            if ($fm_id) {
                // Edición
                $fichero = Fichero_Mercado::findOrFail($fm_id);
                $fichero->updated_at = now();
                $fichero->user_edit = $request->input('user_edit');
            } else {
                // Creación
                $fichero = new Fichero_Mercado();
                $fichero->asesor_id = $request->input('asesor_id');
                $fichero->user_created = $request->input('user_created');
                $fichero->user_edit = $request->input('user_edit');
                $fichero->created_at = now();
            }

            // Actualizar todos los campos siempre
            $fichero->idInstitucion = $request->input('idInstitucion');
            $fichero->idperiodoescolar = $request->input('idperiodoescolar');
            $fichero->id_logo_empresa = $request->input('id_logo_empresa');
            $fichero->fm_trabaja_con_prolipa = $request->input('fm_trabaja_con_prolipa');
            $fichero->fm_convenio = $request->input('fm_convenio');
            $fichero->fm_cantidad_anios_trabaja_con_prolipa = $request->input('fm_cantidad_anios_trabaja_con_prolipa');
            $fichero->fm_tipo_venta = $request->input('fm_tipo_venta');
            $fichero->fm_decide_compra = json_encode($request->input('fm_decide_compra'));
            $fichero->fm_factores_inciden_en_compra = json_encode($request->input('fm_factores_inciden_en_compra'));
            $fichero->fm_niveles_educativos = json_encode($request->input('fm_niveles_educativos'));
            $fichero->fm_pensiones = json_encode($request->input('fm_pensiones'));
            $fichero->fm_numero_aulas_completo = json_encode($request->input('fm_numero_aulas_completo'));
            $fichero->fm_cantidad_estudiantes_x_aula_completo = json_encode($request->input('fm_cantidad_estudiantes_x_aula_completo'));
            $fichero->fm_SumaTotal_EstudiantesxAula = json_encode($request->input('fm_SumaTotal_EstudiantesxAula'));
            $fichero->fm_cantidad_real_estudiantes = json_encode($request->input('fm_cantidad_real_estudiantes'));
            $fichero->fm_observacion = $request->input('fm_observacion');

            $fichero->save();

            $mensaje = $fm_id ? 'Fichero actualizado correctamente' : 'Fichero creado correctamente';
            $fm_id = $fichero->fm_id;

            DB::commit();
            return response()->json([
                'status' => 1,
                'fm_id' => $fm_id,
                'message' => $mensaje
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function GuardarDatos_guardarFicheroAutoridades(Request $request)
    {
        DB::beginTransaction();
        try {
            $fm_id = $request->input('fm_id');
            $personalArray = $request->input('personal_plantel_array', []);

            if (!$fm_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'fm_id es requerido'
                ]);
            }

            // 1. Borrar los registros existentes para este fm_id
            Fichero_Mercado_Autoridades::where('fm_id', $fm_id)->delete();

            // 2. Insertar los registros recibidos
            foreach ($personalArray as $persona) {
                Fichero_Mercado_Autoridades::create([
                    'fm_id' => $fm_id,
                    'usuario_cargo_asignado' => $persona['idusuario'],
                    'fma_cargo' => $persona['fma_cargo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json([
                'status' => 1,
                'message' => 'Autoridades guardadas correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function GuardarDatos_guardarFicheroDetalleEditoriales(Request $request)
    {
        DB::beginTransaction();

        try {
            $fm_id = $request->input('fm_id');
            $nivelesEditoriales = $request->input('fmd_niveles_editoriales', []);

            if (!$fm_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'fm_id es requerido'
                ]);
            }

            // 1. Eliminar registros previos para este fm_id
            Fichero_Mercado_Detalle::where('fm_id', $fm_id)->delete();

            // 2. Insertar nuevos registros
            foreach ($nivelesEditoriales as $nivel) {
                Fichero_Mercado_Detalle::create([
                    'fm_id' => $fm_id,
                    'fmd_nombre_libro' => $nivel['nombre_Libros'],
                    'fmd_niveles_editoriales' => json_encode($nivel['grados']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Detalle de editoriales guardado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
   public function GuardarDatos_EnviarParaAprobacion($request)
    {
        DB::beginTransaction();
        try {
            $fm_id = $request->input('fm_id');
            $user_envia_para_aprobacion = $request->input('user_envia_para_aprobacion');
            if (!$fm_id || !$user_envia_para_aprobacion) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan datos requeridos.'
                ]);
            }
            // Buscar el fichero
            $fichero = Fichero_Mercado::find($fm_id);
            if (!$fichero) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El fichero no existe.'
                ]);
            }
            // ✅ Verificar que esté pendiente de aprobación (estado = 2)
            if ($fichero->fm_estado == '2') {
                return response()->json([
                    'status' => 0,
                    'message' => 'El fichero ya se encuentra en estado pendiente de aprobación.'
                ]);
            }
            // Buscar el fichero
            $fichero = Fichero_Mercado::findOrFail($fm_id);
            // Cambiar el estado a 2 (enviado para aprobación)
            $fichero->fm_estado = '2';
            // Guardar info de aprobación como JSON
            $fichero->info_enviar_para_aprobacion = json_encode([
                'user_envia_para_aprobacion' => $user_envia_para_aprobacion,
                'fecha_envia_para_aprobacion' => now()->toDateTimeString()
            ]);
            $fichero->user_edit = $user_envia_para_aprobacion;
            // Actualizar timestamp de edición
            $fichero->updated_at = now();
            $fichero->save();
            //NOTIFICACION
             // Registrar notificación
            $formData = (Object)[
                'nombre'        => 'Fichero Pendiente de aprobación',
                'descripcion'   => '',
                'tipo'          => '6',
                'user_created'  => $user_envia_para_aprobacion,
                'id_periodo'    => $fichero->idperiodoescolar,
                'id_padre'      => $fm_id,
            ];
            $notificacion = $this->verificacionRepository->save_notificacion($formData);
            $channel = 'admin.notifications_verificaciones';
            $event = 'NewNotification';
            $data = [
                'message' => 'Nueva notificación',
            ];
            // notificacion en pusher
            $this->NotificacionRepository->notificacionVerificaciones($channel, $event, $data);
            DB::commit();
            return response()->json([
                'status' => 1,
                'message' => 'Fichero enviado para aprobación correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 0,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function GuardarDatos_GuardarComoActivo_Fichero($request)
    {
        DB::beginTransaction();
        try {
            $fm_id = $request->input('fm_id');
            $user_edit = $request->input('user_edit');
            if (!$fm_id || !$user_edit) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan datos requeridos.'
                ]);
            }
            // Buscar el fichero
            $fichero = Fichero_Mercado::findOrFail($fm_id);
            // Cambiar el estado a 2 (enviado para aprobación)
            $fichero->fm_estado = '1';
            $fichero->user_edit = $user_edit;
            $fichero->updated_at = now();
            $fichero->save();
            //NOTIFICACION
             // Registrar notificación
            $formData = (Object)[
                'nombre'        => 'Fichero Activado',
                'descripcion'   => '',
                'tipo'          => '9',
                'user_created'  => $user_edit,
                'id_periodo'    => $fichero->idperiodoescolar,
                'id_padre'      => $fm_id,
            ];
            $notificacion = $this->verificacionRepository->save_notificacion($formData);
            $channel = 'admin.notifications_verificaciones';
            $event = 'NewNotification';
            $data = [
                'message' => 'Nueva notificación',
            ];
            // notificacion en pusher
            $this->NotificacionRepository->notificacionVerificaciones($channel, $event, $data);
            DB::commit();
            return response()->json([
                'status' => 1,
                'message' => 'Fichero activado correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function GuardarDatos_FicheroAprobado($request)
    {
        DB::beginTransaction();
        try {
            $fm_id = $request->input('fm_id');
            $user_aprobacion = $request->input('user_aprobacion');
            if (!$fm_id || !$user_aprobacion) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan datos requeridos.'
                ]);
            }
            // Buscar el fichero
            $fichero = Fichero_Mercado::find($fm_id);
            if (!$fichero) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El fichero no existe.'
                ]);
            }
            // ✅ Verificar que esté pendiente de aprobación (estado = 2)
            if ($fichero->fm_estado != '2') {
                return response()->json([
                    'status' => 0,
                    'message' => 'El fichero no se encuentra pendiente de aprobación.'
                ]);
            }
            /** ACTUALIZAR NOTIFICACIÓN SI EXISTE */
            DB::table('notificaciones_general')
                ->where('tipo', 6)
                ->where('id_padre', $fm_id)
                ->where('estado', 0)
                ->update([
                    'estado' => 1,
                    'updated_at' => now(),
                    'fecha_finaliza' => now(),
                    'user_finaliza' => $user_aprobacion
                ]);
            // Cambiar el estado a 2 (enviado para aprobación)
            $fichero->fm_estado = '3';
            // Guardar info de aprobación como JSON
            $fichero->info_aprobacion = json_encode([
                'user_aprobacion' => $user_aprobacion,
                'fecha_aprobacion' => now()->toDateTimeString()
            ]);
            $fichero->user_edit = $user_aprobacion;
            $fichero->updated_at = now();
            $fichero->save();
            //NOTIFICACION
             // Registrar notificación
            $formData = (Object)[
                'nombre'        => 'Fichero Aprobado',
                'descripcion'   => '',
                'tipo'          => '7',
                'user_created'  => $user_aprobacion,
                'id_periodo'    => $fichero->idperiodoescolar,
                'id_padre'      => $fm_id,
            ];
            $notificacion = $this->verificacionRepository->save_notificacion($formData);
            $channel = 'admin.notifications_verificaciones';
            $event = 'NewNotification';
            $data = [
                'message' => 'Nueva notificación',
            ];
            // notificacion en pusher
            $this->NotificacionRepository->notificacionVerificaciones($channel, $event, $data);
            DB::commit();
            return response()->json([
                'status' => 1,
                'message' => 'Fichero aprobado correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function GuardarDatos_FicheroRechazado($request)
    {
        DB::beginTransaction();
        try {
            $fm_id = $request->input('fm_id');
            $user_aprobacion = $request->input('user_aprobacion');
            $comentario_rechazo = $request->input('comentario_rechazo');
            if (!$fm_id || !$user_aprobacion || !$comentario_rechazo) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan datos requeridos.'
                ]);
            }
            // Buscar el fichero
            $fichero = Fichero_Mercado::find($fm_id);
            if (!$fichero) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El fichero no existe.'
                ]);
            }
            // ✅ Verificar que esté pendiente de aprobación (estado = 2) o aprobado (estado = 3)
            if ($fichero->fm_estado != '2' && $fichero->fm_estado != '3') {
                return response()->json([
                    'status' => 0,
                    'message' => 'El fichero no se encuentra pendiente de aprobación ni aprobado.'
                ]);
            }
            /** ACTUALIZAR NOTIFICACIÓN SI EXISTE */
            DB::table('notificaciones_general')
                ->where('tipo', 6)
                ->where('id_padre', $fm_id)
                ->where('estado', 0)
                ->update([
                    'estado' => 1,
                    'updated_at' => now(),
                    'fecha_finaliza' => now(),
                    'user_finaliza' => $user_aprobacion
                ]);
            // Cambiar el estado a 2 (enviado para aprobación)
            $fichero->fm_estado = '4';
            // Guardar info de aprobación como JSON
            $fichero->info_rechazo = json_encode([
                'user_rechazo' => $user_aprobacion,
                'fecha_rechazo' => now()->toDateTimeString(),
                'comentario_rechazo' => $comentario_rechazo
            ]);
            $fichero->user_edit = $user_aprobacion;
            $fichero->updated_at = now();
            $fichero->save();
            //NOTIFICACION
             // Registrar notificación
            $formData = (Object)[
                'nombre'        => 'Fichero Devuelto',
                'descripcion'   => '',
                'tipo'          => '8',
                'user_created'  => $user_aprobacion,
                'id_periodo'    => $fichero->idperiodoescolar,
                'id_padre'      => $fm_id,
            ];
            $notificacion = $this->verificacionRepository->save_notificacion($formData);
            $channel = 'admin.notifications_verificaciones';
            $event = 'NewNotification';
            $data = [
                'message' => 'Nueva notificación',
            ];
            // notificacion en pusher
            $this->NotificacionRepository->notificacionVerificaciones($channel, $event, $data);
            DB::commit();
            return response()->json([
                'status' => 1,
                'message' => 'Fichero rechazado correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function Copiar_Informacion_Fichero_Nuevo(Request $request)
    {
        // return $request;
        DB::beginTransaction();
        try {
            // =====================================================
            // 1. Datos del request
            // =====================================================
            $fm_id_Origen     = $request->input('idFicheroOrigen');
            $idPeriodoDestino = $request->input('idPeriodoDestino');
            $idPeriodoOrigen  = $request->input('idPeriodoOrigen');
            $idInstitucion    = $request->input('idInstitucion');
            $user_created     = $request->input('user_created');
            $secciones        = $request->input('secciones_a_copiar', []);
            if (!$fm_id_Origen || !$idPeriodoDestino || !$idInstitucion || !$user_created) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Faltan datos requeridos.'
                ]);
            }
            // =====================================================
            // 2. Buscar fichero origen
            // =====================================================
            $ficheroOrigen = Fichero_Mercado::find($fm_id_Origen);
            if (!$ficheroOrigen) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El fichero origen no existe.'
                ]);
            }
            // =====================================================
            // 3. Obtener asesor_id desde institución
            // =====================================================
            $institucion = Institucion::where('idInstitucion', $idInstitucion)->first();
            if (!$institucion) {
                return response()->json([
                    'status' => 0,
                    'message' => 'La institución no existe.'
                ]);
            }
            $asesor_id = $institucion->asesor_id;
            // =====================================================
            // 4. DEFAULTS (CUANDO SECCIÓN = FALSE)
            // =====================================================
            $defaultFactores = [
                "precio" => false,
                "contenido" => false,
                "pedagogia" => false,
                "servicios" => false
            ];
            $defaultNivelesEducativos = [
                "iniciales" => false,
                "preparatoria" => false,
                "elemental" => false,
                "medio" => false,
                "superior" => false,
                "bachillerato" => false
            ];
            $defaultPensiones = [
                "iniciales" => null,
                "preparatoria" => null,
                "elemental" => null,
                "medio" => null,
                "superior" => null,
                "bachillerato" => null
            ];
            $defaultNumeroAulas = [
                ["idnivel"=>18,"numeroAulas"=>0],
                ["idnivel"=>19,"numeroAulas"=>0],
                ["idnivel"=>4,"numeroAulas"=>0],
                ["idnivel"=>5,"numeroAulas"=>0],
                ["idnivel"=>6,"numeroAulas"=>0],
                ["idnivel"=>7,"numeroAulas"=>0],
                ["idnivel"=>8,"numeroAulas"=>0],
                ["idnivel"=>9,"numeroAulas"=>0],
                ["idnivel"=>11,"numeroAulas"=>0],
                ["idnivel"=>15,"numeroAulas"=>0],
                ["idnivel"=>16,"numeroAulas"=>0],
                ["idnivel"=>17,"numeroAulas"=>0],
                ["idnivel"=>20,"numeroAulas"=>0],
                ["idnivel"=>21,"numeroAulas"=>0],
                ["idnivel"=>22,"numeroAulas"=>0],
            ];
            $defaultEstudiantesXAula = array_map(function ($n) {
                return [
                    "idnivel" => $n["idnivel"],
                    "estudiantesXAula" => []
                ];
            }, $defaultNumeroAulas);
            // =====================================================
            // 5. Crear nuevo Fichero_Mercado
            // =====================================================
            $nuevoFichero = $ficheroOrigen->replicate();
            $nuevoFichero->idperiodoescolar = $idPeriodoDestino;
            $nuevoFichero->asesor_id = $asesor_id;
            // ---------------- DATOS COMERCIALES ----------------
            if (!($secciones['datos_comerciales'] ?? false)) {
                $nuevoFichero->fm_trabaja_con_prolipa = 1;
                $nuevoFichero->fm_convenio = 0;
                $nuevoFichero->fm_cantidad_anios_trabaja_con_prolipa = null;
                $nuevoFichero->fm_tipo_venta = null;
            }
            // ---------------- FACTORES ----------------
            if (!($secciones['factores'] ?? false)) {
                $nuevoFichero->fm_factores_inciden_en_compra = json_encode($defaultFactores);
            }
            // ---------------- AULAS ----------------
            if (!($secciones['aulas'] ?? false)) {
                $nuevoFichero->fm_niveles_educativos = json_encode($defaultNivelesEducativos);
                $nuevoFichero->fm_pensiones = json_encode($defaultPensiones);
                $nuevoFichero->fm_numero_aulas_completo = json_encode($defaultNumeroAulas);
                $nuevoFichero->fm_cantidad_estudiantes_x_aula_completo = json_encode($defaultEstudiantesXAula);
                $nuevoFichero->fm_SumaTotal_EstudiantesxAula = 0;
                $nuevoFichero->fm_cantidad_real_estudiantes = 0;
            }
            // ---------------- OBSERVACIÓN ----------------
            if (!($secciones['observacion'] ?? false)) {
                $nuevoFichero->fm_observacion = null;
            }
            // ---------------- RESET DE ESTADOS ----------------
            $nuevoFichero->fm_fecha_aprobacion_definicion = null;
            $nuevoFichero->fm_estado = '1';
            $nuevoFichero->info_enviar_para_aprobacion = null;
            $nuevoFichero->info_aprobacion = null;
            $nuevoFichero->info_rechazo = null;
            // Auditoría
            $nuevoFichero->user_created = $user_created;
            $nuevoFichero->user_edit = $user_created;
            $nuevoFichero->created_at = now();
            $nuevoFichero->updated_at = now();
            $nuevoFichero->save();
            $nuevoFmId = $nuevoFichero->fm_id;
            // =====================================================
            // 6. EDITORIALES (Fichero_Mercado_Detalle)
            // =====================================================
            if ($secciones['editoriales'] ?? false) {
                $detalles = Fichero_Mercado_Detalle::where('fm_id', $fm_id_Origen)->get();
                foreach ($detalles as $detalle) {
                    $nuevoDetalle = $detalle->replicate();
                    $nuevoDetalle->fm_id = $nuevoFmId;
                    $nuevoDetalle->created_at = now();
                    $nuevoDetalle->updated_at = now();
                    $nuevoDetalle->save();
                }
            }
            // =====================================================
            // 7. PERSONAL (Fichero_Mercado_Autoridades)
            // =====================================================
            if ($secciones['personal'] ?? false) {
                $autoridades = Fichero_Mercado_Autoridades::where('fm_id', $fm_id_Origen)->get();
                foreach ($autoridades as $autoridad) {
                    $nuevaAutoridad = $autoridad->replicate();
                    $nuevaAutoridad->fm_id = $nuevoFmId;
                    $nuevaAutoridad->created_at = now();
                    $nuevaAutoridad->updated_at = now();
                    $nuevaAutoridad->save();
                }
            }
            // =====================================================
            // 8. BENEFICIARIOS (pedidos_beneficiarios)
            // =====================================================
            if ($secciones['beneficiarios'] ?? false) {
                $beneficiarios = Beneficiarios::where('idInstitucion', $idInstitucion)
                    ->where('idperiodoescolar', $idPeriodoOrigen)
                    ->get();
                foreach ($beneficiarios as $beneficiario) {
                    $nuevoBeneficiario = $beneficiario->replicate();
                    $nuevoBeneficiario->id_beneficiario_pedido = null;
                    $nuevoBeneficiario->id_pedido = 0;
                    $nuevoBeneficiario->idperiodoescolar = $idPeriodoDestino;
                    $nuevoBeneficiario->valor = 0;
                    $nuevoBeneficiario->porcentaje_real = 0.00;
                    $nuevoBeneficiario->comision_real = 0.00;
                    $nuevoBeneficiario->verificado = 0;
                    $nuevoBeneficiario->fecha_verificacion = null;
                    $nuevoBeneficiario->user_verificado = null;
                    $nuevoBeneficiario->estado = 1;
                    $nuevoBeneficiario->created_at = now();
                    $nuevoBeneficiario->updated_at = now();
                    $nuevoBeneficiario->save();
                }
            }

            DB::commit();
            return response()->json([
                'status' => 1,
                'message' => 'Fichero copiado correctamente.',
                'fm_id_nuevo' => $nuevoFmId
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 0,
                'message' => 'Error al copiar el fichero.',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function Eliminar_FicheroMercado(Request $request)
    {
        DB::beginTransaction();
        try {
            $fm_id = $request->input('fm_id');
            if (!$fm_id) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'fm_id es requerido.'
                ]);
            }
            /* ===============================
            * 1. CONSULTA PRINCIPAL DE VALIDACIÓN
            * =============================== */
            $query = DB::select("SELECT
                    fm.fm_id,
                    fm.idInstitucion,
                    fm.idperiodoescolar,
                    fm.fm_estado,
                    pe.id_pedido,
                    pe.estado AS estado_pedido
                FROM fichero_mercado fm
                LEFT JOIN pedidos pe
                    ON pe.id_pedido = (
                        SELECT p.id_pedido
                        FROM pedidos p
                        WHERE p.id_institucion = fm.idInstitucion
                        AND p.id_periodo = fm.idperiodoescolar
                        AND p.estado IN (0,1,2)
                        ORDER BY p.created_at DESC
                        LIMIT 1
                    )
                WHERE fm.fm_id = ?
            ", [$fm_id]);
            if (empty($query)) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'Fichero de mercado no encontrado.'
                ]);
            }
            $data = $query[0];
            /* ===============================
            * 2. VALIDACIONES DE ESTADOS
            * =============================== */
            // Validar fm_estado controlado
            if (!in_array($data->fm_estado, ['0','1','2','3','4'])) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'Estado del fichero no controlado.'
                ]);
            }
            // Caso: pedido activo
            if (
                !is_null($data->id_pedido) &&
                in_array($data->estado_pedido, [0,1])
            ) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'No se puede eliminar: tiene un pedido activo.'
                ]);
            }
            // Caso permitido:
            // - sin pedido
            // - pedido cerrado (estado 2)
            /* ===============================
            * 3. ELIMINACIONES
            * =============================== */
            // 3.1 Detalle
            DB::table('fichero_mercado_detalle')
                ->where('fm_id', $fm_id)
                ->delete();
            // 3.2 Autoridades
            DB::table('fichero_mercado_autoridades')
                ->where('fm_id', $fm_id)
                ->delete();
            // 3.3 Beneficiarios (por periodo + institución)
            DB::table('pedidos_beneficiarios')
                ->where('idperiodoescolar', $data->idperiodoescolar)
                ->where('idInstitucion', $data->idInstitucion)
                ->delete();
            // 3.4 Fichero Mercado (FINAL)
            DB::table('fichero_mercado')
                ->where('fm_id', $fm_id)
                ->delete();
            $this->ActualizarAutoIncrementable_TodoFichero_Beneficiarios();
            DB::commit();
            return response()->json([
                'status'  => 1,
                'message' => 'Fichero de mercado eliminado correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 0,
                'message' => 'Error al eliminar el fichero.',
                'error'   => $e->getMessage()
            ]);
        }
    }
    // METODOS POST FIN
    // METODOS PRIVADOS INICIO
    private function ActualizarAutoIncrementable_TodoFichero_Beneficiarios()
    {
        // Reajustar el autoincremento de fichero_mercado
        $ultimoId_Fichero_Mercado  = Fichero_Mercado::max('fm_id') + 1;
        DB::statement('ALTER TABLE fichero_mercado AUTO_INCREMENT = ' . $ultimoId_Fichero_Mercado);
        // Reajustar el autoincremento de fichero_mercado_autoridades
        $ultimoId_Fichero_Mercado_Autoridades  = Fichero_Mercado_Autoridades::max('fma_id') + 1;
        DB::statement('ALTER TABLE fichero_mercado_autoridades AUTO_INCREMENT = ' . $ultimoId_Fichero_Mercado_Autoridades);
        // Reajustar el autoincremento de fichero_mercado_detalle
        $ultimoId_Fichero_Mercado_Detalle  = Fichero_Mercado_Detalle::max('fmd_id') + 1;
        DB::statement('ALTER TABLE fichero_mercado_detalle AUTO_INCREMENT = ' . $ultimoId_Fichero_Mercado_Detalle);
        // Reajustar el autoincremento de pedidos_beneficiarios
        $ultimoId_pedidos_beneficiarios  = Beneficiarios::max('id_beneficiario_pedido') + 1;
        DB::statement('ALTER TABLE pedidos_beneficiarios AUTO_INCREMENT = ' . $ultimoId_pedidos_beneficiarios);
    }
    // METODOS PRIVADOS FIN
}
