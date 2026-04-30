<?php
namespace App\Services\Regulatory;
use App\Models\Inventory\Item;
use App\Models\Regulatory\RegulatoryProduct;
class ProductMatchingService {
    public function candidates(RegulatoryProduct $rp, int $limit = 10): array {
        return Item::query()->limit(200)->get()->map(function($item) use($rp){
            similar_text(strtolower($item->name), strtolower($rp->product_name_source), $name);
            $score=$name*0.7;
            if($rp->dosage_form && str_contains(strtolower((string)$item->dosage_form), strtolower((string)$rp->dosage_form))) $score+=10;
            if($rp->strength && str_contains(strtolower((string)$item->strength), strtolower((string)$rp->strength))) $score+=10;
            return ['item_id'=>$item->id,'sku'=>$item->sku,'name'=>$item->name,'confidence_score'=>round(min(100,$score),2)];
        })->sortByDesc('confidence_score')->take($limit)->values()->all();
    }
}
