<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class AgendaPlanificacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = "planificacion_agenda";
    protected $primaryKey = 'id';

    // Deshabilitar el casting automático de fechas para evitar problemas de timezone
    protected $casts = [
        'startDate' => 'string',
        'endDate' => 'string',
    ];
}
