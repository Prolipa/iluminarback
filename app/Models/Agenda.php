<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Agenda extends Model
{
    protected $table = "agenda_usuario";
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_usuario',
        'usuario_creador',
        'usuario_editor',
        'title',
        'label',
        'classes',
        'startDate',
        'endDate',
        'fecha_que_visita',
        'hora_inicio',
        'hora_fin',
        'url',
        'institucion_id',
        'institucion_id_temporal',
        'nombre_institucion_temporal',
        'estado_institucion_temporal',
        'opciones',
        'periodo_id',
        'estado',
        'dias_desface',
        'latitud',
        'longitud',
        'fecha_captura_longitud',
        'latitud_fin',
        'longitud_fin',
        'fecha_captura_longitud_fin',
        'otraEditorial'
    ];

    // Deshabilitar timestamps automáticos si no los usas
    public $timestamps = false;

    // Casting de fechas como strings para evitar problemas de timezone
    // Las fechas se manejarán como strings simples en formato Y-m-d
    protected $casts = [
        'startDate' => 'string',
        'endDate' => 'string',
        'fecha_que_visita' => 'string',
        'fecha_captura_longitud' => 'string',
        'fecha_captura_longitud_fin' => 'string'
    ];
}
