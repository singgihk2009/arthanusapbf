<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use App\Models\Sales\Customer;
use App\Models\Sales\PriceList;
use App\Services\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function __construct(private readonly PriceListService $service)
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

        return response()->json($this->service->resolvePrice(
            $data['item_id'],
            $data['qty'] ?? 1,
            $data['uom_id'] ?? null,
            $data['date'] ?? null,
            $data['price_list_id'] ?? null,
        ));
    }

    public function searchItems(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->canAny(['price-list.view', 'price-list.create', 'price-list.update']),
            403
        );

        $q = (string) $request->query('q', '');
        $query = Item::query()->select(['id', 'sku', 'name', 'base_uom_id'])
            ->with('baseUom:id,name')
            ->when(Schema::hasColumn('items', 'is_active'), fn ($x) => $x->where('is_active', true))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('sku', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->limit(20);

        return response()->json($query->get()->map(fn ($item) => [
            'id' => $item->id,
            'code' => $item->sku,
            'name' => $item->name,
            'uom_id' => $item->base_uom_id,
            'uom_name' => $item->baseUom?->name,
            'selling_price' => $item->selling_price ?? $item->sale_price ?? $item->price ?? $item->default_price ?? null,
        ]));
    }

    private function uomOptions(): array
    {
        if (! Schema::hasTable('uoms')) {
            return [];
        }

        return Uom::query()->select(['id', 'name'])->orderBy('name')->get()->toArray();
    }
}
