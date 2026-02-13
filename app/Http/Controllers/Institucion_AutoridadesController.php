<?php

namespace App\Http\Controllers;

use App\Models\Institucion_Autoridades;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class Institucion_AutoridadesController extends Controller
{
    public function VerifcarMetodosGet_Institucion_Autoridades(Request $request)
    {
        $action = $request->query('action'); // Leer el parámetro `action` desde la URL

        switch ($action) {
            case 'GetTablaComentarioInstitucion_Autoridades':
                return $this->GetTablaComentarioInstitucion_Autoridades();
            case 'Get_Institucion_AutoridadesContador':
                return $this->Get_Institucion_AutoridadesContador($request);
            case 'Get_Institucion_Autoridades':
                return $this->Get_Institucion_Autoridades();
            case 'Get_Institucion_AutoridadesActivo':
                return $this->Get_Institucion_AutoridadesActivo();
            case 'Get_Institucion_Autoridades_xfiltro':
                return $this->Get_Institucion_Autoridades_xfiltro($request);
            default:
                return response()->json(['error' => 'Acción no válida'], 400);
        }
    }
    public function VerifcarMetodosPost_Institucion_Autoridades(Request $request)
    {
        $action = $request->input('action'); // Recibir el parámetro 'action'

        switch ($action) {
            case 'Post_Registrar_modificar_Institucion_Autoridades':
                return $this->Post_Registrar_modificar_Institucion_Autoridades($request);
            case 'ActualizarComentarioInstitucion_Autoridades':
                return $this->ActualizarComentarioInstitucion_Autoridades($request);
            case 'Desactivar_Institucion_Autoridades':
                return $this->Desactivar_Institucion_Autoridades($request);
            case 'Post_Eliminar_Institucion_Autoridades':
                return $this->Post_Eliminar_Institucion_Autoridades($request);
            default:
                return response()->json(['error' => 'Acción no válida'], 400);
        }
    }
    public function GetTablaComentarioInstitucion_Autoridades()
    {
        $commentQuery = DB::select("
            SELECT table_comment
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = ?
        ", ['institucion_autoridades']);

        $comment = !empty($commentQuery) ? $commentQuery[0]->table_comment : null;

        return $comment;
    }

    public function Get_Institucion_AutoridadesContador(Request $request){
        $valormasuno = $request->conteo+1;
        $query = DB::SELECT("SELECT ina_id FROM institucion_autoridades LIMIT $valormasuno");
        $conteogeneral = count($query);
        if($conteogeneral<$valormasuno){
            return response()->json(['mensaje' => 'data_menor', 'conteo' => $conteogeneral]);
        }else if($conteogeneral==$valormasuno){
            return response()->json(['mensaje' => 'data_igual', 'conteo' => $conteogeneral]);
        }
    }

    public function Get_Institucion_Autoridades(){
        $query = DB::SELECT("SELECT ina.*, CONCAT(user_edit.nombres,' ',user_edit.apellidos) as name_user_edit,
        CONCAT(user_created.nombres,' ',user_created.apellidos) as name_user_created
        FROM institucion_autoridades ina
        LEFT JOIN usuario user_edit ON ina.user_edit = user_edit.idusuario
        LEFT JOIN usuario user_created ON ina.user_created = user_created.idusuario
        ORDER BY ina.ina_id ASC");
        return $query;
    }

    public function Get_Institucion_AutoridadesActivo(){
        $query = DB::SELECT("SELECT ina.*
        FROM institucion_autoridades ina
        WHERE ina.ina_estado = '1'
        ORDER BY ina.created_at DESC");
        return $query;
    }

    public function Get_Institucion_Autoridades_xfiltro(Request $request){
        if ($request->busqueda == 'undefined' || $request->busqueda == 'codigo' || $request->busqueda == '' || $request->busqueda == null) {
            $query = DB::SELECT("SELECT ina.*, ina.ina_id as codigoanterior FROM institucion_autoridades ina
            WHERE ina.ina_id LIKE '%$request->razonbusqueda%'
            ORDER BY ina.created_at DESC
            ");
            return $query;
        }
        if ($request->busqueda == 'nombre') {
            $query = DB::SELECT("SELECT ina.*, ina.ina_id as codigoanterior FROM institucion_autoridades ina
            WHERE ina.ina_nombre LIKE '%$request->razonbusqueda%'
            ORDER BY ina.created_at DESC
            ");
            return $query;
        }
    }

    public function Post_Registrar_modificar_Institucion_Autoridades(Request $request)
    {
        try {
            $this->ActualizarAutoIncrementableje();
            $esNuevo = empty($request->ina_id); // Verificamos si se está creando
            // Si se está editando, buscar el registro por ID. Si no, crear nuevo.
            $const_institucion_autoridades = $esNuevo ? new Institucion_Autoridades() : Institucion_Autoridades::findOrFail($request->ina_id);
            $const_institucion_autoridades->ina_nombre = $request->ina_nombre;
            $const_institucion_autoridades->user_edit = $request->user_edit;
            $const_institucion_autoridades->updated_at = now();
            // Si es nuevo, asignar user_created
            if ($esNuevo) {
                $const_institucion_autoridades->user_created = $request->user_created;
                $const_institucion_autoridades->user_edit = $request->user_edit;
                $const_institucion_autoridades->updated_at = now();
            }
            $const_institucion_autoridades->save();

            return response()->json([
                'status' => 1,
                'message' => $esNuevo ? 'Se ha registrado correctamente' : 'Se ha editado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Ocurrió un error al guardar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function ActualizarComentarioInstitucion_Autoridades(Request $request)
    {
        try {
            // Obtener el comentario de la solicitud
            $comentario = $request->Actualizar_comentario;

            // Escapar el comentario para evitar problemas de inyección SQL
            $comentarioEscapado = DB::getPdo()->quote($comentario);

            // Actualizar el comentario de la tabla
            DB::statement("ALTER TABLE institucion_autoridades COMMENT = {$comentarioEscapado}");

            return response()->json(["status" => "1", 'message' => 'Comentario actualizado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(["status" => "0", 'message' => 'Error al actualizar el comentario: ' . $e->getMessage()], 500);
        }
    }

    public function Desactivar_Institucion_Autoridades(Request $request)
    {
        try {
            // Validar que venga el ID
            if (!$request->ina_id) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No se ha proporcionado un ina_id válido.'
                ], 400);
            }
            // Buscar el ina_id
            $const_institucion_autoridades = Institucion_Autoridades::find($request->ina_id);

            if (!$const_institucion_autoridades) {
                return response()->json([
                    'status' => 0,
                    'message' => 'El ina_id no existe en la base de datos.'
                ], 404);
            }
            // Actualizar el estado
            $const_institucion_autoridades->ina_estado = $request->ina_estado;
            $const_institucion_autoridades->save();
            // Generar mensaje según el estado
            $mensaje = $request->ina_estado == 1 ? 'El cargo de autoridades se activó correctamente.' : 'El cargo de autoridades se inactivo correctamente.';
            return response()->json([
                'status' => 1,
                'message' => $mensaje,
                'data' => $const_institucion_autoridades
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error al actualizar el estado del cargo de autoridades: ' . $e->getMessage()
            ], 500);
        }
    }

    public function Post_Eliminar_Institucion_Autoridades(Request $request)
    {
        DB::beginTransaction();
        try {
            $const_institucion_autoridades = Institucion_Autoridades::findOrFail($request->ina_id);
            $const_institucion_autoridades->delete();
            $this->ActualizarAutoIncrementableje();
            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Cargo de autoridades eliminado correctamente.',
                'data' => $const_institucion_autoridades
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 0,
                'message' => 'Error al eliminar el cargo de autoridades: ' . $e->getMessage()
            ]);
        }
    }

    public function ActualizarAutoIncrementableje()
    {
        // Reajustar el autoincremento - estas 2 lineas permite reajustar el autoincrementable por defecto
        $ultimoId  = Institucion_Autoridades::max('ina_id') + 1;
        DB::statement('ALTER TABLE institucion_autoridades AUTO_INCREMENT = ' . $ultimoId);
    }
}
