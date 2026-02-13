<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionGeneral extends Model
{
    use HasFactory;

    protected $table = 'configuracion_general';
    
    protected $primaryKey = 'id';
    
    public $timestamps = true;
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'nombre',
        'minimo',
        'maximo',
        'descripcion',
        'user_created',
        'id_seleccion',
        'id_seleccion_padre',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'minimo' => 'double',
        'maximo' => 'double',
        'user_created' => 'integer',
        'id_seleccion' => 'integer',
        'id_seleccion_padre' => 'integer',
    ];
}
