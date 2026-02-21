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
use App\Services\Inventory\ItemPictureService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemPictureService $itemPictureService)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:master-item-data', only: ['index', 'exportExcel']),
            new Middleware('permission:master-item-create', only: ['create', 'store']),
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
            fputcsv($output, ['SKU', 'Nama', 'Kategori', 'Base UOM', 'Minimum Stok', 'Jumlah Foto', 'Status']);

            foreach ($rows as $item) {
                fputcsv($output, [
                    $item->sku,
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

    public function create()
    {
        return inertia('Apps/MasterData/Items/Create', [
            'categories' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(ItemRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $item = Item::query()->create(collect($validated)->except(['warehouse_id', 'min_stock_base', 'pictures', 'default_new_picture_index', 'default_picture_id'])->all());

            $this->syncDefaultBarcode($item, $validated['default_barcode'] ?? null);
            $this->syncMinimumStock($item, $validated['warehouse_id'] ?? null, $validated['min_stock_base'] ?? null);
            $this->itemPictureService->upload($item, $request->file('pictures', []), $validated['default_new_picture_index'] ?? null);
        });

        return to_route('apps.master-data.items.index');
    }

    public function edit(Item $item)
    {
        $item->load([
            'warehouseItemSettings' => fn ($query) => $query->latest()->limit(1),
            'pictures:id,item_id,path,disk,file_name,mime_type,size,is_default,created_at',
        ]);

        return inertia('Apps/MasterData/Items/Edit', [
            'item' => $item,
            'categories' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
            'minimumStockSetting' => $item->warehouseItemSettings->first(),
        ]);
    }

    public function update(ItemRequest $request, Item $item)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $item, $validated): void {
            $item->update(collect($validated)->except(['warehouse_id', 'min_stock_base', 'pictures', 'default_new_picture_index', 'default_picture_id'])->all());

            $this->syncDefaultBarcode($item, $validated['default_barcode'] ?? null);
            $this->syncMinimumStock($item, $validated['warehouse_id'] ?? null, $validated['min_stock_base'] ?? null);
            $this->itemPictureService->upload($item, $request->file('pictures', []), $validated['default_new_picture_index'] ?? null);

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

        return to_route('apps.master-data.items.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        $ids = explode(',', $id);

        Item::query()->whereIn('id', $ids)->delete();

        return back();
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
}
