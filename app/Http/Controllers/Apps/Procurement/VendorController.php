<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorRequest;
use App\Http\Requests\Procurement\UpdateVendorRequest;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Vendor;
use App\Models\Procurement\DocumentType;
use App\Models\Document;
use App\Services\VendorComplianceService;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorLedger;
use App\Models\Procurement\VendorPayment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use ZipArchive;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search')->toString());
        $vendors = Vendor::query()->with('documents')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('vendor_code', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhere('vendor_type', 'like', "%{$search}%")
                        ->orWhere('qualification_status', 'like', "%{$search}%");
                });
            })
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->when(request('qualification_status'), fn ($q, $v) => $q->where('qualification_status', $v))
            ->when(request('vendor_type'), fn ($q, $v) => $q->where('vendor_type', $v))
            ->when(request('city'), fn ($q, $v) => $q->where('city', $v))
            ->latest()->paginate(10)->withQueryString();
        return Inertia::render('Apps/Procurement/Vendors/Index', [
            'vendors' => $vendors,
            'filters' => ['search' => $search],
        ]);
    }
    public function downloadTemplateExcel()
    {
        $rows = [
            ['vendor_code', 'vendor_name', 'vendor_type', 'qualification_status', 'status', 'address', 'city', 'province', 'postal_code', 'phone', 'email'],
            ['VND-001', 'PT Contoh Vendor', 'Distributor', 'draft', 'ACTIVE', 'Jl. Contoh 123', 'Jakarta', 'DKI Jakarta', '12345', '021123456', 'vendor@example.com'],
        ];
        $tempPath = storage_path('app/vendor-master-template-'.now()->format('YmdHis').'.xlsx');
        $this->buildTemplateXlsx($tempPath, $rows);
        return response()->download($tempPath, 'vendor-master-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt']]);
        $rows = $this->parseImportRows($request->file('file'));
        $requiredHeaders = ['vendor_code', 'vendor_name'];
        if ($rows->isNotEmpty() && ! $this->hasRequiredHeaders($rows->first(), $requiredHeaders)) {
            return back()->withErrors(['import' => 'Header tidak valid. Gunakan template impor vendor.']);
        }
        foreach ($rows as $row) {
            if ($this->isRowEmpty($row)) continue;
            Vendor::query()->updateOrCreate(
                ['vendor_code' => $row['vendor_code']],
                [
                    'vendor_name' => $row['vendor_name'] ?? null,
                    'name' => $row['vendor_name'] ?? $row['vendor_code'],
                    'vendor_type' => $row['vendor_type'] ?? null,
                    'qualification_status' => $row['qualification_status'] ?? 'draft',
                    'status' => $this->normalizeStatus($row['status'] ?? 'ACTIVE'),
                    'address' => $row['address'] ?? null,
                    'city' => $row['city'] ?? null,
                    'province' => $row['province'] ?? null,
                    'postal_code' => $row['postal_code'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'email' => $row['email'] ?? null,
                    'updated_by' => auth()->id(),
                ]
            );
        }
        return back()->with('success', 'Import vendor berhasil diproses.');
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['documents.documentType']);
        $compliance = app(VendorComplianceService::class)->evaluate($vendor);
        $compliance['required_documents'] = app(VendorComplianceService::class)->getRequiredDocuments($vendor);
        $vendor->setAttribute('compliance', $compliance);
        $currentTab = request('tab', 'overview');

        return Inertia::render('Apps/Procurement/Vendors/Show', [
            'vendor' => $vendor,
            'currentTab' => $currentTab,
            'summary' => $this->summary($vendor),
            'documentTypes' => DocumentType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'category']),
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

    public function legal(Vendor $vendor)
    {
        $documents = $vendor->documents()->with('documentType')->get()->keyBy('document_type_id');
        $requirements = DocumentType::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->whereRaw('LOWER(category) = ?', ['regulatory'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function ($req) use ($documents) {
                $document = $documents->get($req->id);
                return [
                    'requirement_id' => $req->id,
                    'document_type_id' => $req->id,
                    'document_type_name' => $req->name ?? $req->code ?? '-',
                    'category' => $req->category ?? null,
                    'is_requested' => true,
                    'verification_status' => $document?->status ?? 'belum upload',
                    'document_number' => $document->document_number ?? null,
                    'issue_date' => $document->issue_date ?? null,
                    'expiry_date' => $document->expiry_date ?? null,
                    'original_file_name' => $document->original_filename ?? null,
                ];
            });

        return response()->json(['documents' => $requirements]);
    }
    public function contacts(Vendor $vendor) {
        if ($vendor->party_id && $vendor->party) {
            return response()->json(['contacts' => $vendor->party->partyContacts()->with('contact.user')->where('status', 'active')->latest()->get()]);
        }
        return response()->json(['contacts' => $vendor->contacts()->orderBy('contact_type')->get()]);
    }
    public function documents(Vendor $vendor) {
        $requirements = app(VendorComplianceService::class)->getRequiredDocuments($vendor);
        return response()->json(['documents' => $vendor->documents()->with('documentType')->latest()->get(), 'requirements'=>$requirements]);
    }
    public function purchaseOrders(Vendor $vendor) { return response()->json(['purchase_orders' => PurchaseOrder::where('vendor_id', $vendor->id)->latest('document_date')->paginate(10)]); }
    public function receivings(Vendor $vendor) { return response()->json(['receivings' => GoodsReceipt::where('supplier_id', $vendor->id)->latest('document_date')->paginate(10)]); }
    public function invoices(Vendor $vendor) { return response()->json(['invoices' => VendorInvoice::where('vendor_id', $vendor->id)->latest('invoice_date')->paginate(10)]); }
    public function payments(Vendor $vendor) { return response()->json(['payments' => VendorPayment::where('vendor_id', $vendor->id)->latest('payment_date')->paginate(10)]); }
    public function ledger(Vendor $vendor) { return response()->json(['ledger' => VendorLedger::where('vendor_id', $vendor->id)->latest('transaction_date')->paginate(10)]); }
    public function auditLogs(Vendor $vendor) { return response()->json(['audit_logs' => [['action' => 'Vendor created/updated', 'at' => $vendor->updated_at, 'by' => $vendor->updated_by]]]); }

    public function create(){ return Inertia::render('Apps/Procurement/Vendors/Form'); }
    public function store(StoreVendorRequest $request){ DB::transaction(function () use ($request) { $validated = $request->validated(); $vendorPayload = Arr::except($validated, ['company_director', 'technical_responsible_person', 'documents']); $vendorPayload['status'] = $this->normalizeStatus($vendorPayload['status'] ?? null); $vendorPayload['name'] = $vendorPayload['vendor_name'] ?? ($vendorPayload['name'] ?? $vendorPayload['vendor_code'] ?? 'UNKNOWN'); $vendor = Vendor::query()->create(array_merge($vendorPayload, ['created_by' => auth()->id(), 'updated_by' => auth()->id()])); $this->upsertContacts($vendor, $validated); }); return redirect('/apps/procurement/vendors'); }
    public function edit(Vendor $vendor){ $vendor->load('contacts', 'documents'); return Inertia::render('Apps/Procurement/Vendors/Form', compact('vendor')); }
    public function update(UpdateVendorRequest $request, Vendor $vendor){ DB::transaction(function () use ($request, $vendor) { $validated = $request->validated(); $vendorPayload = Arr::except($validated, ['company_director', 'technical_responsible_person', 'documents']); $vendorPayload['status'] = $this->normalizeStatus($vendorPayload['status'] ?? null); $vendorPayload['name'] = $vendorPayload['vendor_name'] ?? ($vendorPayload['name'] ?? $vendorPayload['vendor_code'] ?? 'UNKNOWN'); $vendor->update(array_merge($vendorPayload, ['updated_by' => auth()->id()])); $this->upsertContacts($vendor, $validated); }); return redirect('/apps/procurement/vendors'); }

    public function submitQualification(Vendor $vendor){ $vendor->update(['qualification_status' => 'submitted', 'submitted_by' => auth()->id(), 'submitted_at' => now()]); return back(); }
    public function approveQualification(Vendor $vendor){ $invalid = $vendor->documents()->whereIn('status', ['rejected'])->exists(); if ($invalid) return back()->withErrors(['qualification' => 'Dokumen invalid / need revision harus diselesaikan.']); $vendor->update(['qualification_status' => 'qualified', 'qualification_date' => now(), 'approved_by' => auth()->id(), 'approved_at' => now()]); return back(); }
    public function rejectQualification(Request $request, Vendor $vendor){ $vendor->update(['qualification_status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now(), 'notes' => $request->input('notes')]); return back(); }

    public function uploadDocument(Request $request, Vendor $vendor)
    {
        $data = $request->validate(['document_type_id' => ['nullable','exists:document_types,id'], 'document_type' => ['nullable','string'], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], 'document_number' => ['nullable'], 'issue_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date']]);
        $path = $request->file('file')->store("private/vendor-documents/{$vendor->id}");
        $docTypeId = $data['document_type_id'] ?? null;
        if (!$docTypeId && !empty($data['document_type'])) {
            $docType = DocumentType::firstOrCreate(['code' => strtoupper($data['document_type'])], ['name' => strtoupper($data['document_type'])]);
            $docTypeId = $docType->id;
        }
        $originalFileName = $request->file('file')->getClientOriginalName();
        $docTypeName = !empty($data['document_type'])
            ? strtoupper($data['document_type'])
            : ($docTypeId ? DocumentType::query()->whereKey($docTypeId)->value('name') : null);
        $title = $docTypeName ?: pathinfo($originalFileName, PATHINFO_FILENAME);

        Document::updateOrCreate(['owner_type' => 'vendor', 'owner_id' => $vendor->id, 'document_type_id' => $docTypeId], ['title' => $title, 'file_path' => $path, 'original_file_name' => $originalFileName, 'mime_type' => $request->file('file')->getClientMimeType(), 'file_size' => $request->file('file')->getSize(), 'document_number' => $data['document_number'] ?? null, 'issue_date' => $data['issue_date'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null, 'uploaded_by' => auth()->id()]);
        return back();
    }

    public function deleteDocument(Vendor $vendor, Document $document){ if ($document->file_path) Storage::delete($document->file_path); $document->delete(); return back(); }
    public function verifyDocument(Request $request, Vendor $vendor, Document $document){ $request->validate(['verification_status' => ['required', 'in:pending,valid,invalid,expired,need_revision']]); $document->update(['status' => $request->verification_status === 'valid' ? 'verified' : ($request->verification_status === 'invalid' ? 'rejected' : 'pending_review'), 'verified_by' => auth()->id(), 'verified_at' => now(), 'notes' => $request->notes]); return back(); }
    public function downloadDocument(Vendor $vendor, Document $document){ return Storage::download($document->file_path, $document->original_file_name ?? 'document'); }
    public function qualificationReport(){ $vendors = Vendor::with(['technicalResponsiblePerson', 'documents'])->get(); return Inertia::render('Apps/Procurement/Vendors/QualificationReport', compact('vendors')); }
    public function destroy(string $id){ $ids = explode(',', $id); Vendor::query()->whereIn('id', $ids)->delete(); return back(); }

    protected function upsertContacts(Vendor $vendor, array $data): void
    {
        foreach (['company_director', 'technical_responsible_person'] as $type) {
            if (!isset($data[$type]) || !is_array($data[$type])) continue;

            $contact = $data[$type];
            if (empty($contact['name'])) continue;

            $vendor->contacts()->updateOrCreate(
                ['contact_type' => $type],
                array_merge($contact, ['contact_type' => $type, 'updated_by' => auth()->id(), 'created_by' => auth()->id()])
            );
        }
    }

    protected function normalizeStatus(?string $status): string
    {
        return in_array(strtolower((string) $status), ['active', 'prospect'], true) ? 'ACTIVE' : 'INACTIVE';
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

    private function parseImportRows(UploadedFile $file): Collection
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        return $extension === 'xlsx' ? $this->parseXlsxRows($file->getRealPath()) : $this->parseCsvRows($file->getRealPath());
    }
    private function parseCsvRows(string $path): Collection
    {
        $handle = fopen($path, 'r'); if (! $handle) return collect();
        $headers = null; $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) { $headers = array_map(fn ($v) => strtolower(trim((string) $v)), $data); continue; }
            if ($headers === []) continue;
            $rows[] = collect($headers)->mapWithKeys(fn ($h, $i) => [$h => isset($data[$i]) ? trim((string) $data[$i]) : null])->all();
        }
        fclose($handle); return collect($rows);
    }
    private function parseXlsxRows(string $path): Collection
    {
        $zip = new ZipArchive(); if ($zip->open($path) !== true) return collect();
        $sharedStrings = []; $shared = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared) { $xml = simplexml_load_string($shared); if ($xml) foreach ($xml->si as $si) $sharedStrings[] = trim((string) ($si->t ?? '')); }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml'); if (! $sheet) { $zip->close(); return collect(); }
        $sheetXml = simplexml_load_string($sheet); if (! $sheetXml) { $zip->close(); return collect(); }
        $sheetXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($sheetXml->xpath('//main:sheetData/main:row') as $rowNode) {
            $row = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r']; $col=''; foreach (str_split($ref) as $ch) { if (ctype_alpha($ch)) $col.=$ch; else break; }
                $idx = 0; foreach (str_split($col) as $ch) $idx = ($idx * 26) + (ord($ch) - 64); $idx--;
                $val = (string) ($cell->v ?? ''); if ((string) $cell['t'] === 's') $val = $sharedStrings[(int) $val] ?? ''; $row[$idx] = trim($val);
            } ksort($row); $rows[] = array_values($row);
        } $zip->close();
        if ($rows === []) return collect();
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]); $data = [];
        foreach (array_slice($rows, 1) as $r) $data[] = collect($headers)->mapWithKeys(fn ($h, $i) => [$h => $r[$i] ?? null])->all();
        return collect($data);
    }
    private function isRowEmpty(array $row): bool { foreach ($row as $v) if ($v !== null && trim((string) $v) !== '') return false; return true; }
    private function hasRequiredHeaders(array $row, array $requiredHeaders): bool { return collect($requiredHeaders)->every(fn ($h) => array_key_exists($h, $row)); }
    private function buildTemplateXlsx(string $path, array $rows): void
    {
        $escape = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1);
        $sheetRows = [];
        foreach ($rows as $ri => $row) { $cells = []; foreach (array_values($row) as $ci => $v) { $col=''; $n=$ci+1; while($n>0){$m=($n-1)%26;$col=chr(65+$m).$col;$n=intdiv($n-1,26);} $cells[] = '<c r="'.$col.($ri+1).'" t="inlineStr"><is><t>'.$escape($v).'</t></is></c>'; } $sheetRows[]='<row r="'.($ri+1).'">'.implode('', $cells).'</row>'; }
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>';
        $zip = new ZipArchive(); $zip->open($path, ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml); $zip->close();
    }
}
