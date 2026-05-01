<?php
namespace App\Http\Controllers\Apps\MasterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemRegulatoryMappingRequest;
use App\Http\Requests\MasterData\RegulatoryProductRequest;
use App\Models\Inventory\Item;
use App\Models\Regulatory\ItemRegulatoryProduct;
use App\Models\Regulatory\RegulatoryProduct;
use App\Models\Regulatory\RegulatorySource;
use App\Services\Regulatory\ProductMatchingService;
use App\Services\Regulatory\RegulatoryProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;
class RegulatoryProductController extends Controller {
 public function index(Request $request){
  if($request->boolean('download_template')){return $this->downloadTemplateExcel();}
  $q=trim((string)$request->get('q'));
  $source=$request->string('source')->toString();
  $commodityType=$request->string('commodity_type')->toString();
  $dosageForm=$request->string('dosage_form')->toString();
  $producer=$request->string('producer')->toString();
  $perPage=(int)$request->integer('per_page',10);
  $perPage=in_array($perPage,[10,25,50,100],true)?$perPage:10;
  $shouldSearch=mb_strlen($q)>=3;

  $items=RegulatoryProduct::query()
   ->with('source')
   ->when($source!=='', fn($x)=>$x->whereHas('source', fn($s)=>$s->where('source_name',$source)))
   ->when($commodityType!=='', fn($x)=>$x->where('commodity_type',$commodityType))
   ->when($dosageForm!=='', fn($x)=>$x->where('dosage_form',$dosageForm))
   ->when($producer!=='', fn($x)=>$x->where('industry_name',$producer))
   ->when($shouldSearch,function($x) use ($q){
    $driver=config('database.default');
    $isMysql=in_array(config("database.connections.{$driver}.driver"),['mysql','mariadb'],true);
    if($isMysql && $this->hasRegulatoryProductFulltextIndex()){
      $x->where(function($query) use ($q){
       $query->whereRaw("MATCH(product_name_source,industry_name,raw_composition_text) AGAINST (? IN BOOLEAN MODE)",[$q.'*'])
        ->orWhere('nie','like',"%$q%");
      });
      return;
    }
    $x->where(function($query) use ($q){
      $query->where('nie','like',"%$q%")
        ->orWhere('product_name_source','like',"%$q%")
        ->orWhere('industry_name','like',"%$q%")
        ->orWhere('raw_composition_text','like',"%$q%");
    });
   })
   ->paginate($perPage)->withQueryString();

  return inertia('Apps/MasterData/RegulatoryProducts/Index',[
    'products'=>$items,
    'filters'=>['q'=>$q,'source'=>$source,'commodity_type'=>$commodityType,'dosage_form'=>$dosageForm,'producer'=>$producer,'per_page'=>$perPage],
    'filterOptions'=>[
      'sources'=>RegulatorySource::query()->orderBy('source_name')->pluck('source_name'),
      'commodity_types'=>RegulatoryProduct::query()->whereNotNull('commodity_type')->where('commodity_type','!=','')->distinct()->orderBy('commodity_type')->pluck('commodity_type'),
      'dosage_forms'=>RegulatoryProduct::query()->whereNotNull('dosage_form')->where('dosage_form','!=','')->distinct()->orderBy('dosage_form')->pluck('dosage_form'),
      'producers'=>RegulatoryProduct::query()->whereNotNull('industry_name')->where('industry_name','!=','')->distinct()->orderBy('industry_name')->pluck('industry_name'),
    ],
  ]);
 }

