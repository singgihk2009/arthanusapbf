<?php
namespace App\Http\Controllers\Apps\MasterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemRegulatoryMappingRequest;
use App\Http\Requests\MasterData\RegulatoryProductRequest;
use App\Models\Inventory\Item;
use App\Models\Regulatory\ItemRegulatoryProduct;
use App\Models\Regulatory\RegulatoryProduct;
use App\Models\Regulatory\RegulatorySource;
use App\Services\Regulatory\ProductMatchingService;
use App\Services\Regulatory\RegulatoryProductImportService;
use Illuminate\Http\Request;
class RegulatoryProductController extends Controller {
 public function index(Request $request){$q=trim((string)$request->get('q'));$items=RegulatoryProduct::with('source')->when($q,fn($x)=>$x->where('nie','like',"%$q%")->orWhere('product_name_source','like',"%$q%"))->paginate(10)->withQueryString(); return inertia('Apps/MasterData/RegulatoryProducts/Index',['products'=>$items,'filters'=>['q'=>$q]]);} 
 public function create(){return inertia('Apps/MasterData/RegulatoryProducts/Create',['sources'=>RegulatorySource::all()]);}
 public function store(RegulatoryProductRequest $request){RegulatoryProduct::create($request->validated());return to_route('apps.master-data.regulatory-products.index');}
 public function edit(RegulatoryProduct $regulatoryProduct){$regulatoryProduct->load('compositions','packagings','source');return inertia('Apps/MasterData/RegulatoryProducts/Edit',['product'=>$regulatoryProduct,'sources'=>RegulatorySource::all(),'items'=>Item::select('id','sku','name')->limit(100)->get()]);}
 public function update(RegulatoryProductRequest $request, RegulatoryProduct $regulatoryProduct){$regulatoryProduct->update($request->validated());return back();}
 public function destroy(RegulatoryProduct $regulatoryProduct){$regulatoryProduct->delete();return back();}
 public function importBpom(Request $request, RegulatoryProductImportService $s){$request->validate(['file'=>['required','file','mimes:csv,txt']]);$count=$s->importBpom($request->file('file')->getRealPath());return back()->with('success',"Import BPOM berhasil: {$count}");}
 public function importKemenkes(Request $request, RegulatoryProductImportService $s){$request->validate(['file'=>['required','file','mimes:csv,txt']]);$count=$s->importKemenkes($request->file('file')->getRealPath());return back()->with('success',"Import KEMENKES berhasil: {$count}");}
 public function attach(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::firstOrCreate($request->validated(),['is_primary'=>false]);return back();}
 public function detach(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::where('item_id',$request->item_id)->where('regulatory_product_id',$request->regulatory_product_id)->delete();return back();}
 public function setPrimary(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::where('item_id',$request->item_id)->update(['is_primary'=>false]); ItemRegulatoryProduct::where('item_id',$request->item_id)->where('regulatory_product_id',$request->regulatory_product_id)->update(['is_primary'=>true]);return back();}
 public function candidates(RegulatoryProduct $regulatoryProduct, ProductMatchingService $service){ return response()->json($service->candidates($regulatoryProduct)); }
}
