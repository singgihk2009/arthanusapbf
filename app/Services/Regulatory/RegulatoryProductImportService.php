<?php
namespace App\Services\Regulatory;
use App\Models\Regulatory\ProductComposition;
use App\Models\Regulatory\ProductPackaging;
use App\Models\Regulatory\RegulatoryProduct;
use App\Models\Regulatory\RegulatorySource;
class RegulatoryProductImportService {
    public function importBpom(string $path): int { return $this->importBpomDrug($path); }
    public function importKemenkes(string $path): int { return $this->importKemenkesDrug($path); }
    public function importBpomDrug(string $path): int { return $this->importCsv($path,'BPOM',',', fn($r)=>$this->normalizeDrugRow($r,'BPOM'), ','); }
    public function importKemenkesDrug(string $path): int { return $this->importCsv($path,'KEMENKES','|', fn($r)=>$this->normalizeDrugRow($r,'KEMENKES'), '|'); }
    public function importKemenkesAlkes(string $path): int { return $this->importCsv($path,'KEMENKES',',', fn($r)=>$this->normalizeAlkesRow($r), null); }
    private function importCsv(string $path,string $sourceName,string $separator, callable $map, ?string $compositionSeparator): int {
        $source=RegulatorySource::firstOrCreate(['source_name'=>$sourceName]); $h=fopen($path,'r'); $headers=fgetcsv($h,0,$separator); $count=0;
        while(($row=fgetcsv($h,0,$separator))!==false){$assoc=array_combine($headers,$row); $data=$map($assoc); if(empty($data['nie'])) continue; $data['source_id']=$source->id; $data['raw_payload']=$assoc; $p=RegulatoryProduct::updateOrCreate($this->identityFor($data),$data); if($compositionSeparator){ $p->compositions()->delete(); foreach($this->parseCompositions((string)($data['raw_composition_text']??''),$compositionSeparator) as $sub){ ProductComposition::create(['regulatory_product_id'=>$p->id,'substance_name'=>$sub]); }} $p->packagings()->delete(); ProductPackaging::create(['regulatory_product_id'=>$p->id,'description_raw'=>$data['raw_packaging_text']??null]+$this->parsePackaging((string)($data['raw_packaging_text']??''))); $count++; }
        fclose($h); return $count;
    }
    public function normalizeDrugRow(array $r, string $source): array { $nie=RegulatoryProduct::normalizeNie($r['NIE']??null); return ['product_type'=>RegulatoryProduct::TYPE_DRUG,'nie'=>$nie,'source_code'=>$this->cleanHtmlText($r['Kode Obat Jadi'] ?? $r['Kode Obat Ja'] ?? $r['source_code'] ?? $r['KODE OBAT JADI'] ?? $nie),'product_name_source'=>$this->cleanHtmlText($r['Nama Obat Jadi'] ?? $r['NAMA PRODUK'] ?? ''),'industry_name'=>$this->cleanHtmlText($r['Produsen'] ?? $r['NAMA INDUSTRI'] ?? null),'dosage_form'=>$r['Sediaan'] ?? null,'strength'=>$r['Kekuatan'] ?? null,'commodity_type'=>$r['Jenis Komoditi'] ?? null,'raw_packaging_text'=>$this->cleanHtmlText($r['Kemasan'] ?? $r['PRODUK KEMASAN'] ?? null),'raw_composition_text'=>$this->cleanHtmlText($r['Bahan Obat'] ?? $r['KOMPOSISI'] ?? null)]; }
    public function normalizeAlkesRow(array $r): array {
        $nie = RegulatoryProduct::normalizeNie($r['nie'] ?? ($r['NOMOR'] ?? null));
        $brand = $this->cleanHtmlText($r['brand'] ?? ($r['MERK'] ?? null));
        $licenseType = strtoupper(trim((string)($r['license_type'] ?? '')));

        return [
            'product_type' => RegulatoryProduct::TYPE_MEDICAL_DEVICE,
            'nie' => $nie,
            'source_code' => $this->cleanHtmlText($r['source_code'] ?? ($r['TIPE'] ?? null)) ?: $nie,
            'license_type' => $licenseType !== '' ? $licenseType : $this->detectLicenseType($nie),
            'registration_date' => $this->normalizeDate($r['registration_date'] ?? ($r['TGL TERBIT'] ?? null)),
            'expiry_date' => $this->normalizeDate($r['expiry_date'] ?? ($r['TGL EXP'] ?? null)),
            'brand' => $brand,
            'product_name_source' => $this->cleanHtmlText($r['product_name_source'] ?? $brand),
            'sub_category' => $r['sub_category'] ?? ($r['SUB KATEGORI'] ?? null),
            'device_type' => $r['device_type'] ?? ($r['JENIS PRODUK'] ?? null),
            'product_group' => $r['product_group'] ?? ($r['KELOMPOK PRODUK'] ?? null),
            'model_type' => $r['model_type'] ?? ($r['TIPE'] ?? null),
            'device_class' => $r['device_class'] ?? ($r['KELAS'] ?? null),
            'risk_class' => $r['risk_class'] ?? ($r['KELAS RESIKO'] ?? null),
            'registrant_name' => $r['registrant_name'] ?? ($r['PENDAFTAR'] ?? null),
            'registrant_address' => $r['registrant_address'] ?? ($r['ALAMAT PENDAFTAR'] ?? null),
            'manufacturer_name' => $r['manufacturer_name'] ?? ($r['PABRIK'] ?? null),
            'manufacturer_address' => $r['manufacturer_address'] ?? ($r['ALAMAT PABRIK'] ?? null),
            'manufacturer_name_2' => $r['manufacturer_name_2'] ?? ($r['PABRIK2'] ?? null),
        ];
    }
    private function identityFor(array $data): array { return ['source_id'=>$data['source_id'],'product_type'=>$data['product_type'] ?? RegulatoryProduct::TYPE_DRUG,'nie'=>$data['nie'],'source_code'=>$data['source_code'] ?? $data['nie']]; }
    public function parseCompositions(?string $text,string $separator=','): array { return array_values(array_filter(array_map('trim',explode($separator,(string)$text)))); }
    public function parsePackaging(?string $text): array { return ['packaging_type'=>trim((string)$text)?:null]; }
    public function cleanHtmlText($value): ?string { if($value===null) return null; $v=strip_tags((string)$value); $v=preg_replace('/\s+/', ' ', $v); return trim($v) ?: null; }
    public function normalizeDate($value): ?string { if(empty($value)) return null; if(is_numeric($value)){ return now()->startOfDay()->addDays((int)$value-25569)->format('Y-m-d'); } $ts=strtotime((string)$value); return $ts ? date('Y-m-d',$ts) : null; }
    public function detectLicenseType($nomor): ?string { $n=RegulatoryProduct::normalizeNie((string)$nomor); return str_starts_with($n,'AKD') ? 'AKD' : (str_starts_with($n,'AKL') ? 'AKL' : null); }
}
