<?php
namespace App\Http\Controllers\Apps\MasterData;
use App\Http\Controllers\Controller;
use App\Models\Regulatory\RegulatorySource;
use Illuminate\Http\Request;
class RegulatorySourceController extends Controller {
    public function index(Request $request){$q=trim((string)$request->get('q'));$sources=RegulatorySource::query()->when($q,fn($x)=>$x->where('source_name','like',"%$q%"))->paginate(10)->withQueryString(); return inertia('Apps/MasterData/RegulatorySources/Index',['sources'=>$sources,'filters'=>['q'=>$q]]);}
}
