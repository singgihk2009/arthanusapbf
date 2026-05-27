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
use App\Services\Inventory\InventoryAvailabilityService;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class SalesOrderController extends Controller{
public function __construct(private readonly SalesOrderService $service, private readonly InventoryAvailabilityService $availabilityService){}
public function index(Request $r){abort_unless($r->user()?->can('sales-order.view'),403);$search=$r->string('search');$status=$r->string('status');$sales=Sale::with('customer:id,customer_name')->withCount('lines')->when($search->toString()!=='',fn($q)=>$q->where('number','like',"%$search%"))->when($status->toString()!=='',fn($q)=>$q->where('status',$status))->latest()->paginate(15)->withQueryString();return Inertia::render('Apps/Sales/SalesOrders/Index',['salesOrders'=>$sales,'filters'=>$r->only('search','status')]);}
public function customerIndex(Customer $customer,Request $r){abort_unless($r->user()?->can('sales-order.view'),403);return response()->json($customer->salesOrders()->latest()->get());}
public function createForCustomer(Customer $customer,Request $r){abort_unless($r->user()?->can('sales-order.create'),403);$customer->load('priceList:id,code,name');$priceList=$customer->price_list_id?PriceList::query()->whereKey($customer->price_list_id)->first():PriceList::query()->where('status','active')->where('is_default',true)->latest('id')->first();return Inertia::render('Apps/Sales/SalesOrders/Form',['customer'=>$customer,'warehouses'=>Warehouse::select('id','name')->orderBy('name')->get(),'priceList'=>$priceList,'priceListSource'=>$customer->price_list_id?'customer':'default','salesOrder'=>['status'=>'draft','status_label'=>'Draft','can_edit'=>true,'can_submit'=>false,'can_approve'=>false,'can_cancel'=>true,'can_create_shipment'=>false,'subtotal'=>0,'discount_total'=>0,'tax_total'=>0,'grand_total'=>0,'lines'=>[]]]);}
public function storeForCustomer(StoreSalesOrderRequest $req, Customer $customer){$sale=$this->service->createForCustomer($customer,$req->validated());return redirect()->route('apps.sales-orders.show',$sale)->with('success','Sales order created.');}
public function show(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.view'),403);$salesOrder->load(['customer.priceList','warehouse','priceList','lines.item.baseUom','lines.batch','lines.uom','lines.facilityScheme']);$salesOrder->lines->transform(function($line) use ($salesOrder){$available=$this->availabilityService->getAvailableStock((int)$line->item_id,$salesOrder->warehouse_id);$line->available_stock=$available;$line->stock_status=$this->availabilityService->stockStatus($available,(float)$line->qty_sold);return $line;});return Inertia::render('Apps/Sales/SalesOrders/Show',['salesOrder'=>$salesOrder]);}
public function edit(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.update'),403);abort_unless($salesOrder->status==='draft',422);$salesOrder->load('customer.priceList','lines.item.baseUom','lines.batch','lines.uom');$priceList=$salesOrder->price_list_id?PriceList::query()->whereKey($salesOrder->price_list_id)->first():PriceList::query()->where('status','active')->where('is_default',true)->latest('id')->first();return Inertia::render('Apps/Sales/SalesOrders/Form',['salesOrder'=>$salesOrder,'customer'=>$salesOrder->customer,'warehouses'=>Warehouse::select('id','name')->orderBy('name')->get(),'priceList'=>$priceList,'priceListSource'=>$salesOrder->customer?->price_list_id?'customer':'default','facilitySchemes'=>FacilityScheme::select('id','name')->orderBy('name')->get()]);}
public function update(UpdateSalesOrderRequest $req,Sale $salesOrder){$this->service->updateSale($salesOrder,$req->validated());return redirect()->route('apps.sales-orders.show',$salesOrder)->with('success','Sales order updated.');}
public function destroy(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.delete'),403);if($salesOrder->status!=='draft'){return back()->withErrors(['status'=>'Only draft can be deleted.']);}$salesOrder->delete();return redirect()->route('apps.sales-orders.index')->with('success','Deleted.');}
public function submit(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.submit'),403);$this->service->submit($salesOrder);return back()->with('success','Submitted');}
public function approve(Sale $salesOrder,Request $r){abort_unless($r->user()?->can('sales-order.approve'),403);$this->service->approve($salesOrder);return back()->with('success','Approved');}

public function batches(Request $r){abort_unless($r->user()?->canAny(['sales-order.create','sales-order.update']),403);$data=$r->validate(['item_id'=>['required','integer'],'warehouse_id'=>['nullable','integer']]);$rows=DB::table('item_batches as b')->leftJoin('stock_balances as sb',function($j) use ($data){$j->on('sb.batch_id','=','b.id')->on('sb.item_id','=','b.item_id'); if(!empty($data['warehouse_id'])){$j->where('sb.warehouse_id','=',$data['warehouse_id']);}})->where('b.item_id',$data['item_id'])->groupBy('b.id','b.batch_no','b.expired_date')->leftJoin('inv_batches as ib', function ($j) { $j->on('ib.batch_no','=','b.batch_no')->on('ib.product_id','=','b.item_id'); })
->groupBy('b.id','b.item_id','b.batch_no','b.expired_date','ib.unit_cost')
->selectRaw('b.id,b.item_id,b.batch_no,b.expired_date,COALESCE(SUM(sb.on_hand_base),0) as available_stock, COALESCE(MAX(ib.unit_cost),0) as cogs')
->orderBy('b.expired_date')->orderBy('b.batch_no')->get();return response()->json($rows);}
public function cancel(Request $r,Sale $salesOrder){abort_unless($r->user()?->can('sales-order.cancel'),403);$r->validate(['cancel_reason'=>['required','string']]);$this->service->cancel($salesOrder,$r->string('cancel_reason')->toString());return back()->with('success','Cancelled');}
}
