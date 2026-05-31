<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorPaymentRequest;
use App\Http\Requests\Procurement\UpdateVendorPaymentRequest;
use App\Models\Document;
use App\Models\CashAccount;
use App\Models\DocumentType;
use App\Models\Procurement\Vendor;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VendorPaymentController extends Controller
{
    public function index(Vendor $vendor){ return Inertia::render('Apps/Procurement/VendorPayments/Index',['vendor'=>$vendor]); }
    public function create(Vendor $vendor){ return Inertia::render('Apps/Procurement/VendorPayments/Form',['vendor'=>$vendor,'outstandingInvoices'=>$this->outstandingInvoices($vendor),'bankAccounts'=>$this->bankAccounts($vendor),'cashAccounts'=>$this->cashAccounts(), 'documentTypes' => $this->documentTypes(), 'uploadedDocuments' => collect()]); }
    public function show(Vendor $vendor, VendorPayment $payment){ return Inertia::render('Apps/Procurement/VendorPayments/Show',['vendor'=>$vendor,'payment'=>$payment->load('lines','bankAccount:id,bank_name,account_number,account_name','cashAccount.chartOfAccount:id,account_code,account_name'),'uploadedDocuments' => $this->uploadedDocuments($payment)]); }
    public function edit(Vendor $vendor, VendorPayment $payment){ abort_unless($payment->can_edit, 422, 'Only draft can be edited'); return Inertia::render('Apps/Procurement/VendorPayments/Form',['vendor'=>$vendor,'payment'=>$payment->load('lines'),'outstandingInvoices'=>$this->outstandingInvoices($vendor),'bankAccounts'=>$this->bankAccounts($vendor),'cashAccounts'=>$this->cashAccounts(), 'documentTypes' => $this->documentTypes(), 'uploadedDocuments' => $this->uploadedDocuments($payment)]); }
    public function store(StoreVendorPaymentRequest $request, Vendor $vendor){ $data = $request->validated(); $uploadedDocumentCount = 0; DB::transaction(function() use($data, $vendor, $request, &$uploadedDocumentCount){ $payment = $this->upsertPayment(new VendorPayment(),$vendor,$data); $uploadedDocumentCount = $this->attachDocumentsToPayment($payment, (array) ($data['documents'] ?? []), $request); }); $message = 'Payment draft berhasil disimpan.'; if ($uploadedDocumentCount > 0) { $message .= " {$uploadedDocumentCount} dokumen berhasil diupload."; } return back()->with('success',$message); }
    public function update(UpdateVendorPaymentRequest $request, Vendor $vendor, VendorPayment $payment){ abort_unless($payment->can_edit, 422); $data = $request->validated(); $uploadedDocumentCount = 0; DB::transaction(function() use($data, $vendor, $payment, $request, &$uploadedDocumentCount){ $savedPayment = $this->upsertPayment($payment,$vendor,$data); $uploadedDocumentCount = $this->attachDocumentsToPayment($savedPayment, (array) ($data['documents'] ?? []), $request); }); $message = 'Payment draft berhasil diperbarui.'; if ($uploadedDocumentCount > 0) { $message .= " {$uploadedDocumentCount} dokumen berhasil diupload."; } return back()->with('success',$message); }
    public function submit(Vendor $vendor, VendorPayment $payment){ return $this->changeStatus($payment,'DRAFT','SUBMITTED','payment_submitted'); }
    public function approve(Vendor $vendor, VendorPayment $payment){ return $this->changeStatus($payment,'SUBMITTED','APPROVED','payment_approved', ['approved_by'=>auth()->id(),'approved_at'=>now()]); }
    public function markAsPaid(Vendor $vendor, VendorPayment $payment){
        DB::transaction(function() use($payment){
            if (strtoupper((string)$payment->status) !== 'APPROVED') throw ValidationException::withMessages(['status'=>'Invalid status']);
            foreach ($payment->lines as $line){ $inv = VendorInvoice::lockForUpdate()->findOrFail($line->vendor_invoice_id); $inv->paid_amount += $line->payment_amount; $inv->wht_paid_amount = ($inv->wht_paid_amount ?? 0) + $line->wht_amount; $inv->outstanding_amount = max(0, (float)$inv->net_payable_amount - (float)$inv->paid_amount - (float)$inv->wht_paid_amount); $inv->status = $inv->outstanding_amount <= 0 ? 'PAID' : ((($inv->paid_amount + $inv->wht_paid_amount) > 0) ? 'PARTIAL_PAID' : 'POSTED'); $inv->payment_status = $inv->outstanding_amount <= 0 ? 'paid' : ((($inv->paid_amount + $inv->wht_paid_amount) > 0) ? 'partial_paid' : 'unpaid'); $inv->save(); }
            $payment->update(['status'=>'PAID','paid_by'=>auth()->id(),'paid_at'=>now()]);
        }); return back()->with('success','Payment marked as paid.'); }
    public function post(Vendor $vendor, VendorPayment $payment){ DB::transaction(function() use($payment){ if(strtoupper((string)$payment->status)!=='PAID') throw ValidationException::withMessages(['status'=>'Payment must be paid first']); $this->postToGeneralLedger($payment); $payment->update(['status'=>'POSTED','posted_by'=>auth()->id(),'posted_at'=>now()]); $this->upsertVendorPaymentFinanceHubOutbox($payment->fresh(['vendor','lines','cashAccount.chartOfAccount'])); }); $this->sendVendorPaymentFinanceHubEvent($payment->id); return back()->with('success','Payment posted dan event Finance Hub Vendor Payment dibuat.'); }
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
        $reservedByOtherPayments = DB::table('vendor_payment_lines as vpl')
            ->join('vendor_payments as vp', 'vp.id', '=', 'vpl.vendor_payment_id')
            ->whereIn('vpl.vendor_invoice_id', $invoiceIds)
            ->where('vp.vendor_id', $vendor->id)
            ->whereNull('vp.deleted_at')
            ->whereNotIn(DB::raw('UPPER(vp.status)'), ['CANCELLED'])
            ->when($payment->exists, fn ($query) => $query->where('vp.id', '!=', $payment->id))
            ->groupBy('vpl.vendor_invoice_id')
            ->selectRaw('vpl.vendor_invoice_id, SUM(COALESCE(vpl.payment_amount,0) + COALESCE(vpl.wht_amount,0)) as reserved_total')
            ->pluck('reserved_total', 'vpl.vendor_invoice_id');

        $totalInvoice=0; $totalWht=0; $lines=[];
        foreach($data['lines'] as $line){ $inv=$invoices[$line['vendor_invoice_id']]??null; if(!$inv || (int)$inv->vendor_id !== (int)$vendor->id) throw ValidationException::withMessages(['lines'=>'Invoice vendor mismatch']); $baseOutstanding=(float)($inv->outstanding_amount ?? 0); $reserved=(float)($reservedByOtherPayments[$inv->id] ?? 0); $outstanding=max(0, $baseOutstanding - $reserved); $pay=(float)$line['payment_amount']; $wht=(float)($line['wht_amount']??0); if($outstanding <= 0.0001) throw ValidationException::withMessages(['lines'=>"Invoice {$inv->invoice_no_internal} sudah lunas / sudah dialokasikan di payment lain."]); if(($pay+$wht) > $outstanding + 0.0001) throw ValidationException::withMessages(['lines'=>'Overpayment detected']); $totalInvoice+=$pay; $totalWht+=$wht; $lines[]=[ 'vendor_invoice_id'=>$inv->id,'invoice_number'=>$inv->vendor_invoice_no ?? $inv->invoice_no_internal,'invoice_date'=>$inv->invoice_date,'invoice_total_amount'=>$inv->net_payable_amount ?? $inv->grand_total,'invoice_outstanding_amount'=>$outstanding,'payment_amount'=>$pay,'wht_amount'=>$wht,'net_payment_amount'=>$pay-$wht,'notes'=>$line['notes']??null ]; }
        $stamp=(float)($data['stamp_duty_amount']??0); $freight=(float)($data['freight_amount']??0); $bank=(float)($data['bank_charge_amount']??0);
        $additional=$stamp+$freight+$bank; $net=$totalInvoice-$totalWht+$stamp+$freight; $cashOut=$net+$bank;
        if ($cashOut > ($totalInvoice + 0.0001)) {
            throw ValidationException::withMessages([
                'bank_charge_amount' => 'Total cash out tidak boleh melebihi total tagihan invoice yang dipilih.',
            ]);
        }
        $paymentNo = $payment->payment_no ?: $this->nextNo();
        $payment->fill(['vendor_id'=>$vendor->id,'payment_no'=>$paymentNo,'payment_number'=>$payment->payment_number ?: $paymentNo,'payment_date'=>$data['payment_date'],'payment_method'=>strtoupper((string)($data['payment_method'] ?? 'BANK_TRANSFER')),'bank_account_id'=>$data['bank_account_id'] ?? null,'cash_account_id'=>$data['cash_account_id'] ?? null,'currency'=>'IDR','status'=>$payment->exists ? $payment->status : 'DRAFT','total_invoice_amount'=>$totalInvoice,'total_wht_amount'=>$totalWht,'stamp_duty_amount'=>$stamp,'freight_amount'=>$freight,'bank_charge_amount'=>$bank,'total_additional_cost'=>$additional,'net_vendor_payment_amount'=>$net,'total_cash_out_amount'=>$cashOut,'notes'=>$data['notes']??null,'created_by'=>$payment->created_by ?? auth()->id(),'updated_by'=>auth()->id()]);
        $payment->save(); $payment->lines()->delete(); $payment->lines()->createMany($lines); return $payment->fresh('lines');
    }
    private function nextNo(): string
    {
        $prefix = 'VPY-'.now()->format('Ym').'-';
        $lastPaymentNo = VendorPayment::withTrashed()
            ->where('payment_no', 'like', $prefix.'%')
            ->orderByDesc('payment_no')
            ->value('payment_no');

        $nextSeq = $lastPaymentNo ? ((int) substr((string) $lastPaymentNo, -5) + 1) : 1;

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
            $exists = VendorPayment::withTrashed()->where('payment_no', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }
    private function outstandingInvoices(Vendor $vendor){
        $invoices = VendorInvoice::query()
            ->where('vendor_id', $vendor->id)
            ->where('outstanding_amount', '>', 0)
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere(DB::raw('LOWER(payment_status)'), '!=', 'paid');
            })
            ->whereIn(DB::raw('UPPER(status)'), ['POSTED', 'PARTIAL_PAID'])
            ->orderBy('invoice_date')
            ->get();

        if ($invoices->isEmpty()) {
            return $invoices;
        }

        $reservedTotals = DB::table('vendor_payment_lines as vpl')
            ->join('vendor_payments as vp', 'vp.id', '=', 'vpl.vendor_payment_id')
            ->whereIn('vpl.vendor_invoice_id', $invoices->pluck('id')->all())
            ->where('vp.vendor_id', $vendor->id)
            ->whereNull('vp.deleted_at')
            ->whereNotIn(DB::raw('UPPER(vp.status)'), ['CANCELLED'])
            ->groupBy('vpl.vendor_invoice_id')
            ->selectRaw('vpl.vendor_invoice_id, SUM(COALESCE(vpl.payment_amount, 0) + COALESCE(vpl.wht_amount, 0)) as reserved_total')
            ->pluck('reserved_total', 'vpl.vendor_invoice_id');

        return $invoices
            ->map(function (VendorInvoice $invoice) use ($reservedTotals) {
                $reserved = (float) ($reservedTotals[$invoice->id] ?? 0);
                $invoice->outstanding_amount = max(0, (float) $invoice->outstanding_amount - $reserved);

                return $invoice;
            })
            ->filter(fn (VendorInvoice $invoice) => (float) $invoice->outstanding_amount > 0.0001)
            ->values();
    }

    private function cashAccounts()
    {
        if (! Schema::hasTable('cash_accounts')) {
            return [];
        }

        $companyId = (int) (auth()->user()?->company_id ?? 1);

        return CashAccount::query()
            ->with('chartOfAccount:id,account_code,account_name')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get()
            ->map(fn (CashAccount $account) => [
                'id' => $account->id,
                'cash_type' => $account->cash_type,
                'label' => trim(collect([
                    $account->code,
                    $account->name,
                    $account->cash_type === 'BANK' ? $account->bank_name : null,
                    $account->chartOfAccount ? 'COA '.$account->chartOfAccount->account_code.' - '.$account->chartOfAccount->account_name : null,
                    $account->is_default ? '(Default)' : null,
                ])->filter()->join(' - ')),
            ])
            ->values();
    }

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

    private function upsertVendorPaymentFinanceHubOutbox(VendorPayment $payment): void
    {
        $payload = $this->buildVendorPaymentFinanceHubPayload($payment);
        $encoded = json_encode($payload);
        $hash = hash('sha256', (string) $encoded);

        DB::table('integration_outbox')->updateOrInsert(
            ['idempotency_key' => $payload['idempotency_key']],
            [
                'event_type' => $payload['event_name'],
                'aggregate_type' => 'vendor_payment',
                'aggregate_id' => $payment->id,
                'payload_json' => $encoded,
                'payload_hash' => $hash,
                'status' => 'ready',
                'available_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function buildVendorPaymentFinanceHubPayload(VendorPayment $payment): array
    {
        $documentNo = (string) ($payment->payment_no ?: $payment->payment_number ?: ('VP-'.$payment->id));
        $postedAt = $payment->posted_at ?: now();
        $paymentDate = $payment->payment_date ? $payment->payment_date->toDateString() : now()->toDateString();
        $cashAccount = $payment->cashAccount;

        return [
            'event_name' => 'vendor.payment.posted',
            'event_datetime' => $postedAt->copy()->utc()->toJSON(),
            'idempotency_key' => 'VP-POSTED-'.$documentNo,
            'source_document_type' => 'vendor_payment',
            'source_document_id' => (string) $payment->id,
            'source_document_no' => $documentNo,
            'schema_version' => 'v1',
            'payload' => [
                'transaction_type' => $payment->payment_method === 'CASH' ? 'vendor.payment.cash' : 'vendor.payment.bank_transfer',
                'currency_code' => (string) ($payment->currency ?: $payment->currency_code ?: 'IDR'),
                'posting_date' => $paymentDate,
                'entry_date' => $postedAt->toDateString(),
                'reference_no' => $documentNo,
                'description' => 'Vendor Payment '.$documentNo,
                'cash_account_id' => $cashAccount?->id,
                'amounts' => [
                    'invoice_payment_total' => (float) ($payment->total_invoice_amount ?? 0),
                    'withholding_tax_total' => (float) ($payment->total_wht_amount ?? 0),
                    'stamp_duty' => (float) ($payment->stamp_duty_amount ?? 0),
                    'bank_charge' => (float) ($payment->bank_charge_amount ?? 0),
                    'freight' => (float) ($payment->freight_amount ?? 0),
                ],
                'invoice_lines' => $payment->lines->map(fn ($line) => [
                    'invoice_no' => (string) ($line->invoice_number ?: ('INV-'.$line->vendor_invoice_id)),
                    'payment_amount' => (float) ($line->payment_amount ?? 0),
                    'withholding_tax' => (float) ($line->wht_amount ?? 0),
                ])->values()->all(),
            ],
        ];
    }

    private function sendVendorPaymentFinanceHubEvent(int $paymentId): void
    {
        $outbox = DB::table('integration_outbox')
            ->where('aggregate_type', 'vendor_payment')
            ->where('aggregate_id', $paymentId)
            ->first();

        if (! $outbox) {
            return;
        }

        $eventsUrl = $this->vendorPaymentFinanceHubEventsUrl();
        $clientKey = config('services.finance_hub.client_key');
        $clientSecret = config('services.finance_hub.client_secret');

        if (! $eventsUrl || ! $clientKey || ! $clientSecret) {
            $this->markVendorPaymentFinanceHubOutboxFailed((int) $outbox->id, 'Konfigurasi Finance Hub Vendor Payment belum lengkap. Pastikan FINANCE_HUB_BASE_URL, FINANCE_HUB_CLIENT_KEY, dan FINANCE_HUB_CLIENT_SECRET tersedia.');
            return;
        }

        $payload = json_decode((string) $outbox->payload_json, true) ?: [];
        $payload['client_key'] = $clientKey;
        $payload['client_secret'] = $clientSecret;

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.finance_hub.timeout', 10))
                ->post($eventsUrl, $payload);

            if ($response->successful()) {
                DB::table('integration_outbox')->where('id', $outbox->id)->update([
                    'status' => 'sent',
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            $message = sprintf('Finance Hub HTTP %s: %s', $response->status(), mb_strimwidth($response->body(), 0, 500));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
        }

        $this->markVendorPaymentFinanceHubOutboxFailed((int) $outbox->id, $message);
    }

    private function vendorPaymentFinanceHubEventsUrl(): ?string
    {
        $configuredUrl = config('services.finance_hub.vendor_payment_events_url');
        if (is_string($configuredUrl) && trim($configuredUrl) !== '') {
            return trim($configuredUrl);
        }

        $baseUrl = config('services.finance_hub.base_url');
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            return rtrim(trim($baseUrl), '/').'/api/integrations/vendor-payments/events';
        }

        return null;
    }

    private function markVendorPaymentFinanceHubOutboxFailed(int $outboxId, string $message): void
    {
        DB::table('integration_outbox')->where('id', $outboxId)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => $message,
            'updated_at' => now(),
        ]);
    }

    private function documentTypes()
    {
        return DocumentType::query()->select(['id', 'name', 'code'])->orderBy('name')->get();
    }
    private function uploadedDocuments(VendorPayment $payment)
    {
        return Document::query()->where('owner_type', 'vendor_payment')->where('owner_id', $payment->id)->with('documentType:id,name,code')->latest()->get();
    }
    private function attachDocumentsToPayment(VendorPayment $payment, array $documents, StoreVendorPaymentRequest $request): int
    {
        $uploadedCount = 0;
        foreach ($documents as $index => $document) {
            $file = $request->file("documents.$index.file");
            $documentTypeId = $document['document_type_id'] ?? null;
            if (! $file || ! $documentTypeId) continue;
            $path = $file->store('documents/vendor-payments', 'public');
            Document::create([
                'business_id' => auth()->user()?->company_id ?? 1,
                'owner_type' => 'vendor_payment',
                'owner_id' => $payment->id,
                'document_type_id' => $documentTypeId,
                'title' => $document['title'] ?: ('Payment Document #'.$payment->payment_no),
                'document_number' => $document['document_number'] ?? null,
                'issue_date' => $document['issue_date'] ?? null,
                'expiry_date' => $document['expiry_date'] ?? null,
                'notes' => $document['notes'] ?? null,
                'file_path' => $path,
                'storage_disk' => 'public',
                'original_file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
                'status' => 'pending_review',
            ]);
            $uploadedCount++;
        }
        return $uploadedCount;
    }
}
