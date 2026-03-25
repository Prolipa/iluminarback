<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncuestaRespuestaDetalleProlipa extends Model
{
    use HasFactory;
    protected $table = 'encuesta_prolipa_respuestas_detalle';

    protected $fillable = [
        'respuesta_id',
        'pregunta_id',
        'opcion_id',
        'valor_escala',
        'texto_abierto'
    ];
}
