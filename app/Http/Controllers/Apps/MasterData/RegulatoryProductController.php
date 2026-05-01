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
use ZipArchive;
class RegulatoryProductController extends Controller {
 public function index(Request $request){
  if($request->boolean('download_template')){return $this->downloadTemplateExcel();}
  $q=trim((string)$request->get('q'));$items=RegulatoryProduct::with('source')->when($q,fn($x)=>$x->where('nie','like',"%$q%")->orWhere('product_name_source','like',"%$q%"))->paginate(10)->withQueryString(); return inertia('Apps/MasterData/RegulatoryProducts/Index',['products'=>$items,'filters'=>['q'=>$q]]);
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
  $errors=[]; $inserted=0; $updated=0;
  foreach($rows as $index=>$row){
   if($this->isRowEmpty($row)) continue;
   try{
    $data=validator($row,['source_name'=>['required','string','max:255'],'nie'=>['required','string','max:255'],'product_name_source'=>['required','string','max:255'],'industry_name'=>['nullable','string'],'dosage_form'=>['nullable','string'],'strength'=>['nullable','string'],'commodity_type'=>['nullable','string'],'raw_packaging_text'=>['nullable','string'],'raw_composition_text'=>['nullable','string']])->validate();
    $sourceId=RegulatorySource::query()->where('source_name',$data['source_name'])->value('id');
    if(! $sourceId){throw new \RuntimeException('Source tidak ditemukan: '.$data['source_name']);}
    $product=RegulatoryProduct::query()->updateOrCreate(['source_id'=>$sourceId,'nie'=>$data['nie']],$data+['source_id'=>$sourceId]);
    $product->wasRecentlyCreated ? $inserted++ : $updated++;
   }catch(\Throwable $exception){$errors[]=['row'=>$index+2,'message'=>$exception->getMessage()];}
  }
  if(!empty($errors)) return response()->json(['message'=>'Import regulatory product gagal, periksa data file.','errors'=>$errors],422);
  return response()->json(['message'=>"Import regulatory product berhasil. {$inserted} data baru, {$updated} data diperbarui."]);
 }
 public function importBpom(Request $request, RegulatoryProductImportService $s){$request->validate(['file'=>['required','file','mimes:csv,txt']]);$count=$s->importBpom($request->file('file')->getRealPath());return back()->with('success',"Import BPOM berhasil: {$count}");}
 public function importKemenkes(Request $request, RegulatoryProductImportService $s){$request->validate(['file'=>['required','file','mimes:csv,txt']]);$count=$s->importKemenkes($request->file('file')->getRealPath());return back()->with('success',"Import KEMENKES berhasil: {$count}");}
 public function attach(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::firstOrCreate($request->validated(),['is_primary'=>false]);return back();}
 public function detach(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::where('item_id',$request->item_id)->where('regulatory_product_id',$request->regulatory_product_id)->delete();return back();}
 public function setPrimary(ItemRegulatoryMappingRequest $request){ItemRegulatoryProduct::where('item_id',$request->item_id)->update(['is_primary'=>false]); ItemRegulatoryProduct::where('item_id',$request->item_id)->where('regulatory_product_id',$request->regulatory_product_id)->update(['is_primary'=>true]);return back();}
 public function candidates(RegulatoryProduct $regulatoryProduct, ProductMatchingService $service){ return response()->json($service->candidates($regulatoryProduct)); }
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
