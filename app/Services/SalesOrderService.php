<?php

namespace App\Services;

use App\Models\Inventory\Item;
use App\Models\Sales\Customer;
use App\Models\Sales\PriceList;
use App\Models\Sales\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function generateNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'SO-'.now()->format('Ym').'-';
            $last = Sale::query()->withTrashed()->where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('number');
            $seq = $last ? ((int) substr($last, -5)) + 1 : 1;
            return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        });
    }

    public function calculateLine(array $line): array {
        $gross = (float)$line['qty_sold'] * (float)$line['unit_price'];
        $discountAmount = $gross * ((float)($line['discount_percent'] ?? 0) / 100);
        $taxable = $gross - $discountAmount;
        $taxAmount = $taxable * ((float)($line['tax_percent'] ?? 0) / 100);
        $lineTotal = $taxable + $taxAmount;
        return array_merge($line, ['discount_amount'=>round($discountAmount,2),'tax_amount'=>round($taxAmount,2),'line_total'=>round($lineTotal,2)]);
    }
    public function calculateTotals(array $lines): array {
        $subtotal=$discount=$tax=$grand=0; foreach($lines as $l){$subtotal+=((float)$l['qty_sold']*(float)$l['unit_price']);$discount+=(float)$l['discount_amount'];$tax+=(float)$l['tax_amount'];$grand+=(float)$l['line_total'];}
        return ['subtotal'=>round($subtotal,2),'discount_total'=>round($discount,2),'tax_total'=>round($tax,2),'grand_total'=>round($grand,2)];
    }
    public function createForCustomer(Customer $customer,array $data): Sale { return DB::transaction(function() use($customer,$data){$data['number']=$data['number']??$this->generateNumber();$data['customer_id']=$customer->id;$data['price_list_id']=$data['price_list_id']??$customer->price_list_id??PriceList::query()->where('is_default',true)->where('status','active')->value('id');$data['status']='draft';$sale=Sale::create($data);$lines=$this->syncLines($sale,$data['lines']);$sale->update($this->calculateTotals($lines));return $sale->fresh('lines');}); }
    public function updateSale(Sale $sale,array $data): Sale { if($sale->status!=='draft'){throw ValidationException::withMessages(['status'=>'Only draft can be updated']);} return DB::transaction(function()use($sale,$data){$sale->update(collect($data)->except('lines')->all());$lines=$this->syncLines($sale,$data['lines']);$sale->update($this->calculateTotals($lines));return $sale->fresh('lines');}); }
    public function submit(Sale $sale): Sale { if($sale->status!=='draft'){throw ValidationException::withMessages(['status'=>'Only draft can be submitted']);} if(!$sale->lines()->exists()){throw ValidationException::withMessages(['lines'=>'At least one line required']);}$sale->update(['status'=>'submitted','submitted_by'=>Auth::id(),'submitted_at'=>now()]);return $sale; }
    public function approve(Sale $sale): Sale { if($sale->status!=='submitted'){throw ValidationException::withMessages(['status'=>'Only submitted can be approved']);}$sale->update(['status'=>'approved','approved_by'=>Auth::id(),'approved_at'=>now()]);return $sale; }
    public function cancel(Sale $sale,?string $reason=null): Sale { if(in_array($sale->status,['fully_shipped','fully_invoiced','closed','cancelled'],true)){throw ValidationException::withMessages(['status'=>'Cannot cancel this order']);}$sale->update(['status'=>'cancelled','cancelled_by'=>Auth::id(),'cancelled_at'=>now(),'cancel_reason'=>$reason]);return $sale; }
    public function syncLines(Sale $sale,array $lines): array { if($sale->status!=='draft'){throw ValidationException::withMessages(['status'=>'Lines can only be changed on draft']);}
        $existing = $sale->lines()->get()->keyBy('id'); $keep=[]; $calculated=[];
        foreach($lines as $line){if(empty($line['uom_id']) && !empty($line['item_id'])){$line['uom_id']=Item::query()->whereKey($line['item_id'])->value('base_uom_id');} $line=$this->calculateLine($line); if(!empty($line['id']) && $existing->has($line['id'])){$model=$existing[$line['id']]; if($model->qty_shipped>0||$model->qty_invoiced>0){throw ValidationException::withMessages(['lines'=>'Cannot edit fulfilled lines']);}$model->update($line);$keep[]=$model->id;} else {$created=$sale->lines()->create($line);$keep[]=$created->id;} $calculated[]=$line;}
        $sale->lines()->whereNotIn('id',$keep)->where('qty_shipped',0)->where('qty_invoiced',0)->delete(); return $calculated; }
}
