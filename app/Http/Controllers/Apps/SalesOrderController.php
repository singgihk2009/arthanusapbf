<?php
namespace App\Http\Controllers\Apps;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Models\Inventory\FacilityScheme;
use App\Models\Inventory\Item;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Customer;
use App\Models\Sales\PriceList;
use App\Models\Sales\Sale;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class SalesOrderController extends Controller{
public function __construct(private readonly SalesOrderService $service){}
public function index(Request $r){abort_unless($r->user()?->can('sales-order.view'),403);$search=$r->string('search');$status=$r->string('status');$sales=Sale::with('customer:id,customer_name')->when($search->toString()!=='',fn($q)=>$q->where('number','like',"%$search%"))->when($status->toString()!=='',fn($q)=>$q->where('status',$status))->latest()->paginate(15)->withQueryString();return Inertia::render('Apps/Sales/SalesOrders/Index',['salesOrders'=>$sales,'filters'=>$r->only('search','status')]);}
public function customerIndex(Customer $customer,Request $r){abort_unless($r->user()?->can('sales-order.view'),403);return response()->json($customer->salesOrders()->latest()->get());}
public function createForCustomer(Customer $customer,Request $r){abort_unless($r->user()?->can('sales-order.create'),403);return Inertia::render('Apps/Sales/SalesOrders/Form',['customer'=>$customer,'warehouses'=>Warehouse::select('id','name')->orderBy('name')->get(),'items'=>Item::select('id','name','base_uom_id')->with('baseUom:id,name')->orderBy('name')->get(),'priceList'=>PriceList::find($customer->price_list_id)]);}
public function storeForCustomer(StoreSalesOrderRequest $req, Customer $customer){$sale=$this->service->createForCustomer($customer,$req->validated());return redirect()->route('apps.sales-orders.show',$sale)->with('success','Sales order created.');}
public function show(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.view'),403);$salesOrder->load(['customer','warehouse','priceList','lines.item','lines.uom','lines.facilityScheme']);return Inertia::render('Apps/Sales/SalesOrders/Show',['salesOrder'=>$salesOrder]);}
public function edit(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.update'),403);abort_unless($salesOrder->status==='draft',422);$salesOrder->load('customer','lines');return Inertia::render('Apps/Sales/SalesOrders/Form',['salesOrder'=>$salesOrder,'customer'=>$salesOrder->customer,'warehouses'=>Warehouse::select('id','name')->orderBy('name')->get(),'items'=>Item::select('id','name','base_uom_id')->with('baseUom:id,name')->orderBy('name')->get(),'facilitySchemes'=>FacilityScheme::select('id','name')->orderBy('name')->get()]);}
public function update(UpdateSalesOrderRequest $req,Sale $salesOrder){$this->service->updateSale($salesOrder,$req->validated());return redirect()->route('apps.sales-orders.show',$salesOrder)->with('success','Sales order updated.');}
public function destroy(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.delete'),403);if($salesOrder->status!=='draft'){return back()->withErrors(['status'=>'Only draft can be deleted.']);}$salesOrder->delete();return redirect()->route('apps.sales-orders.index')->with('success','Deleted.');}
public function submit(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.submit'),403);$this->service->submit($salesOrder);return back()->with('success','Submitted');}
public function approve(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.approve'),403);$this->service->approve($salesOrder);return back()->with('success','Approved');}
public function cancel(Request $r,Sale $salesOrder){abort_unless($r->user()?->can('sales-order.cancel'),403);$r->validate(['cancel_reason'=>['required','string']]);$this->service->cancel($salesOrder,$r->string('cancel_reason')->toString());return back()->with('success','Cancelled');}
}
