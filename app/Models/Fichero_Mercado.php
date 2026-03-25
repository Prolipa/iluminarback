<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fichero_Mercado extends Model
{
    use HasFactory;
    protected $table = "fichero_mercado";
    protected $primaryKey = 'fm_id';
    protected $fillable = [
        'idInstitucion',
        'asesor_id',
        'idperiodoescolar',
        'id_logo_empresa',
        'fm_trabaja_con_prolipa',
        'fm_convenio',
        'fm_cantidad_anios_trabaja_con_prolipa',
        'fm_tipo_venta',
        'fm_establecimientos_para_despacho',
        'fm_decide_compra',
        'fm_factores_inciden_en_compra',
        'fm_niveles_educativos',
        'fm_pensiones',
        'fm_numero_aulas_completo',
        'fm_cantidad_estudiantes_x_aula_completo',
        'fm_SumaTotal_EstudiantesxAula',
        'fm_cantidad_real_estudiantes',
        'fm_observacion',
        'fm_fecha_aprobacion_definicion',
        'fm_asesor_comercial',
        'fm_estado',
        'user_created',
        'user_edit',
        'info_enviar_para_aprobacion',
        'info_aprobacion',
        'info_rechazo',
        'created_at',
        'updated_at'
    ];
}
