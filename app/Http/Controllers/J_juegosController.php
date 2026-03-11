<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\J_juegos;

class J_juegosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $juegos = DB::SELECT("SELECT * FROM j_juegos");

        return $juegos;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function juego_y_contenido($id)
    {
        $juego= DB::SELECT("SELECT * FROM j_juegos jj WHERE jj.id_juego = $id");
        if(!empty($juego)){
            foreach ($juego as $key => $value) {
                $preguntas = DB::SELECT("SELECT * FROM j_contenido_juegos WHERE id_juego = ?",[$value->id_juego]);

                $temas = DB::SELECT("SELECT t.nombre_tema, t.unidad, a.nombreasignatura FROM j_temas_por_juego jt, temas t, asignatura a WHERE jt.id_tema = t.id AND t.id_asignatura = a.idasignatura AND jt.id_juego = ? ORDER BY t.unidad",[$value->id_juego]);
                $data['items'][$key] = [
                    'id_juego' => $value->id_juego,
                    'id_tipo_juego' => $value->id_tipo_juego,
                    'id_docente' => $value->id_docente,
                    'puntos' => $value->puntos,
                    'duracion' => $value->duracion,
                    'nombre_juego' => $value->nombre_juego,
                    'descripcion_juego' => $value->descripcion_juego,
                    'imagen_juego' => $value->imagen_juego,
                    'fecha_inicio' => $value->fecha_inicio,
                    'fecha_fin' => $value->fecha_fin,
                    'bloque_curricular'=>$value->bloque_curricular,
                    'grado'=>$value->grado,
                    'destrezas'=>$value->destrezas,
                    'habilidades'=>$value->habilidades,
                    'elaborado_por'=>$value->elaborado_por,
                    'intencion_didactica'=>$value->intencion_didactica,
                    'consigna'=>$value->consigna,
                    'consideraciones'=>$value->consideraciones,
                    'preguntas'=>$preguntas,
                    'temas'=>$temas,
                ];
            }
        }else{
            $data = [];
        }
        return $data;
        // return $juego;
    }
     /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function calificacion_estudiante(Request $request)
    {
        $calificacion= DB::SELECT("SELECT * FROM j_calificaciones jc WHERE jc.id_juego = $request->id_juego AND jc.id_usuario = $request->idusuario");

        return $calificacion;
    }


    public function asignar_cursos_juego(Request $request)
    {
        $cursos= DB::SELECT("SELECT * FROM `cursos_has_juego` WHERE `codigo_curso` = '$request->codigo_curso' AND `id_juego` = $request->id_juego");

        if( empty($cursos) ){
            $juegos= DB::INSERT("INSERT INTO `cursos_has_juego`(`codigo_curso`, `id_juego`) VALUES ('$request->codigo_curso', $request->id_juego)");

            return ["status" => "1", "message" => "Asignado correctamente"];
        }else{
            return ["status" => "0", "message" => "Este juego ya se encuentra asignado a este curso"];
        }

    }


    // public function calificacionPonchado(Request $request)
    // {
    //     $califica = DB::INSERT("INSERT INTO  j_calificaciones(id_juego, id_usuario, calificacion) VALUES (?,?,?)", [$request->id_juego, $request->id_usuario, $request->calificacion,]);

    // }

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
        if( $request->id_juego ){
            $juego = J_juegos::find($request->id_juego);
        }else{
            $juego = new J_juegos();
        }

        $juego->id_tipo_juego = $request->id_tipo_juego;
        $juego->id_docente = $request->id_docente;
        $juego->nombre_juego = $request->nombre_juego;
        $juego->descripcion_juego = $request->descripcion_juego;
        $juego->puntos = $request->puntos;
        $juego->duracion = $request->duracion;
        $juego->fecha_inicio = $request->fecha_inicio;
        $juego->fecha_fin = $request->fecha_fin;
        $juego->estado = $request->estado;

        $juego->save();

        return $juego;
    }


    public function guardarTemasJuego(Request $request)
    {
        $temas = explode(",", $request->id_temas);
        $tam = sizeof($temas);

        for( $i=0; $i<$tam; $i++ ){
            $tema= DB::INSERT("INSERT INTO j_temas_por_juego(id_juego, id_tema) VALUES (?,?)", [$request->id_juego, $temas[$i]]);
        }

    }


    public function eliminarTemasJuego($id)
    {
        $tema= DB::DELETE("DELETE FROM j_temas_por_juego WHERE id_juego=$id");

    }


    public function j_guardar_calificacion(Request $request)
    {
        $califica = DB::INSERT("INSERT INTO  j_calificaciones(id_juego, codigo_curso, id_usuario, calificacion) VALUES (?,?,?,?)", [$request->id_juego, $request->codigo_curso, $request->id_usuario, $request->calificacion]);

        return $califica;
        // $juego = new J_juegos();

        // $juego->id_juego = $request->id_juego;
        // $juego->id_usuario = $request->id_usuario;
        // $juego->calificacion = $request->calificacion;

        // $juego->save();

        // return $juego;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
         $juego= DB::SELECT("SELECT * FROM j_juegos WHERE id_juego = $id");

        return $juego;
    }



    // public function filtrar(Request $request){
    //     $filtrar = DB::SELECT("SELECT * FROM usuario WHERE NOMBRES LIKE '%$request->busqueda%'");
    //     return $filtrar;
    // }
    public function juegos_prolipa_admin_tipo($tipo)
    {
        $juego = DB::SELECT("SELECT DISTINCT
                j.*,
                j.estado AS estado_juego,
                a.nombreasignatura,
                a.idasignatura,
                u.id_group,
                g.level,
                CONCAT(u.nombres,' ',u.apellidos) AS nombre_usuario,
                IF(j.estado = '1','Activo','Inactivo') AS statusJuego
            FROM j_juegos j
            INNER JOIN j_temas_por_juego jt ON j.id_juego = jt.id_juego
            INNER JOIN temas t ON jt.id_tema = t.id
            INNER JOIN asignatura a ON t.id_asignatura = a.idasignatura
            INNER JOIN usuario u ON j.id_docente = u.idusuario
            INNER JOIN sys_group_users g ON u.id_group = g.id
            WHERE j.id_tipo_juego = ?
            /*AND j.estado = 1*/

            ORDER BY u.id_group
        ", [$tipo]);

        if (!empty($juego)) {
            foreach ($juego as $key => $value) {

                $temas = DB::SELECT("
                    SELECT *
                    FROM j_temas_por_juego tj
                    INNER JOIN temas t ON tj.id_tema = t.id
                    INNER JOIN asignatura a ON t.id_asignatura = a.idasignatura
                    WHERE tj.id_juego = ?
                ", [$value->id_juego]);

                $data['items'][$key] = [
                    'nombreasignatura' => $value->nombreasignatura,
                    'id_juego' => $value->id_juego,
                    'statusJuego' => $value->statusJuego,
                    'estado_juego' => $value->estado_juego,
                    'id_tipo_juego' => $value->id_tipo_juego,
                    'id_docente' => $value->id_docente,
                    'puntos' => $value->puntos,
                    'duracion' => $value->duracion,
                    'nombre_juego' => $value->nombre_juego,
                    'descripcion_juego' => $value->descripcion_juego,
                    'imagen_juego' => $value->imagen_juego,
                    'fecha_inicio' => $value->fecha_inicio,
                    'fecha_fin' => $value->fecha_fin,
                    'id_group' => $value->id_group,
                    'level' => $value->level,
                    'nombre_usuario' => $value->nombre_usuario,
                    'fecha_creacion_juego' => $value->created_at,
                    'temas' => $temas,
                ];
            }
        } else {
            $data = [];
        }

        return $data;
    }

    public function j_juegos_tipo(Request $request)
    {
        $juego= DB::SELECT("SELECT DISTINCT j . *
        FROM j_juegos j, j_temas_por_juego jt, temas t
        WHERE j.id_tipo_juego = $request->id_tipo_juego
        AND j.id_docente = $request->id_docente
        AND j.id_juego = jt.id_juego
        AND jt.id_tema = t.id
        AND t.id_asignatura = $request->id_asignatura");

        if(!empty($juego)){
            foreach ($juego as $key => $value) {
                $temas = DB::SELECT("SELECT *
                FROM j_temas_por_juego tj, temas t, asignatura a
                WHERE tj.id_tema = t.id
                AND t.id_asignatura = a.idasignatura
                AND tj.id_juego = ?",[$value->id_juego]);
                $data['items'][$key] = [
                    'id_juego' => $value->id_juego,
                    'id_tipo_juego' => $value->id_tipo_juego,
                    'id_docente' => $value->id_docente,
                    'puntos' => $value->puntos,
                    'duracion' => $value->duracion,
                    'nombre_juego' => $value->nombre_juego,
                    'descripcion_juego' => $value->descripcion_juego,
                    'imagen_juego' => $value->imagen_juego,
                    'fecha_inicio' => $value->fecha_inicio,
                    'fecha_fin' => $value->fecha_fin,
                    'estado_juego' => $value->estado,
                    'temas'=>$temas,
                ];
            }
        }else{
            $data = [];
        }
        return $data;

    }


    public function j_juegos_tipo_prolipa(Request $request)
    {
        $juego= DB::SELECT("SELECT DISTINCT j . *
        FROM j_juegos j, j_temas_por_juego jt, temas t, usuario u
        WHERE j.id_tipo_juego = $request->id_tipo_juego
        AND j.estado = 1
        AND j.id_juego = jt.id_juego
        AND jt.id_tema = t.id
        AND t.id_asignatura = $request->id_asignatura
        AND j.id_docente = u.idusuario AND u.id_group != 6
        ");
        if(!empty($juego)){
            foreach ($juego as $key => $value) {
                $temas = DB::SELECT("SELECT *
                FROM j_temas_por_juego tj, temas t, asignatura a
                WHERE tj.id_tema = t.id
                AND t.id_asignatura = a.idasignatura
                AND tj.id_juego = ?",[$value->id_juego]);
                $data['items'][$key] = [
                    'id_juego' => $value->id_juego,
                    'id_tipo_juego' => $value->id_tipo_juego,
                    'id_docente' => $value->id_docente,
                    'puntos' => $value->puntos,
                    'duracion' => $value->duracion,
                    'nombre_juego' => $value->nombre_juego,
                    'descripcion_juego' => $value->descripcion_juego,
                    'imagen_juego' => $value->imagen_juego,
                    'fecha_inicio' => $value->fecha_inicio,
                    'fecha_fin' => $value->fecha_fin,
                    'estado_juego' => $value->estado,
                    'temas'=>$temas,
                ];
            }
        }else{
            $data = [];
        }
        return $data;
    }
    public function j_juegos_tipo_prolipaTodos(Request $request)
    {
        $juego= DB::SELECT("SELECT DISTINCT j . *,tp.nombre_tipo_juego
        FROM j_juegos j, j_temas_por_juego jt, temas t, usuario u,j_tipos_juegos tp
        WHERE j.estado      = 1
        AND j.id_juego      = jt.id_juego
        AND jt.id_tema      = t.id
        AND j.id_tipo_juego = tp.id_tipo_juego
        AND t.id_asignatura = '$request->id_asignatura'
        AND j.id_docente    = u.idusuario
        AND u.id_group      != 6
        AND t.unidad        = '$request->unidad'
        ");
        if(!empty($juego)){
            foreach ($juego as $key => $value) {
                $temas = DB::SELECT("SELECT *
                FROM j_temas_por_juego tj, temas t, asignatura a
                WHERE tj.id_tema = t.id
                AND t.id_asignatura = a.idasignatura
                AND tj.id_juego = ?",[$value->id_juego]);
                $data['items'][$key] = [
                    'id_juego' => $value->id_juego,
                    'id_tipo_juego' => $value->id_tipo_juego,
                    'id_docente' => $value->id_docente,
                    'puntos' => $value->puntos,
                    'duracion' => $value->duracion,
                    'nombre_juego' => $value->nombre_juego,
                    'descripcion_juego' => $value->descripcion_juego,
                    'imagen_juego' => $value->imagen_juego,
                    'fecha_inicio' => $value->fecha_inicio,
                    'fecha_fin' => $value->fecha_fin,
                    'estado_juego' => $value->estado,
                    'nombre_tipo_juego'=> $value->nombre_tipo_juego,
                    'temas'=>$temas,
                ];
            }
        }else{
            $data = [];
        }
        return $data;
    }

    public function j_juegos_tipo_curso_doc(Request $request)
    {

        $estudiante= DB::SELECT("SELECT DISTINCT e.usuario_idusuario, u.cedula, u.nombres, u.apellidos FROM estudiante e, usuario u WHERE e.codigo = '$request->codigo' AND e.usuario_idusuario = u.idusuario ORDER BY u.apellidos");

        if(!empty($estudiante)){
            foreach ($estudiante as $key => $value) {
                $calificaciones = DB::SELECT("SELECT jc.calificacion FROM j_calificaciones jc WHERE jc.id_usuario = ? AND jc.id_juego = ?",[$value->usuario_idusuario, $request->id_juego]);
                $data['items'][$key] = [
                    'nombres' => $value->nombres,
                    'apellidos' => $value->apellidos,
                    'cedula' => $value->cedula,
                    'usuario_idusuario' => $value->usuario_idusuario,
                    'calificaciones'=>$calificaciones,
                ];
            }
        }else{
            $data = [];
        }
        return $data;
    }



    public function j_juegos_ficha(Request $request)
    {
        $juego = J_juegos::find($request->id_juego);

        $juego->bloque_curricular = $request->bloque_curricular;
        $juego->grado = $request->grado;
        $juego->destrezas = $request->destrezas;
        $juego->habilidades = $request->habilidades;
        $juego->elaborado_por = $request->elaborado_por;
        $juego->intencion_didactica = $request->intencion_didactica;
        $juego->consigna = $request->consigna;
        $juego->consideraciones = $request->consideraciones;

        $juego->save();

        return $juego;
    }


    public function calificaciones_estudiante_juego(Request $request)
    {
        $calificaciones = DB::SELECT("SELECT * FROM j_calificaciones jc WHERE jc.id_usuario = $request->id_usuario AND jc.id_juego = $request->id_juego");

        return $calificaciones;
    }


    public function juegos_has_curso($codigo_curso)
    {
        $juegos = DB::SELECT("SELECT j .*, jt.nombre_tipo_juego, jt.descripcion_tipo_juego, jt.imagen_juego FROM j_juegos j, cursos_has_juego cj, j_tipos_juegos jt WHERE j.id_juego = cj.id_juego AND j.id_tipo_juego = jt.id_tipo_juego AND cj.codigo_curso = '$codigo_curso' AND j.estado = 1 ORDER BY j.id_juego DESC");
        return $juegos;
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



    public function j_juegos_eliminar($id)
    {
        $juego = J_juegos::find($id);
        $juego->estado = 0;
        $juego->save();
        return $juego;
    }


    public function destroy($id)
    {
        $juego = J_juegos::find($id);
        $juego->delete();
    }


    public function juego_preguntas_opciones($id_juego)
    {
        $preguntas= DB::SELECT("SELECT c.id_contenido_juego, c.imagen, c.pregunta, c.respuesta, c.descripcion, c.puntaje FROM j_juegos j, j_contenido_juegos c WHERE j.id_juego = $id_juego AND j.id_juego = c.id_juego");

        if(!empty($preguntas)){
            foreach ($preguntas as $key => $value) {
                $opciones = DB::SELECT("SELECT * FROM j_opciones_contenidos WHERE id_contenido_juegos = ?",[$value->id_contenido_juego]);
                $data['items'][$key] = [
                    'pregunta' => $value,
                    'opciones'=>$opciones,
                ];
            }
        }else{
            $data = [];
        }
        return $data;
    }




    public function save_juegos_administrables(Request $request)
    {
        $ruta = public_path('departamentos/');
        if( $request->id != '' ){

            if($request->file('img_portada') && $request->file('img_portada') != null && $request->file('img_portada')!= 'null'){
                $file = $request->file('img_portada');
                $fileName = uniqid().$file->getClientOriginalName();
                $file->move($ruta,$fileName);
                if( file_exists('departamentos/'.$request->img_old) && $request->img_old != '' ){
                    unlink('departamentos/'.$request->img_old);
                }
            }else{
                $fileName = $request->img_old;
            }

            DB::UPDATE("UPDATE `juegos_administrables` SET `img_portada`='?', `titulo`='?', `subtitulo`='?', `descripcion`='?', `tipo_juego`='?' WHERE `id` = ?", [$fileName, $request->titulo, $request->subtitulo, $request->descripcion, $request->tipo_juego, $request->id]);

            return "modificado";
        }else{

            if($request->file('img_portada')){
                $file = $request->file('img_portada');
                $ruta = public_path('departamentos');
                $fileName = uniqid().$file->getClientOriginalName();
                $file->move($ruta, $fileName);
            }else{
                $fileName = '';
            }

            DB::INSERT("INSERT INTO `juegos_administrables`(`img_portada`, `titulo`, `subtitulo`, `descripcion`, `tipo_juego`, `id_usuario`) VALUES (?,?,?,?,?,?)", [$fileName, $request->titulo, $request->subtitulo, $request->descripcion, $request->tipo_juego, $request->id_usuario]);
            return "creado";
        }
    }

    //INICIO METODOS JEYSON
    public function Procesar_MetodosGet_JuegosController(Request $request)
    {
        $action = $request->query('action'); // Leer el parámetro `action` desde la URL
        switch ($action) {
            case 'Consulta_Asignatura_Juegos_Transferencia':
                return $this->Consulta_Asignatura_Juegos_Transferencia($request);
            case 'Consulta_Unidades_Juegos_Transferencia':
                return $this->Consulta_Unidades_Juegos_Transferencia($request);
            case 'Consulta_Temas_Juegos_Transferencia':
                return $this->Consulta_Temas_Juegos_Transferencia($request);
            default:
                return response()->json(['error' => 'Acción no válida'], 400);
        }
    }
    public function Procesar_MetodosPost_JuegosController(Request $request)
    {
        $action = $request->input('action'); // Recibir el parámetro 'action'

        switch ($action) {
            case 'Transferencia_Juegos_Asignatura':
                return $this->Transferencia_Juegos_Asignatura($request);
            case 'Actualizar_Imagen_Contenido_Juego':
                return $this->Actualizar_Imagen_Contenido_Juego($request);
            case 'Actualizar_Imagen_Opcion_Juego':
                return $this->Actualizar_Imagen_Opcion_Juego($request);
            default:
                return response()->json(['error' => 'Acción no válida'], 400);
        }
    }

    public function Consulta_Asignatura_Juegos_Transferencia($request)
    {
        $asignatura_excluir = $request->id_asignatura;
        $juegos = DB::SELECT("SELECT * FROM asignatura asi
            WHERE asi.idasignatura <> $asignatura_excluir
            AND asi.estado = '1'
            AND asi.nombreasignatura NOT LIKE '%combo%'
            AND asi.tipo_asignatura = 1");
        return $juegos;
    }
    public function Consulta_Unidades_Juegos_Transferencia($request)
    {
        $asignatura_unidad = $request->id_asignatura;
        $juegos = DB::SELECT("SELECT li.idlibro, asi.idasignatura, asi.nombreasignatura, ul.*
            FROM libro li
            LEFT JOIN unidades_libros ul ON li.idlibro = ul.id_libro
            LEFT JOIN asignatura asi ON li.asignatura_idasignatura = asi.idasignatura
            WHERE li.asignatura_idasignatura = $asignatura_unidad");
        return $juegos;
    }
    public function Consulta_Temas_Juegos_Transferencia($request)
    {
        $id_unidad_para_tema = $request->id_unidad;
        $juegos = DB::SELECT("SELECT * FROM temas tem
            WHERE tem.id_unidad = $id_unidad_para_tema
            ORDER BY
            CAST(SUBSTRING_INDEX(tem.nombre_tema, '.', 1) AS UNSIGNED)
        ");
        return $juegos;
    }
    public function Transferencia_Juegos_Asignatura($request)
    {
        DB::beginTransaction();
        try {
            $id_juego_origen    = $request->input('id_juego');
            $id_tipo_juego      = $request->input('id_tipo_juego');
            $user_created       = $request->input('user_created');
            $temas              = $request->input('temas_recibir_transferencia', []);

            // =====================================================================
            // PASO 0: Validar si el juego ya existe en los temas destino (evitar duplicados)
            // =====================================================================
            $juegoOrigen = DB::selectOne(
                "SELECT * FROM j_juegos WHERE id_juego = ?",
                [$id_juego_origen]
            );

            if (!$juegoOrigen) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => "No se encontró el juego original con id_juego = {$id_juego_origen}"
                ], 404);
            }

            foreach ($temas as $tema) {
                $existeJuego = DB::selectOne("
                    SELECT j.id_juego
                    FROM j_juegos j
                    INNER JOIN j_temas_por_juego tj ON j.id_juego = tj.id_juego
                    WHERE j.nombre_juego = ?
                      AND tj.id_tema = ?
                      AND j.id_tipo_juego = ?
                      AND j.estado = 1
                    LIMIT 1
                ", [$juegoOrigen->nombre_juego, $tema['id'], $id_tipo_juego]);

                if ($existeJuego) {
                    $nombreTema = isset($tema['nombre_tema']) ? $tema['nombre_tema'] : 'seleccionado';
                    DB::rollBack();
                    return response()->json([
                        'status'  => 'error',
                        'message' => "El juego '{$juegoOrigen->nombre_juego}' (del mismo tipo) ya existe en el tema '{$nombreTema}'. No se puede duplicar."
                        // 'message' => "El juego '{$juegoOrigen->nombre_juego}' (del mismo tipo) ya existe en uno de los temas seleccionados de destino. No se puede duplicar."
                    ], 409);
                }
            }

            // =====================================================================
            // PASO 1: Copiar registro en j_juegos
            // =====================================================================



            $nuevoIdJuego = DB::table('j_juegos')->insertGetId([
                'id_tipo_juego'       => $id_tipo_juego,
                'id_docente'          => $user_created,
                'puntos'              => $juegoOrigen->puntos,
                'duracion'            => $juegoOrigen->duracion,
                'nombre_juego'        => $juegoOrigen->nombre_juego,
                'descripcion_juego'   => $juegoOrigen->descripcion_juego,
                'imagen_juego'        => $juegoOrigen->imagen_juego,
                'fecha_inicio'        => $juegoOrigen->fecha_inicio,
                'fecha_fin'           => $juegoOrigen->fecha_fin,
                'bloque_curricular'   => $juegoOrigen->bloque_curricular,
                'grado'               => $juegoOrigen->grado,
                'destrezas'           => $juegoOrigen->destrezas,
                'habilidades'         => $juegoOrigen->habilidades,
                'elaborado_por'       => $juegoOrigen->elaborado_por,
                'intencion_didactica' => $juegoOrigen->intencion_didactica,
                'consigna'            => $juegoOrigen->consigna,
                'consideraciones'     => $juegoOrigen->consideraciones,
                'estado'              => $juegoOrigen->estado,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // =====================================================================
            // PASO 2: Copiar registros en j_contenido_juegos
            // La imagen se inserta igual a la original.
            // El frontend usará el files-server para copiar físicamente la imagen
            // y luego enviará el nuevo nombre para actualizar el registro.
            // =====================================================================
            $contenidosOrigen = DB::select(
                "SELECT * FROM j_contenido_juegos WHERE id_juego = ?",
                [$id_juego_origen]
            );

            // Mapa: id_contenido_juego_original => id_contenido_juego_nuevo
            $mapaContenidos = [];
            // Tipos 1 y 2 no manejan imágenes; tipos 3, 4 → seleccionSimple; tipo 6 → rompecabezas
            $tiposConImagenes = [3, 4, 6];
            $imagenesContenido = [];

            foreach ($contenidosOrigen as $contenido) {
                $nuevoIdContenido = DB::table('j_contenido_juegos')->insertGetId([
                    'id_juego'    => $nuevoIdJuego,
                    'imagen'      => $contenido->imagen,
                    'pregunta'    => $contenido->pregunta,
                    'respuesta'   => $contenido->respuesta,
                    'descripcion' => $contenido->descripcion,
                    'puntaje'     => $contenido->puntaje,
                    'estado'      => $contenido->estado,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $mapaContenidos[$contenido->id_contenido_juego] = $nuevoIdContenido;

                // Solo registrar imagen si el tipo de juego la requiere
                if (!empty($contenido->imagen) && in_array($id_tipo_juego, $tiposConImagenes)) {
                    $imagenesContenido[] = [
                        'imagen_origen'      => $contenido->imagen,
                        'id_contenido_nuevo' => $nuevoIdContenido,
                    ];
                }
            }

            // =====================================================================
            // PASO 3: Insertar en j_temas_por_juego (uno por cada tema del request)
            // =====================================================================
            foreach ($temas as $tema) {
                DB::table('j_temas_por_juego')->insert([
                    'id_juego'   => $nuevoIdJuego,
                    'id_tema'    => $tema['id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // =====================================================================
            // PASO 4: Para tipos 3 y 4 → copiar j_opciones_contenidos
            // Igual que contenido: la imagen_opcion se deja igual a la original
            // y el frontend la copia a través del files-server.
            // =====================================================================
            $imagenesOpciones = [];

            if (in_array($id_tipo_juego, [3, 4])) {
                foreach ($mapaContenidos as $idContenidoOrigen => $idContenidoNuevo) {
                    $opciones = DB::select(
                        "SELECT * FROM j_opciones_contenidos WHERE id_contenido_juegos = ?",
                        [$idContenidoOrigen]
                    );

                    foreach ($opciones as $opcion) {
                        $nuevoIdOpcion = DB::table('j_opciones_contenidos')->insertGetId([
                            'id_contenido_juegos' => $idContenidoNuevo,
                            'nombre_opcion'       => $opcion->nombre_opcion,
                            'descripcion_opcion'  => $opcion->descripcion_opcion,
                            'tipo_opcion'         => $opcion->tipo_opcion,
                            'imagen_opcion'       => $opcion->imagen_opcion,
                            'estado'              => $opcion->estado,
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);

                        if (!empty($opcion->imagen_opcion)) {
                            $imagenesOpciones[] = [
                                'imagen_opcion_origen' => $opcion->imagen_opcion,
                                'id_opcion_nuevo'      => $nuevoIdOpcion,
                            ];
                        }
                    }
                }
            }
            // Para tipos 1, 2 → NO se copia j_opciones_contenidos ni imágenes
            // Para tipo 6 → NO se copia j_opciones_contenidos (pero sí imágenes de contenido)

            DB::commit();
            return response()->json([
                'status'             => 1,
                'message'            => 'Juego copiado correctamente en base de datos',
                'id_juego_nuevo'     => $nuevoIdJuego,
                'id_tipo_juego'      => $id_tipo_juego,
                // El front usa estos datos para copiar imágenes en el files-server:
                // tipos 3,4 → seleccionSimple | tipo 6 → rompecabezas
                'imagenes_contenido' => $imagenesContenido,
                'imagenes_opciones'  => $imagenesOpciones,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    public function Actualizar_Imagen_Contenido_Juego($request)
    {
        $id_contenido = $request->input('id_contenido_juego');
        $nuevo_nombre = $request->input('nuevo_nombre');
        DB::table('j_contenido_juegos')
            ->where('id_contenido_juego', $id_contenido)
            ->update(['imagen' => $nuevo_nombre, 'updated_at' => now()]);
        return response()->json(['status' => 1]);
    }

    public function Actualizar_Imagen_Opcion_Juego($request)
    {
        $id_opcion    = $request->input('id_opcion_contenido');
        $nuevo_nombre = $request->input('nuevo_nombre');
        DB::table('j_opciones_contenidos')
            ->where('id_opcion_contenido', $id_opcion)
            ->update(['imagen_opcion' => $nuevo_nombre, 'updated_at' => now()]);
        return response()->json(['status' => 1]);
    }
    //FIN METODOS JEYSON


}
