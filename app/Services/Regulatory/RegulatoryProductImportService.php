<?php
namespace App\Services\Regulatory;
use App\Models\Regulatory\ProductComposition;
use App\Models\Regulatory\ProductPackaging;
use App\Models\Regulatory\RegulatoryProduct;
use App\Models\Regulatory\RegulatorySource;
class RegulatoryProductImportService {
    public function importBpom(string $path): int { return $this->importCsv($path,'BPOM',',', fn($r)=>['nie'=>$r['NIE']??null,'product_name_source'=>$r['Nama Obat Jadi']??'','industry_name'=>$r['Produsen']??null,'dosage_form'=>$r['Sediaan']??null,'strength'=>$r['Kekuatan']??null,'commodity_type'=>$r['Jenis Komoditi']??null,'raw_packaging_text'=>$r['Kemasan']??null,'raw_composition_text'=>$r['Bahan Obat']??null]); }
    public function importKemenkes(string $path): int { return $this->importCsv($path,'KEMENKES','|', fn($r)=>['nie'=>$r['NIE']??null,'product_name_source'=>$r['NAMA PRODUK']??'','industry_name'=>$r['NAMA INDUSTRI']??null,'dosage_form'=>$r['BENTUK SEDIAAN']??null,'strength'=>$r['KEKUATAN']??null,'commodity_type'=>$r['JENIS KOMODITI']??null,'raw_packaging_text'=>$r['PRODUK KEMASAN']??null,'raw_composition_text'=>$r['KOMPOSISI']??null]); }
    private function importCsv(string $path,string $sourceName,string $separator, callable $map): int {
        $source=RegulatorySource::where('source_name',$sourceName)->firstOrFail(); $h=fopen($path,'r'); $headers=fgetcsv($h); $count=0;
        while(($row=fgetcsv($h))!==false){$assoc=array_combine($headers,$row); $data=$map($assoc); if(empty($data['nie'])||empty($data['product_name_source'])) continue; $data['source_id']=$source->id; $data['raw_payload']=$assoc; $p=RegulatoryProduct::updateOrCreate(['source_id'=>$source->id,'nie'=>$data['nie']],$data); $p->compositions()->delete(); foreach($this->parseCompositions((string)($data['raw_composition_text']??''),$separator) as $sub){ ProductComposition::create(['regulatory_product_id'=>$p->id,'substance_name'=>$sub]); } $p->packagings()->delete(); ProductPackaging::create(['regulatory_product_id'=>$p->id,'description_raw'=>$data['raw_packaging_text']??null]+$this->parsePackaging((string)($data['raw_packaging_text']??''))); $count++; }
        fclose($h); return $count;
    }
    public function parseCompositions(?string $text,string $separator=','): array { return array_values(array_filter(array_map('trim',explode($separator,(string)$text)))); }
    public function parsePackaging(?string $text): array { return ['packaging_type'=>trim((string)$text)?:null]; }
}
