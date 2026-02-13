<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Institucion;
use Illuminate\Http\Request;
use App\Models\SallePeriodo;
use DB;
class SallePeriodoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //api:get/salle/periodos
    public function index(Request $request)
    {
        $byInstitucion = $request->byInstitucion;
        if($byInstitucion){
            //api:get/salle/periodos?byInstitucion=1690
            $getInstitucion     = Institucion::findOrFail($byInstitucion);
            $tipo_institucion_id = $getInstitucion->tipo_institucion;
            return SallePeriodo::leftJoin(
                'institucion_tipo_institucion as t',
                't.id',
                '=',
                'salle_periodos_evaluacion.tipo_institucion_id'
            )
            ->select(
                'salle_periodos_evaluacion.*',
                't.descripcion as tipo_institucion'
            )
            ->where('salle_periodos_evaluacion.tipo_institucion_id', $tipo_institucion_id)
            ->orderBy('salle_periodos_evaluacion.id', 'desc')
            ->get();

        }
        else if($request->tipo_institucion_id){
            //api:get/salle/periodos?tipo_institucion_id=1
            $tipo_institucion_id = $request->tipo_institucion_id;
            return SallePeriodo::leftJoin(
                'institucion_tipo_institucion as t',
                't.id',
                '=',
                'salle_periodos_evaluacion.tipo_institucion_id'
            )
            ->select(
                'salle_periodos_evaluacion.*',
                't.descripcion as tipo_institucion'
            )
            ->where('salle_periodos_evaluacion.tipo_institucion_id', $tipo_institucion_id)
            ->orderBy('salle_periodos_evaluacion.id', 'desc')
            ->get();
        }
        return SallePeriodo::leftJoin(
                'institucion_tipo_institucion as t',
                't.id',
                '=',
                'salle_periodos_evaluacion.tipo_institucion_id'
            )
            ->select(
                'salle_periodos_evaluacion.*',
                't.descripcion as tipo_institucion'
            )
            ->orderBy('salle_periodos_evaluacion.id', 'desc')
            ->get();
    }

    //API:GET/salle/metodosGetSalle
    public function metodosGetSalle(Request $request){
        switch ($request->action) {
            case 'GET_EVALUACION_ACTIVA':
                return $this->GET_EVALUACION_ACTIVA($request);
                break;
            default:
                return ["status" => "0", "message" => "Método no encontrado"];
                break;
        }
    }

    //API:GET/>>salle/metodosGetSalle?action=GET_EVALUACION_ACTIVA&GET_EVALUACION_ACTIVA&institucion_id=1690
    public function GET_EVALUACION_ACTIVA(Request $request){
        $institucion_id      = $request->institucion_id;
        $getInstitucion      = Institucion::findOrFail($institucion_id);
        $tipo_institucion_id  = $getInstitucion->tipo_institucion;
        $idEvaluacionActiva = null;
        $nombreEvaluacionActiva = null;
        $getEvaluacionActiva  = SallePeriodo::where('estado', '1')
            ->where('tipo_institucion_id', $tipo_institucion_id)
            ->first();
        if($getEvaluacionActiva){
            $idEvaluacionActiva = $getEvaluacionActiva->id;
            $nombreEvaluacionActiva = $getEvaluacionActiva->nombre;
        }else{
            return [
                "status" => "0",
                "message" => "No hay evaluación activa para esta institución",
                "data" => [
                    "idEvaluacionActiva" => $idEvaluacionActiva,
                    "nombreEvaluacionActiva" => $nombreEvaluacionActiva,
                    'tipo_institucion_id' => $tipo_institucion_id
                ]
            ];
        }
        return [
            "status" => "1",
            "message" => "Evaluación activa obtenida correctamente",
            "data" => [
                "idEvaluacionActiva" => $idEvaluacionActiva,
                "nombreEvaluacionActiva" => $nombreEvaluacionActiva,
                "tipo_institucion_id" => $tipo_institucion_id
            ]
        ];

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //desactivar/activar periodo
        if($request->cambiarEstadoPeriodo){
            return $this->cambiarEstadoPeriodo($request);
        }else{
            if($request->id > 0){
                $periodo = SallePeriodo::findOrFail($request->id);
            }else{
                $periodo                    = new SallePeriodo();
            }
            $periodo->nombre                = $request->nombre;
            $periodo->tipo_institucion_id   = $request->tipo_institucion_id;
            $periodo->save();
            return $this->saveStatus($periodo);
        }
    }
    public function cambiarEstadoPeriodo($request){
        //validate que no hay otro periodo abierto solo puede aver uno
        $id         = $request->id;
        $estado     = $request->estado;
        $periodo    = SallePeriodo::findOrFail($request->id);
        $tipo_institucion_id = $periodo->tipo_institucion_id;
        if($estado == 1){
            $query = DB::SELECT("SELECT * FROM salle_periodos_evaluacion p
            WHERE p.estado = '1'
            AND p.tipo_institucion_id = '$tipo_institucion_id'
            ");
            if(count($query) > 0){
                return ["status" => "0", "message" => "Ya existe activo un período de evaluación"];
            }
        }
        $periodo            = SallePeriodo::findOrFail($request->id);
        $periodo->estado    = $request->estado;
        $periodo->save();
        return $this->saveStatus($periodo);
    }
    public function saveStatus($periodo){
        if($periodo){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
