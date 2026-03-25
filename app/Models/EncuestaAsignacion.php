<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuestaAsignacion extends Model
{
    protected $table    = 'encuesta_prolipa_asignaciones';
    protected $fillable = [
        'encuesta_id',
        'entidad_tipo',
        'entidad_id',
        'grupos_acceso',
        'estado',
        'codigo_acceso',
        'created_by',
    ];

    /** Relación polimórfica — resuelve el modelo según entidad_tipo via morphMap */
    public function entidad()
    {
        return $this->morphTo('entidad', 'entidad_tipo', 'entidad_id');
    }

    public function encuesta()
    {
        return $this->belongsTo(EncuestaProlipa::class, 'encuesta_id');
    }
}
