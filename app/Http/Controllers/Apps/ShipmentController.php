<?php
namespace App\Http\Controllers\Apps;
use App\Http\Controllers\Controller;use App\Http\Requests\CancelShipmentRequest;use App\Http\Requests\PostShipmentRequest;use App\Http\Requests\StoreShipmentRequest;use App\Models\Sales\Sale;use App\Models\Sales\Shipment;use App\Services\ShipmentService;use Illuminate\Http\Request;use Inertia\Inertia;
class ShipmentController extends Controller{
 public function __construct(private readonly ShipmentService $service){}
 public function index(Request $r){abort_unless($r->user()?->can('shipment.view'),403);$q=Shipment::with(['sale:id,number','customer:id,customer_name','warehouse:id,name'])->latest();if($s=$r->string('search')->toString())$q->where('number','like',"%$s%");if($st=$r->string('status')->toString())$q->where('status',$st);return Inertia::render('Apps/Sales/Shipments/Index',['shipments'=>$q->paginate(15)->withQueryString(),'filters'=>$r->only('search','status')]);}
 public function createFromSale(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('shipment.create'),403);$salesOrder->load(['customer','warehouse','lines.item','lines.uom']);return Inertia::render('Apps/Sales/Shipments/Form',['salesOrder'=>$salesOrder]);}
 public function storeFromSale(StoreShipmentRequest $req, Sale $salesOrder){$shipment=$this->service->createFromSale($salesOrder,$req->validated());return to_route('apps.shipments.show',$shipment)->with('success','Draft shipment created.');}
 public function show(Shipment $shipment,Request $r){abort_unless($r->user()?->can('shipment.view'),403);$shipment->load(['sale:id,number,status','customer:id,customer_name','warehouse:id,name','lines.item','lines.batch','lines.uom']);$dispatch=null;if($shipment->dispatch_id){$dispatch=\DB::table('internal_usages')->where('id',$shipment->dispatch_id)->first();}return Inertia::render('Apps/Sales/Shipments/Show',['shipment'=>$shipment,'dispatch'=>$dispatch]);}
 public function post(PostShipmentRequest $request, Shipment $shipment){try{$this->service->postShipment($shipment);return back()->with('success','Shipment posted.');}catch(\Throwable $e){return back()->withErrors(['post'=>'Shipment posting failed: '.$e->getMessage()]);}}
 public function cancel(CancelShipmentRequest $request, Shipment $shipment){$this->service->cancelShipment($shipment,$request->validated('cancel_reason'));return back()->with('success','Shipment cancelled.');}
}
