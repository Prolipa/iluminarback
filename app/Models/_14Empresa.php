<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class _14Empresa extends Model
{
    use HasFactory;
    protected $table = "empresas";

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'descripcion_corta',
        'direccion',
        'representante',
        'ruc',
        'email',
        'telefono',
        'estado',
        'tipo',
        'archivo',
        'url',
        'secuencial',
        'img_base64',
        'imagen_utilizada',
        'facturas',
        'notas',
        'token_local',
        'token_prod',
        'perseo_enviroment',
        'created_at',
        'updated_at',
        'letra_empresa',
    ];

    /**
     * Obtener la secuencia de guía según el empresa_id
     * @param int $empresa_id
     * @param object $getSecuencia - Objeto con las secuencias (f_tipo_documento)
     * @return string|null
     */
    public static function obtenerSecuenciaGuia($empresa_id, $getSecuencia)
    {
        $empresa_id = (int) $empresa_id;

        $mapSecuencias = [
            1 => 'tdo_secuencial_Prolipa',
            3 => 'tdo_secuencial_calmed',
            4 => 'tdo_secuencial_calmed2026',
            5 => 'tdo_secuencial_Prolipa2026',
        ];

        if (!isset($mapSecuencias[$empresa_id])) {
            return null;
        }

        $campo = $mapSecuencias[$empresa_id];
        return $getSecuencia->{$campo} ?? null;
    }

    /**
     * Obtener la letra de la empresa según el empresa_id
     * @param int $empresa_id
     * @return string|null
     */
    public static function obtenerLetraEmpresa($empresa_id)
    {
        $empresa = self::find($empresa_id);
        return $empresa ? $empresa->letra_empresa : null;
    }

}
