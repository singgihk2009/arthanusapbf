<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemRequest;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemBarcode;
use App\Models\Inventory\ItemPicture;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItemSetting;
use App\Models\Regulatory\ItemRegulatoryProduct;
use App\Models\Regulatory\RegulatoryProduct;
use App\Services\Inventory\ItemPictureService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ItemController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemPictureService $itemPictureService)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:master-item-data', only: ['index', 'exportExcel', 'downloadTemplateExcel']),
            new Middleware('permission:master-item-create', only: ['create', 'store', 'importExcel']),
            new Middleware('permission:master-item-update', only: ['edit', 'update']),
            new Middleware('permission:master-item-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $filters = [
            'search_item' => trim((string) $request->string('search_item')->toString()),
            'search_category' => trim((string) $request->string('search_category')->toString()),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_dir' => strtolower($request->input('sort_dir', 'desc')),
        ];

        if (! in_array($filters['sort_by'], ['sku', 'name', 'category_name', 'created_at'], true)) {
            $filters['sort_by'] = 'created_at';
        }

        if (! in_array($filters['sort_dir'], ['asc', 'desc'], true)) {
            $filters['sort_dir'] = 'desc';
        }

        $items = $this->baseItemQuery($filters)
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/Items/Index', [
            'items' => $items,
            'filters' => $filters,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $filters = [
            'search_item' => trim((string) $request->string('search_item')->toString()),
            'search_category' => trim((string) $request->string('search_category')->toString()),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_dir' => strtolower($request->input('sort_dir', 'desc')),
        ];

        if (! in_array($filters['sort_by'], ['sku', 'name', 'category_name', 'created_at'], true)) {
            $filters['sort_by'] = 'created_at';
        }

        if (! in_array($filters['sort_dir'], ['asc', 'desc'], true)) {
            $filters['sort_dir'] = 'desc';
        }

        $rows = $this->baseItemQuery($filters)->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['SKU', 'NIE', 'Nama', 'Kategori', 'Base UOM', 'Minimum Stok', 'Jumlah Foto', 'Status']);

            foreach ($rows as $item) {
                fputcsv($output, [
                    $item->sku,
                    $item->nie,
                    $item->name,
                    $item->category?->name ?? '-',
                    $item->baseUom?->code ?? '-',
                    (float) ($item->minimum_stock_base ?? 0),
                    (int) ($item->pictures_count ?? 0),
                    $item->is_active ? 'Aktif' : 'Nonaktif',
                ]);
            }

            fclose($output);
        }, 'master-items-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadTemplateExcel()
    {
        $rows = [
            ['sku', 'nie', 'name', 'category_name', 'base_uom_code', 'default_barcode', 'track_expired', 'is_active', 'warehouse_code', 'min_stock_base'],
            ['SKU-001', 'NIE-001', 'Contoh Item', 'MED-OTC', 'PCS', '8990011223344', '0', '1', 'WH-UTAMA', '10'],
        ];

        $tempPath = storage_path('app/master-item-template-'.now()->format('YmdHis').'.xlsx');
        $this->buildTemplateXlsx($tempPath, $rows);

        return response()->download($tempPath, 'master-item-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt'],
        ]);

        $rows = $this->parseImportRows($request->file('file'));
        $requiredHeaders = ['sku', 'name', 'base_uom_code'];

        if ($rows->isNotEmpty() && ! $this->hasRequiredHeaders($rows->first(), $requiredHeaders)) {
            return response()->json([
                'message' => 'Format header file import tidak valid. Gunakan template agar kolom sesuai: '.implode(', ', $requiredHeaders).'.',
            ], 422);
        }

        $errors = [];
        $inserted = 0;
        $updated = 0;

        DB::beginTransaction();

        foreach ($rows as $index => $row) {
            if ($this->isRowEmpty($row)) {
                continue;
            }

            try {
                $data = validator($row, [
                    'sku' => ['required', 'string', 'max:100'],
                    'name' => ['required', 'string', 'max:255'],
                    'nie' => ['nullable', 'string', 'max:255'],
                    'category_code' => ['nullable', 'string', 'max:100'],
                    'category_name' => ['nullable', 'string', 'max:255'],
                    'category' => ['nullable', 'string', 'max:255'],
                    'base_uom_code' => ['required', 'string', 'max:100'],
                    'default_barcode' => ['nullable', 'string', 'max:100'],
                    'track_expired' => ['nullable'],
                    'is_active' => ['nullable'],
                    'warehouse_code' => ['nullable', 'string', 'max:100'],
                    'min_stock_base' => ['nullable', 'numeric', 'min:0'],
                ])->validate();

                $categoryInput = $data['category_code'] ?? $data['category_name'] ?? $data['category'] ?? null;
                $categoryId = $this->resolveCategoryId($categoryInput);

                $baseUomId = Uom::query()->where('code', $data['base_uom_code'])->value('id');
                if (! $baseUomId) {
                    throw new \RuntimeException('Base UOM tidak ditemukan: '.$data['base_uom_code']);
                }

                $item = Item::query()->updateOrCreate(
                    ['sku' => $data['sku']],
                    [
                        'name' => $data['name'],
                        'nie' => $data['nie'] ?? null,
                        'category_id' => $categoryId,
                        'base_uom_id' => $baseUomId,
                        'track_expired' => $this->toBoolean($data['track_expired'] ?? null),
                        'is_active' => $this->toBoolean($data['is_active'] ?? null, true),
                    ]
                );

                if ($item->wasRecentlyCreated) {
                    $inserted++;
                } else {
                    $updated++;
                }

                $this->syncDefaultBarcode($item, $data['default_barcode'] ?? null);

                if (! empty($data['warehouse_code']) && ($data['min_stock_base'] ?? null) !== null && $data['min_stock_base'] !== '') {
                    $warehouseId = Warehouse::query()->where('code', $data['warehouse_code'])->value('id');
                    if (! $warehouseId) {
                        throw new \RuntimeException('Gudang tidak ditemukan: '.$data['warehouse_code']);
                    }

                    $this->syncMinimumStock($item, $warehouseId, $data['min_stock_base']);
                }
            } catch (\Throwable $exception) {
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if (! empty($errors)) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        if (! empty($errors)) {
            return response()->json([
                'message' => 'Import item gagal, periksa data file.',
                'errors' => $errors,
            ], 422);
        }

        if (($inserted + $updated) === 0) {
            return response()->json([
                'message' => 'Tidak ada data item yang diproses dari file import.',
            ], 422);
        }

        return response()->json([
            'message' => "Import item berhasil. {$inserted} item baru, {$updated} item diperbarui.",
        ]);
    }


    public function inventoryCard(Request $request, Item $item)
    {
        $item->load(['category:id,name', 'baseUom:id,code,name']);

        $currentTab = $request->string('tab')->toString() ?: 'overview';
        $allowedTabs = ['overview', 'item', 'barang-masuk', 'barang-keluar', 'dokumen', 'ledger'];
        if (! in_array($currentTab, $allowedTabs, true)) {
            $currentTab = 'overview';
        }

        $summary = [
            'on_hand_total' => (float) DB::table('stock_balances')->where('item_id', $item->id)->sum('on_hand_base'),
            'warehouse_count' => (int) DB::table('stock_balances')->where('item_id', $item->id)->distinct('warehouse_id')->count('warehouse_id'),
            'incoming_total' => (float) DB::table('stock_ledgers')->where('item_id', $item->id)->where('qty_base', '>', 0)->sum('qty_base'),
            'outgoing_total' => (float) DB::table('stock_ledgers')->where('item_id', $item->id)->where('qty_base', '<', 0)->sum(DB::raw('ABS(qty_base)')),
            'ledger_rows' => (int) DB::table('stock_ledgers')->where('item_id', $item->id)->count(),
        ];

        $ledgers = DB::table('stock_ledgers as sl')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->select('sl.id', 'sl.trx_type', 'sl.trx_id', 'sl.qty_base', 'sl.unit_cost', 'sl.trx_datetime', 'w.name as warehouse_name')
            ->where('sl.item_id', $item->id)
            ->orderByDesc('sl.trx_datetime')
            ->limit(50)
            ->get();

        return inertia('Apps/Inventory/Items/Show', [
            'item' => $item,
            'currentTab' => $currentTab,
            'summary' => $summary,
            'ledgers' => $ledgers,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/Items/Create', [
            'categories' => $this->categoryOptions(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
            'primaryRegulatoryProduct' => null,
        ]);
    }

    public function store(ItemRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $item = Item::query()->create(collect($validated)->except(['warehouse_id', 'min_stock_base', 'pictures', 'default_new_picture_index', 'default_picture_id', 'regulatory_product_id'])->all());

            $this->syncDefaultBarcode($item, $validated['default_barcode'] ?? null);
            $this->syncMinimumStock($item, $validated['warehouse_id'] ?? null, $validated['min_stock_base'] ?? null);
            $this->itemPictureService->upload($item, $request->file('pictures', []), $validated['default_new_picture_index'] ?? null);
            $this->syncRegulatoryReference($item, $validated['regulatory_product_id'] ?? null);
        });

        return to_route('apps.master-data.items.index');
    }

    public function edit(Item $item)
    {
        $item->load([
            'warehouseItemSettings' => fn ($query) => $query->latest()->limit(1),
            'pictures:id,item_id,path,disk,file_name,mime_type,size,is_default,created_at',
            'regulatoryProducts' => fn ($query) => $query->withPivot(['is_primary', 'source_name', 'source_code'])->with('source:id,source_name'),
        ]);

        return inertia('Apps/MasterData/Items/Edit', [
            'item' => $item,
            'categories' => $this->categoryOptions(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
            'minimumStockSetting' => $item->warehouseItemSettings->first(),
            'primaryRegulatoryProduct' => $item->regulatoryProducts->firstWhere('pivot.is_primary', true),
        ]);
    }

    public function update(ItemRequest $request, Item $item)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $item, $validated): void {
            $item->update(collect($validated)->except(['warehouse_id', 'min_stock_base', 'pictures', 'default_new_picture_index', 'default_picture_id', 'regulatory_product_id'])->all());

            $this->syncDefaultBarcode($item, $validated['default_barcode'] ?? null);
            $this->syncMinimumStock($item, $validated['warehouse_id'] ?? null, $validated['min_stock_base'] ?? null);
            $this->itemPictureService->upload($item, $request->file('pictures', []), $validated['default_new_picture_index'] ?? null);
            $this->syncRegulatoryReference($item, $validated['regulatory_product_id'] ?? null);

            if (! empty($validated['default_picture_id'])) {
                $picture = ItemPicture::query()
                    ->where('item_id', $item->id)
                    ->whereKey($validated['default_picture_id'])
                    ->first();

                if ($picture) {
                    $this->itemPictureService->setDefault($picture);
                }
            }
        });

        return back();
    }

    public function destroy(string $id): RedirectResponse
    {
        $ids = explode(',', $id);

        Item::query()->whereIn('id', $ids)->delete();

        return back();
    }


    private function categoryOptions(): Collection
    {
        $categories = Category::query()->select('id', 'name', 'parent_id')->orderBy('name')->get()->keyBy('id');

        return $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'hierarchy_name' => $this->buildCategoryHierarchyName($category, $categories),
            ])
            ->sortBy('hierarchy_name')
            ->values();
    }

    private function buildCategoryHierarchyName(Category $category, Collection $categories): string
    {
        $names = [];
        $current = $category;

        while ($current) {
            array_unshift($names, $current->name);

            if (! $current->parent_id) {
                break;
            }

            $current = $categories->get($current->parent_id);
        }

        return implode(' - ', $names);
    }

    private function syncDefaultBarcode(Item $item, ?string $barcode): void
    {
        if (! $barcode) {
            return;
        }

        ItemBarcode::query()->updateOrCreate(
            ['barcode' => $barcode],
            ['item_id' => $item->id, 'note' => 'Default barcode dari master item']
        );
    }

    private function syncMinimumStock(Item $item, mixed $warehouseId, mixed $minimumStock): void
    {
        if (! $warehouseId || $minimumStock === null || $minimumStock === '') {
            return;
        }

        WarehouseItemSetting::query()->updateOrCreate(
            [
                'warehouse_id' => $warehouseId,
                'item_id' => $item->id,
            ],
            [
                'min_stock_base' => $minimumStock,
            ]
        );
    }

    private function syncRegulatoryReference(Item $item, mixed $regulatoryProductId): void
    {
        if (! $regulatoryProductId) {
            return;
        }

        $product = RegulatoryProduct::query()->with('source:id,source_name')->find($regulatoryProductId);

        if (! $product) {
            return;
        }

        $attributes = [
            'is_primary' => true,
        ];

        if (Schema::hasColumn('item_regulatory_products', 'source_name')) {
            $attributes['source_name'] = $product->source?->source_name;
        }

        if (Schema::hasColumn('item_regulatory_products', 'source_code')) {
            $attributes['source_code'] = $product->source_code ?: $product->nie;
        }

        ItemRegulatoryProduct::query()->updateOrCreate(
            ['item_id' => $item->id, 'regulatory_product_id' => $product->id],
            $attributes
        );

        ItemRegulatoryProduct::query()
            ->where('item_id', $item->id)
            ->where('regulatory_product_id', '!=', $product->id)
            ->update(['is_primary' => false]);
    }

    private function baseItemQuery(array $filters)
    {
        $minimumStockSubquery = WarehouseItemSetting::query()
            ->select('item_id')
            ->selectRaw('COALESCE(SUM(min_stock_base), 0) as minimum_stock_base')
            ->groupBy('item_id');

        return Item::query()
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoinSub($minimumStockSubquery, 'warehouse_minimum_stocks', function (Builder $join): void {
                $join->on('warehouse_minimum_stocks.item_id', '=', 'items.id');
            })
            ->with(['baseUom:id,code,name', 'category:id,name', 'defaultPicture:id,item_id,path,disk,file_name,is_default'])
            ->withCount('pictures')
            ->select('items.*')
            ->selectRaw('COALESCE(warehouse_minimum_stocks.minimum_stock_base, 0) as minimum_stock_base')
            ->when($filters['search_item'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('items.name', 'like', '%'.$filters['search_item'].'%')
                        ->orWhere('items.sku', 'like', '%'.$filters['search_item'].'%');
                });
            })
            ->when($filters['search_category'] !== '', fn ($query) => $query->where('categories.name', 'like', '%'.$filters['search_category'].'%'))
            ->when($filters['sort_by'] === 'category_name', fn ($query) => $query->orderBy('categories.name', $filters['sort_dir']))
            ->when($filters['sort_by'] !== 'category_name', fn ($query) => $query->orderBy('items.'.$filters['sort_by'], $filters['sort_dir']))
            ->orderBy('items.id', 'desc');
    }


    private function resolveCategoryId(?string $categoryInput): ?int
    {
        if ($categoryInput === null || trim($categoryInput) === '') {
            return null;
        }

        $categoryInput = trim($categoryInput);

        if (Schema::hasColumn('categories', 'code')) {
            $categoryId = Category::query()->where('code', $categoryInput)->value('id');
            if ($categoryId) {
                return (int) $categoryId;
            }
        }

        $categoryId = Category::query()->where('name', $categoryInput)->value('id');
        if ($categoryId) {
            return (int) $categoryId;
        }

        throw new \RuntimeException('Kategori tidak ditemukan: '.$categoryInput);
    }

    private function parseImportRows(UploadedFile $file): Collection
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'xlsx' => $this->parseXlsxRows($file->getRealPath()),
            default => $this->parseCsvRows($file->getRealPath()),
        };
    }

    private function parseCsvRows(string $path): Collection
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return collect();
        }

        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if (! $header) {
                $header = array_map(fn ($item) => trim((string) $item), $data);
                continue;
            }

            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($data) {
                return [$key => trim((string) ($data[$index] ?? ''))];
            })->all();
        }

        fclose($handle);

        return collect($rows);
    }

    private function parseXlsxRows(string $path): Collection
    {
        $zip = new ZipArchive();
        $opened = $zip->open($path);

        if ($opened !== true) {
            return collect();
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared !== false && isset($shared->si)) {
                foreach ($shared->si as $si) {
                    $sharedStrings[] = isset($si->t) ? (string) $si->t : '';
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (! $sheetXml) {
            return collect();
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || ! isset($xml->sheetData->row)) {
            return collect();
        }

        $table = [];
        foreach ($xml->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $cellReference = (string) ($cell['r'] ?? '');
                preg_match('/^[A-Z]+/', $cellReference, $matches);
                $columnLetters = $matches[0] ?? '';
                $columnIndex = $this->columnLettersToIndex($columnLetters);
                $type = (string) ($cell['t'] ?? '');
                $value = '';

                if ($type === 's') {
                    $idx = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }

                if ($columnIndex >= 0) {
                    $line[$columnIndex] = trim($value);
                } else {
                    $line[] = trim($value);
                }
            }
            ksort($line);
            $table[] = $line;
        }

        if (count($table) < 2) {
            return collect();
        }

        $header = $table[0];
        $rows = [];

        foreach (array_slice($table, 1) as $line) {
            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($line) {
                return [$key => trim((string) ($line[$index] ?? ''))];
            })->all();
        }

        return collect($rows);
    }

    private function buildTemplateXlsx(string $path, array $rows): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';
            foreach ($row as $colIndex => $value) {
                $column = chr(65 + $colIndex);
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= "<c r=\"{$column}".($rowIndex + 1)."\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows .= "<row r=\"".($rowIndex + 1)."\">{$cellXml}</row>";
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function hasRequiredHeaders(array $row, array $requiredHeaders): bool
    {
        $headers = array_map(
            fn ($header) => strtolower(trim((string) $header)),
            array_keys($row)
        );

        foreach ($requiredHeaders as $requiredHeader) {
            if (! in_array(strtolower($requiredHeader), $headers, true)) {
                return false;
            }
        }

        return true;
    }

    private function toBoolean(mixed $value, bool $default = false): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'ya'], true);
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper(trim($letters));
        if ($letters === '') {
            return -1;
        }

        $index = 0;
        foreach (str_split($letters) as $char) {
            $ord = ord($char);
            if ($ord < 65 || $ord > 90) {
                return -1;
            }

            $index = ($index * 26) + ($ord - 64);
        }

        return $index - 1;
    }
}
