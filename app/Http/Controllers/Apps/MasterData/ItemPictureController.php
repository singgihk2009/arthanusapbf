<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemPictureDefaultRequest;
use App\Http\Requests\MasterData\ItemPictureUploadRequest;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemPicture;
use App\Services\Inventory\ItemPictureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ItemPictureController extends Controller
{
    public function __construct(private readonly ItemPictureService $itemPictureService)
    {
    }

    public function index(Request $request)
    {
        $selectedItemId = (int) $request->integer('item_id');

        $items = Item::query()
            ->with('defaultPicture:id,item_id,path,disk,file_name,is_default')
            ->withCount('pictures')
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);

        $selectedItem = null;
        if ($selectedItemId > 0) {
            $selectedItem = Item::query()
                ->with('pictures:id,item_id,path,disk,file_name,mime_type,size,is_default,created_at')
                ->find($selectedItemId);
        }

        return inertia('Apps/MasterData/Pictures/Index', [
            'items' => $items,
            'selectedItem' => $selectedItem,
        ]);
    }

    public function store(ItemPictureUploadRequest $request, Item $item): RedirectResponse
    {
        $validated = $request->validated();

        $this->itemPictureService->upload(
            $item,
            $request->file('pictures', []),
            $validated['default_new_picture_index'] ?? null
        );

        return back();
    }

    public function setDefault(ItemPictureDefaultRequest $request, Item $item): RedirectResponse
    {
        $picture = ItemPicture::query()
            ->where('item_id', $item->id)
            ->whereKey($request->integer('picture_id'))
            ->firstOrFail();

        $this->itemPictureService->setDefault($picture);

        return back();
    }

    public function destroy(Item $item, ItemPicture $picture): RedirectResponse
    {
        abort_unless($picture->item_id === $item->id, 404);

        $this->itemPictureService->delete($picture);

        return back();
    }
}
