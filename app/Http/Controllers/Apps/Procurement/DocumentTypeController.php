<?php
namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\DocumentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentTypeController extends Controller
{
    public function index(){ return Inertia::render('Apps/Procurement/Settings/DocumentTypes/Index',['documentTypes'=>DocumentType::orderBy('sort_order')->paginate(20)]); }
    public function store(Request $r){ DocumentType::create($r->validate(['code'=>'required|string|max:100|unique:document_types,code','name'=>'required|string|max:255','category'=>'nullable|string|max:100','description'=>'nullable|string','is_required'=>'boolean','is_critical'=>'boolean','blocks_transaction'=>'boolean','requires_expiry_date'=>'boolean','default_validity_days'=>'nullable|integer','applicable_vendor_type'=>'nullable|string|max:100','is_active'=>'boolean','sort_order'=>'nullable|integer'])); return back(); }
    public function update(Request $r, DocumentType $documentType){ $documentType->update($r->validate(['code'=>'required|string|max:100|unique:document_types,code,'.$documentType->id,'name'=>'required|string|max:255','category'=>'nullable|string|max:100','description'=>'nullable|string','is_required'=>'boolean','is_critical'=>'boolean','blocks_transaction'=>'boolean','requires_expiry_date'=>'boolean','default_validity_days'=>'nullable|integer','applicable_vendor_type'=>'nullable|string|max:100','is_active'=>'boolean','sort_order'=>'nullable|integer'])); return back(); }
    public function destroy(DocumentType $documentType){ $documentType->delete(); return back(); }
}
