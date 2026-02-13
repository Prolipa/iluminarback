<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionGeneral;
use Illuminate\Http\Request;

class ConfiguracionGeneralController extends Controller
{
    // GET /configuracion_general?id=12
    public function configuracion_general(Request $request)
    {
        if ($request->has('id')) {
            $configuracion = ConfiguracionGeneral::where('id', $request->id)->get();
            return $configuracion;
        }
        return ConfiguracionGeneral::all();
    }
}
