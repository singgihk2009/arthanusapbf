<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorRequest;
use App\Http\Requests\Procurement\UpdateVendorRequest;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Vendor;
use App\Models\Procurement\VendorDocument;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorLedger;
use App\Models\Procurement\VendorPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::query()->with('documents')
            ->when(request('search'), fn ($q, $s) => $q->where('vendor_code', 'like', "%{$s}%")->orWhere('vendor_name', 'like', "%{$s}%"))
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->when(request('qualification_status'), fn ($q, $v) => $q->where('qualification_status', $v))
            ->when(request('vendor_type'), fn ($q, $v) => $q->where('vendor_type', $v))
            ->when(request('city'), fn ($q, $v) => $q->where('city', $v))
            ->latest()->paginate(10)->withQueryString();
        return Inertia::render('Apps/Procurement/Vendors/Index', compact('vendors'));
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['documents:id,vendor_id,document_type,expiry_date,verification_status']);
        $currentTab = request('tab', 'overview');

        return Inertia::render('Apps/Procurement/Vendors/Show', [
            'vendor' => $vendor,
            'currentTab' => $currentTab,
            'summary' => $this->summary($vendor),
        ]);
    }

    public function overview(Vendor $vendor)
    {
        return response()->json([
            'financial_summary' => $this->summary($vendor),
            'recent_purchase_orders' => PurchaseOrder::where('vendor_id', $vendor->id)->latest('document_date')->limit(5)->get(),
            'recent_invoices' => VendorInvoice::where('vendor_id', $vendor->id)->latest('invoice_date')->limit(5)->get(),
            'recent_payments' => VendorPayment::where('vendor_id', $vendor->id)->latest('payment_date')->limit(5)->get(),
        ]);
    }

    public function profile(Vendor $vendor) { return response()->json(['vendor' => $vendor]); }

    public function updateProfile(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_type' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'village' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'nib_number' => ['nullable', 'string', 'max:100'],
            'company_license_number' => ['nullable', 'string', 'max:100'],
            'cdakb_cpakb_certificate_number' => ['nullable', 'string', 'max:100'],
        ]);

        $vendor->update(array_merge($data, ['updated_by' => auth()->id()]));

        return back();
    }

    public function deleteProfile(Vendor $vendor)
    {
        $vendor->update([
            'vendor_name' => null,
            'vendor_type' => null,
            'address' => null,
            'postal_code' => null,
            'village' => null,
            'district' => null,
            'city' => null,
            'province' => null,
            'phone' => null,
            'fax' => null,
            'email' => null,
            'npwp' => null,
            'nib_number' => null,
            'company_license_number' => null,
            'cdakb_cpakb_certificate_number' => null,
            'updated_by' => auth()->id(),
        ]);

        return back();
    }

    public function legal(Vendor $vendor) { return response()->json(['documents' => $vendor->documents()->get()]); }
    public function contacts(Vendor $vendor) { return response()->json(['contacts' => $vendor->contacts()->orderBy('contact_type')->get()->groupBy('contact_type')]); }
    public function documents(Vendor $vendor) { return response()->json(['documents' => $vendor->documents()->latest()->get()]); }
    public function purchaseOrders(Vendor $vendor) { return response()->json(['purchase_orders' => PurchaseOrder::where('vendor_id', $vendor->id)->latest('document_date')->paginate(10)]); }
    public function receivings(Vendor $vendor) { return response()->json(['receivings' => GoodsReceipt::where('supplier_id', $vendor->id)->latest('document_date')->paginate(10)]); }
    public function invoices(Vendor $vendor) { return response()->json(['invoices' => VendorInvoice::where('vendor_id', $vendor->id)->latest('invoice_date')->paginate(10)]); }
    public function payments(Vendor $vendor) { return response()->json(['payments' => VendorPayment::where('vendor_id', $vendor->id)->latest('payment_date')->paginate(10)]); }
    public function ledger(Vendor $vendor) { return response()->json(['ledger' => VendorLedger::where('vendor_id', $vendor->id)->latest('transaction_date')->paginate(10)]); }
    public function auditLogs(Vendor $vendor) { return response()->json(['audit_logs' => [['action' => 'Vendor created/updated', 'at' => $vendor->updated_at, 'by' => $vendor->updated_by]]]); }

    public function create(){ return Inertia::render('Apps/Procurement/Vendors/Form'); }
    public function store(StoreVendorRequest $request){ DB::transaction(function () use ($request) { $vendor = Vendor::query()->create(array_merge($request->validated(), ['created_by' => auth()->id(), 'updated_by' => auth()->id()])); $this->upsertContacts($vendor, $request->validated()); }); return to_route('apps.procurement.vendors.index'); }
    public function edit(Vendor $vendor){ $vendor->load('contacts', 'documents'); return Inertia::render('Apps/Procurement/Vendors/Form', compact('vendor')); }
    public function update(UpdateVendorRequest $request, Vendor $vendor){ DB::transaction(function () use ($request, $vendor) { $vendor->update(array_merge($request->validated(), ['updated_by' => auth()->id()])); $this->upsertContacts($vendor, $request->validated()); }); return to_route('apps.procurement.vendors.index'); }

    public function submitQualification(Vendor $vendor){ $vendor->update(['qualification_status' => 'submitted', 'submitted_by' => auth()->id(), 'submitted_at' => now()]); return back(); }
    public function approveQualification(Vendor $vendor){ $invalid = $vendor->documents()->whereIn('verification_status', ['invalid', 'need_revision'])->exists(); if ($invalid) return back()->withErrors(['qualification' => 'Dokumen invalid / need revision harus diselesaikan.']); $vendor->update(['qualification_status' => 'qualified', 'qualification_date' => now(), 'approved_by' => auth()->id(), 'approved_at' => now()]); return back(); }
    public function rejectQualification(Request $request, Vendor $vendor){ $vendor->update(['qualification_status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now(), 'notes' => $request->input('notes')]); return back(); }

    public function uploadDocument(Request $request, Vendor $vendor)
    {
        $data = $request->validate(['document_type' => ['required'], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], 'document_number' => ['nullable'], 'issue_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date']]);
        $path = $request->file('file')->store("private/vendor-documents/{$vendor->id}");
        VendorDocument::updateOrCreate(['vendor_id' => $vendor->id, 'document_type' => $data['document_type']], ['file_path' => $path, 'original_filename' => $request->file('file')->getClientOriginalName(), 'mime_type' => $request->file('file')->getClientMimeType(), 'file_size' => $request->file('file')->getSize(), 'document_number' => $data['document_number'] ?? null, 'issue_date' => $data['issue_date'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        return back();
    }

    public function deleteDocument(Vendor $vendor, VendorDocument $document){ if ($document->file_path) Storage::delete($document->file_path); $document->delete(); return back(); }
    public function verifyDocument(Request $request, Vendor $vendor, VendorDocument $document){ $request->validate(['verification_status' => ['required', 'in:pending,valid,invalid,expired,need_revision']]); $document->update(['verification_status' => $request->verification_status, 'verified_by' => auth()->id(), 'verified_at' => now(), 'notes' => $request->notes]); return back(); }
    public function downloadDocument(Vendor $vendor, VendorDocument $document){ return Storage::download($document->file_path, $document->original_filename); }
    public function qualificationReport(){ $vendors = Vendor::with(['technicalResponsiblePerson', 'documents'])->get(); return Inertia::render('Apps/Procurement/Vendors/QualificationReport', compact('vendors')); }
    public function destroy(string $id){ $ids = explode(',', $id); Vendor::query()->whereIn('id', $ids)->delete(); return back(); }

    protected function upsertContacts(Vendor $vendor, array $data): void
    {
        foreach (['company_director', 'technical_responsible_person'] as $type) {
            if (!isset($data[$type])) continue;
            $vendor->contacts()->updateOrCreate(['contact_type' => $type], array_merge($data[$type], ['contact_type' => $type, 'updated_by' => auth()->id(), 'created_by' => auth()->id()]));
        }
    }

    protected function summary(Vendor $vendor): array
    {
        return [
            'outstanding_ap' => VendorInvoice::where('vendor_id', $vendor->id)->sum('outstanding_amount'),
            'total_purchase_ytd' => PurchaseOrder::where('vendor_id', $vendor->id)->whereYear('document_date', now()->year)->sum('grand_total'),
            'last_purchase_date' => PurchaseOrder::where('vendor_id', $vendor->id)->max('document_date'),
            'open_po' => PurchaseOrder::where('vendor_id', $vendor->id)->whereIn('status', ['DRAFT','APPROVED','PARTIALLY_RECEIVED','SENT'])->count(),
            'unpaid_invoice' => VendorInvoice::where('vendor_id', $vendor->id)->whereIn('status', ['POSTED','PARTIAL_PAID'])->count(),
            'expiring_documents' => $vendor->documents()->whereDate('expiry_date', '<=', now()->addDays(30))->count(),
        ];
    }
}
