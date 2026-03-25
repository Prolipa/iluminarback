<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncuestaOpcionProlipa extends Model
{
    use HasFactory;
    protected $table = 'encuesta_prolipa_opciones';

    protected $fillable = [
        'pregunta_id',
        'texto',
        'valor'
    ];
}
