<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncuestaRespuestaProlipa extends Model
{
    use HasFactory;
    protected $table = 'encuesta_prolipa_respuestas';

    protected $fillable = [
        'encuesta_id',
        'usuario_id',
        'asignacion_id',
        'periodo_id',
        'descargo_certificado',
        'fecha_descarga',
    ];

    public function detalles()
    {
        return $this->hasMany(EncuestaRespuestaDetalleProlipa::class, 'respuesta_id');
    }
}
