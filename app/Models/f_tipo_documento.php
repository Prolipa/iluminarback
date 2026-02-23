<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class f_tipo_documento extends Model
{
    use HasFactory;
    protected $table  ="f_tipo_documento";

    protected $primaryKey = 'tdo_id';
    // public $timestamps = false;

    protected $fillable = [
        'tdo_id',
        'tdo_nombre',
        'tdo_secuencial_calmed',
        'tdo_secuencial_Prolipa',
        'tdo_secuencial_calmed2026',
        'tdo_secuencial_Prolipa2026',
        'tdo_letra',
        'tdo_estado',
        'created_at',
        'updated_at',
        'user_created',
    ];
    public function scopeObtenerSecuenciaXId($query, $id, $empresa)
    {
        $campo = '';
        if ($empresa == 1) {
            $campo = 'tdo_secuencial_Prolipa';
        } elseif ($empresa == 3) {
            $campo = 'tdo_secuencial_calmed';
        } elseif ($empresa == 4) {
            $campo = 'tdo_secuencial_calmed2026';
        } elseif ($empresa == 5) {
            $campo = 'tdo_secuencial_Prolipa2026';
        } else {
            $campo = 'tdo_secuencial_SinEmpresa';
        }
        return $query->where('tdo_id', $id)->select('tdo_id', $campo . ' as cod');
    }

    public function scopeObtenerSecuencia($query, $tdo_nombre)
    {
        return $query->where('tdo_nombre', $tdo_nombre)
                     ->where('tdo_estado', '1')->first();
    }
    public static function getLetra(int $id): self
    {
        try {
            $tdo = self::findOrFail($id); // Busca el registro o lanza una excepción
            return $tdo;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Si no se encuentra el registro, lanza una nueva excepción personalizada
            throw new \Exception("El registro con ID {$id} no fue encontrado.");
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, relanzamos la excepción
            throw new \Exception("Ocurrió un error al buscar el registro: " . $e->getMessage());
        }
    }
    public function scopeUpdateSecuencia($query, $tdo_nombre, $empresa, $secuencial)
    {
        $setEmpresa = '';
        // Determinar la columna a actualizar basada en la empresa
        if ($empresa == 1) {
            $setEmpresa = 'tdo_secuencial_Prolipa';
        } else if ($empresa == 3) {
            $setEmpresa = 'tdo_secuencial_calmed';
        } else if ($empresa == 4) {
            $setEmpresa = 'tdo_secuencial_calmed2026';
        } else if ($empresa == 5) {
            $setEmpresa = 'tdo_secuencial_Prolipa2026';
        }
        // Si no se encontró una columna válida, retorna false
        if ($setEmpresa === '') {
            return false;
        }
        // Aplicar la actualización a la consulta
        return $query->where('tdo_nombre', $tdo_nombre)
                     ->where('tdo_estado', '1') // Actualiza solo si el estado es 1
                     ->update([$setEmpresa => $secuencial]);
    }
    public static function formatSecuencia($secuencia)
    {
        // Formato con 7 dígitos y ceros a la izquierda (ejemplo: 0001821)
        return str_pad((int)$secuencia, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementa y actualiza la secuencia de un tipo de documento
     * @param int $tdo_id ID del tipo de documento
     * @param int $id_empresa 1 para Prolipa, 3 para Calmed
     * @return int Nueva secuencia actualizada
     * @throws \Exception Si no se encuentra el documento o no se puede actualizar
     */
    public static function incrementarSecuencia($tdo_id, $id_empresa)
    {
        $tipo_doc = self::findOrFail($tdo_id);

        // Determinar el campo de secuencia según la empresa
        $campoSecuencia = '';
        if ($id_empresa == 1) {
            $campoSecuencia = 'tdo_secuencial_Prolipa';
        } else if ($id_empresa == 3) {
            $campoSecuencia = 'tdo_secuencial_calmed';
        } else if ($id_empresa == 4) {
            $campoSecuencia = 'tdo_secuencial_calmed2026';
        } else if ($id_empresa == 5) {
            $campoSecuencia = 'tdo_secuencial_Prolipa2026';
        } else {
            throw new \Exception("Empresa no válida. Debe ser 1 (Prolipa), 3 (Calmed), 4 (Calmed2026) o 5 (Prolipa2026)");
        }

        // Obtener secuencia actual e incrementar
        $secuenciaActual = (int) $tipo_doc->$campoSecuencia;
        $nuevaSecuencia = $secuenciaActual + 1;

        // Actualizar la secuencia
        $tipo_doc->$campoSecuencia = $nuevaSecuencia;
        $tipo_doc->save();

        return $nuevaSecuencia;
    }
}
