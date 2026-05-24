<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use App\Models\Sales\Customer;
use App\Models\Sales\PriceList;
use App\Services\Inventory\InventoryAvailabilityService;
use App\Services\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function __construct(private readonly PriceListService $service, private readonly InventoryAvailabilityService $availabilityService)
    {
    }

    public function index(Request $request): Response
    {
        $query = PriceList::query()->withCount('lines')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($x) => $x->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('is_default'), fn ($q) => $q->where('is_default', $request->boolean('is_default')))
            ->latest();

        return Inertia::render('Apps/Sales/PriceLists/Index', [
            'priceLists' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'is_default']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Apps/Sales/PriceLists/Form', [
            'priceList' => null,
            'uoms' => $this->uomOptions(),
        ]);
    }

    public function store(StorePriceListRequest $request)
    {
        $this->service->savePriceList($request->validated());
        return redirect()->route('apps.price-lists.index')->with('success', 'Price list created successfully.');
    }

    public function show(PriceList $priceList): Response
    {
        $priceList->load(['lines.item.baseUom', 'lines.uom']);
        return Inertia::render('Apps/Sales/PriceLists/Show', [
            'priceList' => $priceList,
            'summary' => [
                'total_lines' => $priceList->lines->count(),
                'active_lines' => $priceList->lines->where('status', 'active')->count(),
                'effective_period' => trim(($priceList->effective_from?->toDateString() ?? '-').' to '.($priceList->effective_to?->toDateString() ?? '-')),
                'is_default' => $priceList->is_default,
            ],
        ]);
    }

    public function edit(PriceList $priceList): Response
    {
        return Inertia::render('Apps/Sales/PriceLists/Form', [
            'priceList' => $priceList->load(['lines.item.baseUom', 'lines.uom']),
            'uoms' => $this->uomOptions(),
        ]);
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList)
    {
        $this->service->updatePriceList($priceList, $request->validated());
        return redirect()->route('apps.price-lists.index')->with('success', 'Price list updated successfully.');
    }

    public function destroy(PriceList $priceList)
    {
        if (Schema::hasTable('customers') && Customer::query()->where('price_list_id', $priceList->id)->exists()) {
            return back()->withErrors(['delete' => 'Cannot delete: already used by customer.']);
        }

        $priceList->delete();
        return redirect()->route('apps.price-lists.index')->with('success', 'Price list deleted successfully.');
    }

    public function resolvePrice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'qty' => ['nullable', 'numeric', 'min:0.0001'],
            'uom_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'price_list_id' => ['nullable', 'integer'],
        ]);

        $resolved = $this->service->resolvePrice($data['item_id'],$data['qty'] ?? 1,$data['uom_id'] ?? null,$data['date'] ?? null,$data['price_list_id'] ?? null);
        if (($resolved['source'] ?? null) === 'none') { $resolved['message'] = 'No price found for this item.'; }
        return response()->json($resolved);
    }

    public function searchItems(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->canAny([
                'price-list.view', 'price-list.create', 'price-list.update',
                'sales-order.create', 'sales-order.update', 'sales-order.view',
                'item.view', 'item.create', 'item.update',
            ]),
            403
        );

        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'mode' => ['nullable', 'in:auto,barcode,sku,name'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));
        $mode = $data['mode'] ?? 'auto';
        $limit = min((int) ($data['limit'] ?? 20), 50);
        $barcodeLike = (bool) preg_match('/^[0-9]{8,}$/', $q);

        if ($mode !== 'barcode' && mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $baseQuery = Item::query()
            ->select(['items.id', 'items.sku', 'items.name', 'items.base_uom_id', 'items.default_barcode'])
            ->with('baseUom:id,name')
            ->when(Schema::hasColumn('items', 'is_active'), fn ($x) => $x->where('is_active', true));

        if ($mode === 'barcode' || ($mode === 'auto' && $barcodeLike)) {
            $exactBarcode = (clone $baseQuery)
                ->where(function ($x) use ($q) {
                    $x->where('default_barcode', $q)
                        ->orWhereExists(function ($b) use ($q) {
                            $b->selectRaw('1')->from('item_barcodes as ib')->whereColumn('ib.item_id', 'items.id')->where('ib.barcode', $q);
                        });
                })
                ->limit(1)
                ->get();

            if ($exactBarcode->isNotEmpty()) {
                return response()->json($this->mapSearchItems($exactBarcode, $data['warehouse_id'] ?? null));
            }

            if ($mode === 'barcode') {
                return response()->json([]);
            }
        }

        $rows = (clone $baseQuery)
            ->where(function ($x) use ($q) {
                $x->where('sku', 'like', "{$q}%")
                    ->orWhere('sku', $q)
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('default_barcode', 'like', "{$q}%")
                    ->orWhere('default_barcode', $q);
            })
            ->orderByRaw("CASE
                WHEN default_barcode = ? THEN 1
                WHEN sku = ? THEN 2
                WHEN sku LIKE ? THEN 4
                WHEN name LIKE ? THEN 6
                ELSE 7 END", [$q, $q, "{$q}%", "%{$q}%"])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json($this->mapSearchItems($rows, $data['warehouse_id'] ?? null));
    }

    private function mapSearchItems($rows, ?int $warehouseId): array
    {
        return $rows->map(function ($item) use ($warehouseId) {
            $stock = $this->availabilityService->getAvailableStock((int) $item->id, $warehouseId ?: null);

            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'barcode' => $item->default_barcode,
                // Mapping note: this project uses items.sku as code and items.name as product name.
                'code' => $item->sku,
                'name' => $item->name,
                'uom_id' => $item->base_uom_id,
                'uom_name' => $item->baseUom?->name,
                'selling_price' => $item->selling_price ?? $item->sale_price ?? $item->price ?? $item->default_price ?? null,
                'available_stock' => $stock,
                'stock_status' => $this->availabilityService->stockStatus($stock),
                'cogs' => (float) (DB::table('stock_balances')->where('item_id', $item->id)->when($warehouseId, fn ($q, $w) => $q->where('warehouse_id', $w))->value('avg_cost') ?? 0),
            ];
        })->values()->all();
    }

    private function uomOptions(): array
    {
        if (! Schema::hasTable('uoms')) {
            return [];
        }

        return Uom::query()->select(['id', 'name'])->orderBy('name')->get()->toArray();
    }
}
