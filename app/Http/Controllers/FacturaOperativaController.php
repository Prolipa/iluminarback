<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ventas;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class FacturaOperativaController extends Controller
{
    public function guardar(Request $request) {
        $data = $request->all();
        
        DB::beginTransaction();
        try {
            // Verificar si ya existe el código
            $exists = DB::table('f_venta')->where('ven_codigo', $data['documento_codigo'])->exists();
            if ($exists) {
                return response()->json(['status' => 0, 'message' => 'El código de documento ya existe.']);
            }
            // Buscar ID de cliente por Cédula (si es necesario para ven_cliente)
            // El frontend envía data['clienteId'] como cedula y data['cliente'] como objeto.
            // Si ven_cliente requiere el ID de la tabla usuario:
            $clienteUsuario = DB::table('usuario')->where('cedula', $data['clienteId'])->first();
            $ven_cliente = $clienteUsuario ? $clienteUsuario->idusuario : null;
            if (!$ven_cliente) {
                 // Fallback si no encuentra por cedula, usar el user_created o manejar error
                 // return response()->json(['status' => 0, 'message' => 'Cliente no encontrado']);
                 $ven_cliente = $data['cliente']['idusuario'] ?? 0;
            }
            $venta = new Ventas();
            $venta->ven_codigo = $data['documento_codigo'];
            $venta->tip_ven_codigo = 1; // Fijo
            $venta->est_ven_codigo = 1; // Fijo
            $venta->ven_valor = $data['valor'];
            $venta->ven_subtotal = $data['valor'];
            $venta->ven_desc_por = 0;
            $venta->ven_descuento = 0;
            $venta->ven_fecha = now();
            // $venta->institucion_id = $data['instituciones_ids'][0] ?? null; // Tomar el primero
            // Si instituciones_ids es array, tomar el primero valido
            $venta->institucion_id = (is_array($data['instituciones_ids']) && count($data['instituciones_ids']) > 0) ? $data['instituciones_ids'][0] : null;
            
            $venta->periodo_id = $data['periodo_id'];
            $venta->user_created = $data['user_created']; // ID Usuario logueado
            $venta->id_empresa = $data['id_empresa'];
            $venta->ven_observacion = $data['observacion']; // Incluye 'Destino: Notas/Prefacturas' si se concatena o usar campo destino si existe
            
            // Concatenar destino a observacion si no hay campo destino en tabla
            $destinoTexto = ($data['destino'] == 1) ? 'Destino: Notas' : 'Destino: PreFacturas';
            $venta->ven_observacion = $venta->ven_observacion;
            // . " - " . $destinoTexto;
            $venta->idtipodoc = 20; // Factura Operativa
            $venta->ven_cliente = $ven_cliente;
            $venta->ruc_cliente = $data['clienteId']; // Cedula/RUC en campo ruc_cliente? O string
            // Si la columna destino existe:
            // $venta->destino = $data['destino']; 
            
            $venta->save();
            
            // Actualizar secuencial en f_tipo_documento (tdo_id = 20 para Factura Operativa)
            $tipoDoc = DB::table('f_tipo_documento')->where('tdo_id', 20)->first();
            if ($tipoDoc) {
                $campoSecuencial = null;
                if ($data['id_empresa'] == 1) { // Prolipa
                    $campoSecuencial = 'tdo_secuencial_Prolipa';
                } elseif ($data['id_empresa'] == 3) { // Calmed
                    $campoSecuencial = 'tdo_secuencial_calmed';
                } else {
                    // Opcional: Manejar otras empresas o usar SinEmpresa
                    $campoSecuencial = 'tdo_secuencial_SinEmpresa';
                }

                if ($campoSecuencial) {
                    DB::table('f_tipo_documento')
                        ->where('tdo_id', 20)
                        ->increment($campoSecuencial);
                }
            }
            
            DB::commit();
            return response()->json(['status' => 1, 'message' => 'Guardado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    public function generarPDF($codigo) {
        $venta = DB::table('f_venta')
                    ->leftJoin('usuario', 'f_venta.ven_cliente', '=', 'usuario.idusuario')
                    ->leftJoin('institucion', 'f_venta.institucion_id', '=', 'institucion.idInstitucion')
                    ->where('ven_codigo', $codigo)
                    ->select('f_venta.*', 'usuario.nombres', 'usuario.apellidos', 'usuario.cedula', 'usuario.telefono', 'usuario.direccion', 'institucion.nombreInstitucion')
                    ->first();
        if (!$venta) {
            return response()->json(['status' => 0, 'message' => 'Documento no encontrado']);
        }
        
        // Retornar solo la información como JSON
        return response()->json($venta);
    }

    public function cambiarEstado(Request $request) {
        $ven_codigo = $request->ven_codigo; // Código del documento
        
        if (!$ven_codigo) {
             return response()->json(['status' => 0, 'message' => 'Código de documento no proporcionado']);
        }

        DB::beginTransaction();
        try {
            // Actualizar estado a 3 (Anulado/Cancelado según contexto)
            $affected = DB::table('f_venta')
                ->where('ven_codigo', $ven_codigo)
                ->update(['est_ven_codigo' => 3]);

            if ($affected) {
                DB::commit();
                return response()->json(['status' => 1, 'message' => 'Estado actualizado correctamente']);
            } else {
                DB::rollBack();
                return response()->json(['status' => 0, 'message' => 'No se encontró el documento o no se pudo actualizar']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => 'Error al cambiar estado: ' . $e->getMessage()]);
        }
    }
}
