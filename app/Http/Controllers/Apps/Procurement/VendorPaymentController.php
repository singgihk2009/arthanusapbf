<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorPaymentRequest;
use App\Http\Requests\Procurement\UpdateVendorPaymentRequest;
use App\Models\Procurement\Vendor;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VendorPaymentController extends Controller
{
    public function index(Vendor $vendor){ return Inertia::render('Apps/Procurement/VendorPayments/Index',['vendor'=>$vendor]); }
    public function create(Vendor $vendor){ return Inertia::render('Apps/Procurement/VendorPayments/Form',['vendor'=>$vendor,'outstandingInvoices'=>$this->outstandingInvoices($vendor),'bankAccounts'=>$this->bankAccounts($vendor)]); }
    public function show(Vendor $vendor, VendorPayment $payment){ return Inertia::render('Apps/Procurement/VendorPayments/Show',['vendor'=>$vendor,'payment'=>$payment->load('lines')]); }
    public function edit(Vendor $vendor, VendorPayment $payment){ abort_unless($payment->can_edit, 422, 'Only draft can be edited'); return Inertia::render('Apps/Procurement/VendorPayments/Form',['vendor'=>$vendor,'payment'=>$payment->load('lines'),'outstandingInvoices'=>$this->outstandingInvoices($vendor),'bankAccounts'=>$this->bankAccounts($vendor)]); }
    public function store(StoreVendorPaymentRequest $request, Vendor $vendor){ DB::transaction(fn()=> $this->upsertPayment(new VendorPayment(),$vendor,$request->validated())); return back()->with('success','Payment draft berhasil disimpan.'); }
    public function update(UpdateVendorPaymentRequest $request, Vendor $vendor, VendorPayment $payment){ abort_unless($payment->can_edit, 422); DB::transaction(fn()=> $this->upsertPayment($payment,$vendor,$request->validated())); return back()->with('success','Payment draft berhasil diperbarui.'); }
    public function submit(Vendor $vendor, VendorPayment $payment){ return $this->changeStatus($payment,'DRAFT','SUBMITTED','payment_submitted'); }
    public function approve(Vendor $vendor, VendorPayment $payment){ return $this->changeStatus($payment,'SUBMITTED','APPROVED','payment_approved', ['approved_by'=>auth()->id(),'approved_at'=>now()]); }
    public function markAsPaid(Vendor $vendor, VendorPayment $payment){
        DB::transaction(function() use($payment){
            if (strtoupper((string)$payment->status) !== 'APPROVED') throw ValidationException::withMessages(['status'=>'Invalid status']);
            foreach ($payment->lines as $line){ $inv = VendorInvoice::lockForUpdate()->findOrFail($line->vendor_invoice_id); $inv->paid_amount += $line->payment_amount; $inv->wht_paid_amount = ($inv->wht_paid_amount ?? 0) + $line->wht_amount; $inv->outstanding_amount = max(0, (float)$inv->net_payable_amount - (float)$inv->paid_amount - (float)$inv->wht_paid_amount); $inv->status = $inv->outstanding_amount <= 0 ? 'PAID' : ((($inv->paid_amount + $inv->wht_paid_amount) > 0) ? 'PARTIAL_PAID' : 'POSTED'); $inv->payment_status = $inv->outstanding_amount <= 0 ? 'paid' : ((($inv->paid_amount + $inv->wht_paid_amount) > 0) ? 'partial_paid' : 'unpaid'); $inv->save(); }
            $payment->update(['status'=>'PAID','paid_by'=>auth()->id(),'paid_at'=>now()]);
        }); return back()->with('success','Payment marked as paid.'); }
    public function post(Vendor $vendor, VendorPayment $payment){ DB::transaction(function() use($payment){ if(strtoupper((string)$payment->status)!=='PAID') throw ValidationException::withMessages(['status'=>'Payment must be paid first']); $this->postToGeneralLedger($payment); $payment->update(['status'=>'POSTED','posted_by'=>auth()->id(),'posted_at'=>now()]); }); return back()->with('success','Payment posted.'); }
    public function cancel(Vendor $vendor, VendorPayment $payment){ DB::transaction(function() use($payment){ $status=strtoupper((string)$payment->status); if($status==='POSTED') throw ValidationException::withMessages(['status'=>'Posted payment requires reversal flow']); if($status==='PAID'){ foreach($payment->lines as $line){ $inv=VendorInvoice::lockForUpdate()->findOrFail($line->vendor_invoice_id); $inv->paid_amount=max(0,$inv->paid_amount-$line->payment_amount); $inv->wht_paid_amount=max(0,($inv->wht_paid_amount??0)-$line->wht_amount); $inv->outstanding_amount=max(0,(float)$inv->net_payable_amount-(float)$inv->paid_amount-(float)$inv->wht_paid_amount); $inv->payment_status=$inv->outstanding_amount<=0?'paid':((($inv->paid_amount+$inv->wht_paid_amount)>0)?'partial_paid':'unpaid'); $inv->status=$inv->outstanding_amount<=0?'PAID':((($inv->paid_amount+$inv->wht_paid_amount)>0)?'PARTIAL_PAID':'POSTED'); $inv->save(); }} $payment->update(['status'=>'CANCELLED']); }); return back()->with('success','Payment cancelled.'); }
    public function destroy(VendorPayment $vendor_payment)
    {
        abort_unless($vendor_payment->can_edit, 422, 'Only draft can be deleted');

        DB::transaction(function () use ($vendor_payment) {
            $vendor_payment->lines()->delete();
            $vendor_payment->delete();
        });

        return back()->with('success', 'Payment draft berhasil dihapus.');
    }

    private function upsertPayment(VendorPayment $payment, Vendor $vendor, array $data): VendorPayment {
        $invoiceIds = collect($data['lines'])->pluck('vendor_invoice_id')->unique()->all();
        $invoices = VendorInvoice::whereIn('id',$invoiceIds)->lockForUpdate()->get()->keyBy('id');
        $totalInvoice=0; $totalWht=0; $lines=[];
        foreach($data['lines'] as $line){ $inv=$invoices[$line['vendor_invoice_id']]??null; if(!$inv || (int)$inv->vendor_id !== (int)$vendor->id) throw ValidationException::withMessages(['lines'=>'Invoice vendor mismatch']); $outstanding=(float)$inv->outstanding_amount; $pay=(float)$line['payment_amount']; $wht=(float)($line['wht_amount']??0); if(($pay+$wht) > $outstanding + 0.0001) throw ValidationException::withMessages(['lines'=>'Overpayment detected']); $totalInvoice+=$pay; $totalWht+=$wht; $lines[]=[ 'vendor_invoice_id'=>$inv->id,'invoice_number'=>$inv->vendor_invoice_no ?? $inv->invoice_no_internal,'invoice_date'=>$inv->invoice_date,'invoice_total_amount'=>$inv->net_payable_amount ?? $inv->grand_total,'invoice_outstanding_amount'=>$outstanding,'payment_amount'=>$pay,'wht_amount'=>$wht,'net_payment_amount'=>$pay-$wht,'notes'=>$line['notes']??null ]; }
        $stamp=(float)($data['stamp_duty_amount']??0); $freight=(float)($data['freight_amount']??0); $bank=(float)($data['bank_charge_amount']??0);
        $additional=$stamp+$freight+$bank; $net=$totalInvoice-$totalWht+$stamp+$freight; $cashOut=$net+$bank;
        if ($cashOut > ($totalInvoice + 0.0001)) {
            throw ValidationException::withMessages([
                'bank_charge_amount' => 'Total cash out tidak boleh melebihi total tagihan invoice yang dipilih.',
            ]);
        }
        $paymentNo = $payment->payment_no ?: $this->nextNo();
        $payment->fill(['vendor_id'=>$vendor->id,'payment_no'=>$paymentNo,'payment_number'=>$payment->payment_number ?: $paymentNo,'payment_date'=>$data['payment_date'],'payment_method'=>strtoupper((string)($data['payment_method'] ?? 'BANK_TRANSFER')),'bank_account_id'=>$data['bank_account_id'] ?? null,'currency'=>'IDR','status'=>$payment->exists ? $payment->status : 'DRAFT','total_invoice_amount'=>$totalInvoice,'total_wht_amount'=>$totalWht,'stamp_duty_amount'=>$stamp,'freight_amount'=>$freight,'bank_charge_amount'=>$bank,'total_additional_cost'=>$additional,'net_vendor_payment_amount'=>$net,'total_cash_out_amount'=>$cashOut,'notes'=>$data['notes']??null,'created_by'=>$payment->created_by ?? auth()->id(),'updated_by'=>auth()->id()]);
        $payment->save(); $payment->lines()->delete(); $payment->lines()->createMany($lines); return $payment->fresh('lines');
    }
    private function nextNo(): string { $prefix='VPY-'.now()->format('Ym').'-'; $last=VendorPayment::where('payment_no','like',$prefix.'%')->lockForUpdate()->orderByDesc('payment_no')->value('payment_no'); $seq=$last?((int)substr($last,-5)+1):1; $candidate=$prefix.str_pad((string)$seq,5,'0',STR_PAD_LEFT); while (VendorPayment::where('payment_no', $candidate)->exists()) { $seq++; $candidate = $prefix.str_pad((string)$seq,5,'0',STR_PAD_LEFT); } return $candidate; }
    private function outstandingInvoices(Vendor $vendor){ return VendorInvoice::where('vendor_id',$vendor->id)->where('outstanding_amount','>',0)->whereIn('status',['POSTED','PARTIAL_PAID'])->orderBy('invoice_date')->get(); }
    private function bankAccounts(Vendor $vendor)
    {
        if (! Schema::hasTable('vendor_bank_accounts')) {
            return [];
        }

        return DB::table('vendor_bank_accounts')
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_number', 'account_name', 'is_default'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'label' => trim(sprintf('%s - %s a/n %s%s', $row->bank_name, $row->account_number, $row->account_name, $row->is_default ? ' (Default)' : '')),
            ])
            ->values();
    }

    private function changeStatus(VendorPayment $payment,string $from,string $to,string $event,array $extra=[]){ if(strtoupper((string)$payment->status)!==$from) throw ValidationException::withMessages(['status'=>'Invalid status transition']); $payment->update(array_merge(['status'=>$to],$extra)); return back()->with('success',str_replace('_',' ',$event)); }
    private function postToGeneralLedger(VendorPayment $vendorPayment): array { return ['document_type'=>'vendor_payment','document_id'=>$vendorPayment->id,'status'=>'prepared']; }
}
