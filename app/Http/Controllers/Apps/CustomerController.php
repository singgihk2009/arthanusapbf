<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\DocumentType;
use App\Models\Sales\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search')->toString());
        $status = $request->string('status')->toString();

        $customers = Customer::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('customer_code', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('npwp', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('Apps/Sales/Customers/Index', ['customers' => $customers, 'filters' => compact('search', 'status')]);
    }


    public function downloadTemplateExcel()
    {
        $rows = [
            ['customer_code', 'customer_name', 'contact_person', 'phone', 'email', 'address', 'city', 'province', 'postal_code', 'npwp', 'payment_term_days', 'credit_limit', 'status'],
            ['CUST-000001', 'PT Contoh Customer', 'Budi', '08123456789', 'customer@example.com', 'Jl. Contoh No. 1', 'Jakarta', 'DKI Jakarta', '12345', '01.234.567.8-999.000', 30, 5000000, 'active'],
        ];

        $tempPath = storage_path('app/customer-master-template-'.now()->format('YmdHis').'.xlsx');
        $this->buildTemplateXlsx($tempPath, $rows);

        return response()->download($tempPath, 'customer-master-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt']]);
        $rows = $this->parseImportRows($request->file('file'));
        $requiredHeaders = ['customer_code', 'customer_name'];

        if ($rows->isNotEmpty() && ! $this->hasRequiredHeaders($rows->first(), $requiredHeaders)) {
            return back()->withErrors(['import' => 'Header tidak valid. Gunakan template impor customer.']);
        }

        foreach ($rows as $row) {
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $customerCode = trim((string) ($row['customer_code'] ?? ''));
            if ($customerCode === '') {
                continue;
            }

            $payload = [
                'customer_name' => $row['customer_name'] ?? $customerCode,
                'contact_person' => $row['contact_person'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'address' => $row['address'] ?? null,
                'city' => $row['city'] ?? null,
                'province' => $row['province'] ?? null,
                'postal_code' => $row['postal_code'] ?? null,
                'npwp' => $row['npwp'] ?? null,
                'payment_term_days' => (int) ($row['payment_term_days'] ?? 0),
                'credit_limit' => (float) ($row['credit_limit'] ?? 0),
                'status' => in_array(strtolower((string) ($row['status'] ?? 'active')), ['active', 'inactive'], true)
                    ? strtolower((string) $row['status'])
                    : 'active',
                'country' => $row['country'] ?? 'Indonesia',
            ];

            if (Schema::hasColumn('customers', 'code')) {
                $payload['code'] = $customerCode;
            }

            if (Schema::hasColumn('customers', 'name')) {
                $payload['name'] = $payload['customer_name'];
            }

            Customer::query()->updateOrCreate(
                ['customer_code' => $customerCode],
                $payload
            );
        }

        return back()->with('success', 'Import customer berhasil diproses.');
    }

    public function exportExcel()
    {
        $rows = [[
            'customer_code', 'customer_name', 'contact_person', 'phone', 'email', 'address', 'city', 'province', 'postal_code', 'npwp', 'payment_term_days', 'credit_limit', 'status',
        ]];

        foreach (Customer::query()->orderBy('customer_code')->get() as $customer) {
            $rows[] = [
                (string) ($customer->customer_code ?? ''),
                (string) ($customer->customer_name ?? ''),
                (string) ($customer->contact_person ?? ''),
                (string) ($customer->phone ?? ''),
                (string) ($customer->email ?? ''),
                (string) ($customer->address ?? ''),
                (string) ($customer->city ?? ''),
                (string) ($customer->province ?? ''),
                (string) ($customer->postal_code ?? ''),
                (string) ($customer->npwp ?? ''),
                (string) ($customer->payment_term_days ?? 0),
                (string) ($customer->credit_limit ?? 0),
                (string) ($customer->status ?? 'active'),
            ];
        }

        $tempPath = storage_path('app/customer-export-'.now()->format('YmdHis').'.xlsx');
        $this->buildTemplateXlsx($tempPath, $rows);

        return response()->download($tempPath, 'customer-export.xlsx')->deleteFileAfterSend(true);
    }

    public function create()
    {
        return Inertia::render('Apps/Sales/Customers/Form', ['customer' => null]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        if (blank($data['customer_code'] ?? null)) {
            $data['customer_code'] = $this->nextCustomerCode();
        }
        if (Schema::hasColumn('customers', 'code') && blank($data['code'] ?? null)) {
            $data['code'] = $data['customer_code'];
        }
        if (Schema::hasColumn('customers', 'name') && blank($data['name'] ?? null)) {
            $data['name'] = $data['customer_name'] ?? null;
        }
        $data['country'] = $data['country'] ?? 'Indonesia';
        $data['payment_term_days'] = $data['payment_term_days'] ?? 0;
        $data['credit_limit'] = $data['credit_limit'] ?? 0;

        $customer = Customer::create($data);

        return redirect()->route('apps.customers.show', $customer)->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['documents.documentType']);

        $salesOrders = Schema::hasTable('sales') ? $customer->salesOrders()?->with('warehouse:id,name','priceList:id,name')->withCount('lines')->latest()->get() : collect();
        $dispatches = Schema::hasTable('internal_usages')
            ? DB::table('internal_usages as iu')
                ->leftJoin('warehouses as w', 'w.id', '=', 'iu.warehouse_id')
                ->when(Schema::hasTable('customer_invoice_dispatches'), function ($query): void {
                    $query->leftJoin('customer_invoice_dispatches as cid', 'cid.internal_usage_id', '=', 'iu.id')
                        ->leftJoin('customer_invoices as ci', function ($join): void {
                            $join->on('ci.id', '=', 'cid.customer_invoice_id')->where('ci.status', '!=', 'cancelled');
                        });
                })
                ->where('iu.customer_id', $customer->id)
                ->groupBy('iu.id', 'iu.number', 'iu.document_date', 'iu.department', 'iu.cost_center', 'iu.status', 'iu.sale_id', 'iu.source_type', 'iu.source_id', 'iu.source_number', 'w.name')
                ->orderByDesc('iu.id')
                ->get([
                    'iu.id',
                    'iu.number',
                    'iu.document_date',
                    'iu.department',
                    'iu.cost_center',
                    'iu.status',
                    'iu.sale_id',
                    'iu.source_type',
                    'iu.source_id',
                    'iu.source_number',
                    DB::raw('COALESCE(w.name, "-") as warehouse_label'),
                    DB::raw(Schema::hasTable('customer_invoice_dispatches') ? 'MAX(ci.id) as invoice_id' : 'NULL as invoice_id'),
                    DB::raw(Schema::hasTable('customer_invoice_dispatches') ? 'MAX(ci.number) as invoice_number' : 'NULL as invoice_number'),
                ])
            : collect();

        $customerInvoices = Schema::hasTable('customer_invoices')
            ? DB::table('customer_invoices')
                ->where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->get([
                    'id',
                    'number',
                    'invoice_date',
                    'due_date',
                    'status',
                    'subtotal',
                    'discount_total',
                    'tax_total',
                    DB::raw(Schema::hasColumn('customer_invoices', 'freight_amount') ? 'freight_amount' : '0 as freight_amount'),
                    'grand_total',
                    'amount_paid',
                    'balance_due',
                ])
            : collect();

        $customerPayments = Schema::hasTable('customer_payments')
            ? DB::table('customer_payments')
                ->where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->get([
                    'id',
                    'number',
                    'payment_date',
                    'payment_method',
                    'amount',
                    'bank_charge',
                    'discount_taken',
                    DB::raw(Schema::hasColumn('customer_payments', 'wht_amount') ? 'wht_amount' : '0 as wht_amount'),
                    DB::raw(Schema::hasColumn('customer_payments', 'other_deduction_amount') ? 'other_deduction_amount' : '0 as other_deduction_amount'),
                    DB::raw(Schema::hasColumn('customer_payments', 'gross_settlement_amount') ? 'gross_settlement_amount' : '(amount + discount_taken) as gross_settlement_amount'),
                    'status',
                ])
            : collect();

        return Inertia::render('Apps/Sales/Customers/Show', [
            'customer' => $customer,
            'salesOrders' => $salesOrders,
            'dispatches' => $dispatches,
            'customerInvoices' => $customerInvoices,
            'customerPayments' => $customerPayments,
            'summary' => [
                'total_sales_orders' => Schema::hasTable('sales') ? $customer->salesOrders()?->count() ?? 0 : 0,
                'total_invoices' => $customerInvoices->count(),
                'total_payments' => $customerPayments->count(),
                'outstanding_balance' => $customerInvoices->sum(fn ($invoice): float => (float) ($invoice->balance_due ?? 0)),
            ],
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Apps/Sales/Customers/Form', ['customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $data['country'] = $data['country'] ?? 'Indonesia';
        $customer->update($data);

        return redirect()->route('apps.customers.show', $customer)->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        if ((Schema::hasTable('sales') && ($customer->salesOrders()?->exists())) ||
            (Schema::hasTable('customer_invoices') && ($customer->invoices()?->exists())) ||
            (Schema::hasTable('customer_payments') && ($customer->payments()?->exists()))) {
            return back()->withErrors(['delete' => 'Customer cannot be deleted because it already has transactions.']);
        }

        $customer->delete();
        return redirect()->route('apps.customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->string('q')->toString());
        $rows = Customer::query()->where('status', 'active')
            ->when($q !== '', fn ($query) => $query->where(fn ($s) => $s->where('customer_code', 'like', "%{$q}%")->orWhere('customer_name', 'like', "%{$q}%")))
            ->limit(20)
            ->get(['id', 'customer_code', 'customer_name', 'phone', 'city', 'payment_term_days', 'credit_limit', 'price_list_id']);

        return response()->json($rows);
    }

    private function nextCustomerCode(): string
    {
        return DB::transaction(function () {
            $last = Customer::query()->lockForUpdate()->orderByDesc('id')->first();
            $lastNumber = $last ? (int) preg_replace('/\D/', '', (string) $last->customer_code) : 0;
            return 'CUST-'.str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
        });
    }

    private function parseImportRows(UploadedFile $file): Collection
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'csv' || $ext === 'txt') {
            $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            if (count($lines) === 0) return collect();
            $headers = array_map(fn ($h) => trim(strtolower((string) $h)), str_getcsv(array_shift($lines)));
            return collect($lines)->map(function ($line) use ($headers) {
                $cols = str_getcsv((string) $line);
                $row = [];
                foreach ($headers as $i => $h) $row[$h] = isset($cols[$i]) ? trim((string) $cols[$i]) : null;
                return $row;
            });
        }

        return $this->parseSimpleXlsx($file->getRealPath());
    }

    private function parseSimpleXlsx(string $path): Collection
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return collect();
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    $text = '';
                    if (isset($si->t)) $text = (string) $si->t;
                    elseif (isset($si->r)) foreach ($si->r as $r) $text .= (string) ($r->t ?? '');
                    $shared[] = $text;
                }
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) return collect();
        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet || ! isset($sheet->sheetData->row)) return collect();
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $data = [];
            foreach ($row->c as $c) {
                $r = (string) ($c['r'] ?? '');
                $col = preg_replace('/\d+/', '', $r);
                $val = '';
                if ((string) ($c['t'] ?? '') === 's') {
                    $idx = (int) ($c->v ?? -1);
                    $val = $shared[$idx] ?? '';
                } else $val = (string) ($c->v ?? '');
                $data[$col] = trim($val);
            }
            $rows[] = $data;
        }
        if (count($rows) === 0) return collect();
        $letters = range('A', 'Z');
        $headerRaw = $rows[0];
        $headers = [];
        foreach ($letters as $l) {
            if (array_key_exists($l, $headerRaw)) $headers[$l] = trim(strtolower((string) $headerRaw[$l]));
        }
        return collect(array_slice($rows,1))->map(function($r) use ($headers){
            $out=[];
            foreach($headers as $l=>$h){ if($h==='') continue; $out[$h]=array_key_exists($l,$r)?trim((string)$r[$l]):null; }
            return $out;
        });
    }

    private function buildTemplateXlsx(string $path, array $rows): void
    {
        $csvPath = preg_replace('/\.xlsx$/', '.csv', $path) ?: ($path.'.csv');
        $fh = fopen($csvPath, 'w');
        foreach ($rows as $r) fputcsv($fh, $r);
        fclose($fh);

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $sheetData = '';
            foreach ($rows as $ri => $cols) {
                $rowNum = $ri + 1;
                $sheetData .= '<row r="'.$rowNum.'">';
                foreach (array_values($cols) as $ci => $value) {
                    $col = chr(65 + $ci);
                    $cellRef = $col.$rowNum;
                    $escaped = htmlspecialchars((string) $value, ENT_XML1);
                    $sheetData .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
                }
                $sheetData .= '</row>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetData.'</sheetData></worksheet>');
            $zip->close();
        }
        @unlink($csvPath);
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') return false;
        }

        return true;
    }

    private function hasRequiredHeaders(array $row, array $requiredHeaders): bool
    {
        return collect($requiredHeaders)->every(fn ($header) => array_key_exists($header, $row));
    }

}