 public function search(Request $request): JsonResponse {
  $validated=$request->validate([
    'q'=>['required','string','min:3'],
    'source_name'=>['nullable','in:BPOM,KEMENKES'],
    'limit'=>['nullable','integer','min:1','max:50'],
  ]);
  $q=trim($validated['q']);
  $limit=(int)($validated['limit'] ?? 20);
  $items=RegulatoryProduct::query()
    ->select('regulatory_products.id','regulatory_products.source_code','regulatory_products.nie','regulatory_products.product_name_source','regulatory_products.industry_name','regulatory_products.dosage_form','regulatory_products.strength','regulatory_products.commodity_type','regulatory_products.raw_packaging_text','regulatory_products.raw_composition_text','regulatory_sources.source_name')
    ->join('regulatory_sources','regulatory_sources.id','=','regulatory_products.source_id')
    ->when(!empty($validated['source_name']),fn($x)=>$x->where('regulatory_sources.source_name',$validated['source_name']))
    ->where(function($query) use ($q){
      $query->where('regulatory_products.source_code','like',$q.'%')
        ->orWhere('regulatory_products.nie','like',$q.'%')
        ->orWhere('regulatory_products.product_name_source','like','%'.$q.'%')
        ->orWhere('regulatory_products.industry_name','like','%'.$q.'%')
        ->orWhere('regulatory_products.raw_composition_text','like','%'.$q.'%');
    })
    ->limit($limit)
    ->get()
    ->map(fn($row)=>[
      'id'=>$row->id,
      'source_name'=>$row->source_name,
      'source_code'=>$row->source_code ?: $row->nie,
      'product_name_source'=>$row->product_name_source,
      'industry_name'=>$row->industry_name,
      'dosage_form'=>$row->dosage_form,
      'strength'=>$row->strength,
      'commodity_type'=>$row->commodity_type,
      'raw_packaging_text'=>$row->raw_packaging_text,
      'raw_composition_text'=>$row->raw_composition_text,
    ]);

  return response()->json(['data'=>$items]);
 }
 public function exportExcel(Request $request): StreamedResponse {
  $q=trim((string)$request->get('q'));
  $rows=RegulatoryProduct::with('source')->when($q,fn($x)=>$x->where('nie','like',"%$q%")->orWhere('product_name_source','like',"%$q%"))->orderBy('id')->get();
  return response()->streamDownload(function() use ($rows): void {
   $output=fopen('php://output','w');
   fputcsv($output,['Source','NIE','Nama Produk','Produsen','Kemasan','Kekuatan','Jenis Komoditi','Packing','Bahan Obat']);
   foreach($rows as $product){
    fputcsv($output,[
     $product->source?->source_name ?? '-',
     $product->nie,
     $product->product_name_source,
     $product->industry_name,
     $product->dosage_form,
     $product->strength,
     $product->commodity_type,
     $product->raw_packaging_text,
     $product->raw_composition_text,
    ]);
   }
   fclose($output);
  },'master-regulatory-products-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
 } 
 public function create(){return inertia('Apps/MasterData/RegulatoryProducts/Create',['sources'=>RegulatorySource::all()]);}
 public function store(RegulatoryProductRequest $request){RegulatoryProduct::create($request->validated());return to_route('apps.master-data.regulatory-products.index');}
 public function edit(RegulatoryProduct $regulatoryProduct){$regulatoryProduct->load('compositions','packagings','source');return inertia('Apps/MasterData/RegulatoryProducts/Edit',['product'=>$regulatoryProduct,'sources'=>RegulatorySource::all(),'items'=>Item::select('id','sku','name')->limit(100)->get()]);}
 public function update(RegulatoryProductRequest $request, RegulatoryProduct $regulatoryProduct){$regulatoryProduct->update($request->validated());return back();}
 public function destroy(RegulatoryProduct $regulatoryProduct){$regulatoryProduct->delete();return back();}
 public function downloadTemplateExcel(){
  $rows=[
   ['source_name','nie','product_name_source','industry_name','dosage_form','strength','commodity_type','raw_packaging_text','raw_composition_text'],
   ['BPOM','NIE-0001','Contoh Produk','Contoh Industri','Tablet','500mg','Obat','Strip 10 Tablet','Paracetamol']
  ];
  $tempPath=storage_path('app/regulatory-product-template-'.now()->format('YmdHis').'.xlsx');
  $this->buildTemplateXlsx($tempPath,$rows);
  return response()->download($tempPath,'regulatory-product-template.xlsx')->deleteFileAfterSend(true);
 }
 public function importExcel(Request $request): JsonResponse {
  $request->validate(['file'=>['required','file','mimes:xlsx,csv,txt']]);
  $rows=$this->parseImportRows($request->file('file'));
  if($rows->isEmpty()) return response()->json(['message'=>'File import kosong atau tidak dapat dibaca.'],422);
  $errors=[]; $upsertRows=[]; $processed=0;
  $sourceMap=RegulatorySource::query()->pluck('id','source_name')->all();
  foreach($rows as $index=>$row){
   if($this->isRowEmpty($row)) continue;
   try{
    $data=validator($row,['source_name'=>['required','string','max:255'],'nie'=>['required','string','max:255'],'product_name_source'=>['required','string','max:255'],'industry_name'=>['nullable','string'],'dosage_form'=>['nullable','string'],'strength'=>['nullable','string'],'commodity_type'=>['nullable','string'],'raw_packaging_text'=>['nullable','string'],'raw_composition_text'=>['nullable','string']])->validate();
    $sourceId=$sourceMap[$data['source_name']] ?? null;
    if(! $sourceId){throw new \RuntimeException('Source tidak ditemukan: '.$data['source_name']);}
    $upsertRows[]=[
      'source_id'=>$sourceId,
      'nie'=>$data['nie'],
      'product_name_source'=>$data['product_name_source'],
      'industry_name'=>$data['industry_name'] ?: null,
      'dosage_form'=>$data['dosage_form'] ?: null,
      'strength'=>$data['strength'] ?: null,
      'commodity_type'=>$data['commodity_type'] ?: null,
      'raw_packaging_text'=>$data['raw_packaging_text'] ?: null,
      'raw_composition_text'=>$data['raw_composition_text'] ?: null,
      'updated_at'=>now(),
      'created_at'=>now(),
    ];
    $processed++;
   }catch(\Throwable $exception){$errors[]=['row'=>$index+2,'message'=>$exception->getMessage()];}
  }

  if(!empty($errors)) return response()->json(['message'=>'Import regulatory product gagal, periksa data file.','errors'=>$errors],422);

  foreach(array_chunk($upsertRows,1000) as $chunk){
   RegulatoryProduct::query()->upsert($chunk,['source_id','nie'],['product_name_source','industry_name','dosage_form','strength','commodity_type','raw_packaging_text','raw_composition_text','updated_at']);
  }

  return response()->json(['message'=>"Import regulatory product berhasil melalui upsert. {$processed} data diproses (unik berdasarkan source_name + NIE, sehingga aman untuk import ulang jika sebelumnya gagal)."]);
 }
 public function importBpom(Request $request, RegulatoryProductImportService $s){$request->validate(['file'=>['required','file','mimes:csv,txt']]);$count=$s->importBpom($request->file('file')->getRealPath());return back()->with('success',"Import BPOM berhasil: {$count}");}
 public function importKemenkes(Request $request, RegulatoryProductImportService $s){$request->validate(['file'=>['required','file','mimes:csv,txt']]);$count=$s->importKemenkes($request->file('file')->getRealPath());return back()->with('success',"Import KEMENKES berhasil: {$count}");}
 public function attach(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::firstOrCreate($request->validated(),['is_primary'=>false]);return back();}
 public function detach(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::where('item_id',$request->item_id)->where('regulatory_product_id',$request->regulatory_product_id)->delete();return back();}
 public function setPrimary(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::where('item_id',$request->item_id)->update(['is_primary'=>false]); ItemRegulatoryProduct::where('item_id',$request->item_id)->where('regulatory_product_id',$request->regulatory_product_id)->update(['is_primary'=>true]);return back();}
 public function candidates(RegulatoryProduct $regulatoryProduct, ProductMatchingService $service){ return response()->json($service->candidates($regulatoryProduct)); }

 private function hasRegulatoryProductFulltextIndex(): bool {
  static $hasIndex=null;
  if($hasIndex!==null) return $hasIndex;

  $index=DB::selectOne("SHOW INDEX FROM regulatory_products WHERE Key_name = 'fulltext_regulatory_product_search'");
  $hasIndex=$index!==null;
  return $hasIndex;
 }

 private function parseImportRows(UploadedFile $file): Collection {
  $ext=strtolower($file->getClientOriginalExtension());
  return $ext==='xlsx' ? $this->parseXlsxRows($file->getRealPath()) : $this->parseCsvRows($file->getRealPath());
 }
 private function parseCsvRows(string $path): Collection {
  $rows=[]; $handle=fopen($path,'r'); if(! $handle) return collect(); $header=null;
  while(($data=fgetcsv($handle))!==false){if(! $header){$header=array_map(fn($x)=>trim((string)$x),$data); continue;} $rows[]=collect($header)->mapWithKeys(fn($key,$index)=>[$key=>trim((string)($data[$index]??''))])->all();}
  fclose($handle); return collect($rows);
 }
 private function parseXlsxRows(string $path): Collection {
  $zip=new ZipArchive(); if($zip->open($path)!==true) return collect();
  $sheetXml=$zip->getFromName('xl/worksheets/sheet1.xml');
  $sharedStringsXml=$zip->getFromName('xl/sharedStrings.xml');
  $zip->close();
  if(! $sheetXml) return collect();

  $sharedStrings=[];
  if($sharedStringsXml){
   $sharedXml=simplexml_load_string($sharedStringsXml);
   if($sharedXml!==false && isset($sharedXml->si)){
    foreach($sharedXml->si as $stringItem){
     $textParts=[];
     if(isset($stringItem->t)) $textParts[]=(string)$stringItem->t;
     if(isset($stringItem->r)){ foreach($stringItem->r as $run){ $textParts[]=(string)($run->t ?? ''); } }
     $sharedStrings[]=trim(implode('', $textParts));
    }
   }
  }

  $xml=simplexml_load_string($sheetXml); if($xml===false||!isset($xml->sheetData->row)) return collect(); $table=[];
  foreach($xml->sheetData->row as $row){
   $line=[];
   foreach($row->c as $cell){
    $reference=(string)($cell['r'] ?? '');
    preg_match('/([A-Z]+)/', $reference, $matches);
    $columnLetters=$matches[1] ?? '';
    $columnIndex=$this->xlsxColumnToIndex($columnLetters);

    $value='';
    $cellType=(string)($cell['t'] ?? '');
    if($cellType==='s'){
     $sharedIndex=(int)($cell->v ?? -1);
     $value=$sharedStrings[$sharedIndex] ?? '';
    }elseif($cellType==='inlineStr'){
     $value=(string)($cell->is->t ?? '');
    }else{
     $value=(string)($cell->v ?? '');
    }
    $line[$columnIndex]=trim($value);
   }
   $table[]=$line;
  }

  if(count($table)<2) return collect();
  $headerRow=$table[0] ?? [];
  $header=[];
  foreach($headerRow as $index => $value){
   $header[$index]=trim((string)$value);
  }

  $validHeaderIndexes=array_keys(array_filter($header, fn($value)=>$value!==''));
  if(empty($validHeaderIndexes)) return collect();

  $rows=[];
  foreach(array_slice($table,1) as $line){
   $mapped=[];
   foreach($validHeaderIndexes as $index){
    $mapped[$header[$index]]=trim((string)($line[$index] ?? ''));
   }
   $rows[]=$mapped;
  }
  return collect($rows);
 }

 private function xlsxColumnToIndex(string $column): int {
  if($column==='') return 0;
  $index=0;
  $length=strlen($column);
  for($i=0;$i<$length;$i++){
   $index=$index*26 + (ord($column[$i]) - 64);
  }
  return max(0,$index-1);
 }
 private function buildTemplateXlsx(string $path, array $rows): void {
  $zip=new ZipArchive(); if($zip->open($path, ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) return; $sheetRows='';
  foreach($rows as $rowIndex=>$row){$cellXml=''; foreach($row as $colIndex=>$value){$column=chr(65+$colIndex); $escaped=htmlspecialchars((string)$value,ENT_XML1); $cellXml.="<c r=\"{$column}".($rowIndex+1)."\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";} $sheetRows.="<row r=\"".($rowIndex+1)."\">{$cellXml}</row>";}
  $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
  $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
  $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
  $zip->close();
 }
 private function isRowEmpty(array $row): bool { foreach($row as $value){ if(trim((string)$value)!=='') return false; } return true; }
}
