<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemPicture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemPictureService
{
    public const MAX_PICTURES_PER_ITEM = 6;

    public function upload(Item $item, array $files, ?int $defaultIndex = null): void
    {
        if ($files === []) {
            return;
        }

        $currentCount = $item->pictures()->count();
        if ($currentCount + count($files) > self::MAX_PICTURES_PER_ITEM) {
            throw ValidationException::withMessages([
                'pictures' => 'Maksimal 6 foto untuk setiap produk.',
            ]);
        }

        $disk = config('inventory.pictures_disk', 'public');

        DB::transaction(function () use ($item, $files, $defaultIndex, $disk): void {
            $createdPictures = [];

            /** @var UploadedFile $file */
            foreach ($files as $file) {
                $path = $file->store('item-pictures/'.$item->id, $disk);
                $createdPictures[] = $item->pictures()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize() ?? 0,
                    'is_default' => false,
                ]);
            }

            $targetDefault = null;
            if ($defaultIndex !== null && isset($createdPictures[$defaultIndex])) {
                $targetDefault = $createdPictures[$defaultIndex];
            }

            $hasDefault = $item->pictures()->where('is_default', true)->exists();
            if (! $hasDefault) {
                $targetDefault = $targetDefault ?? $createdPictures[0] ?? null;
            }

            if ($targetDefault) {
                $this->setDefault($targetDefault);
            }
        });
    }

    public function setDefault(ItemPicture $picture): void
    {
        DB::transaction(function () use ($picture): void {
            ItemPicture::query()
                ->where('item_id', $picture->item_id)
                ->update(['is_default' => false]);

            $picture->update(['is_default' => true]);
        });
    }

    public function delete(ItemPicture $picture): void
    {
        DB::transaction(function () use ($picture): void {
            $wasDefault = (bool) $picture->is_default;
            Storage::disk($picture->disk)->delete($picture->path);
            $itemId = $picture->item_id;
            $picture->delete();

            if (! $wasDefault) {
                return;
            }

            $newDefault = ItemPicture::query()
                ->where('item_id', $itemId)
                ->orderByDesc('created_at')
                ->first();

            if ($newDefault) {
                $this->setDefault($newDefault);
            }
        });
    }
}
