<?php
namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\DocumentType;
use App\Models\Procurement\VendorDocumentRequirement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendorDocumentRequirementController extends Controller
{
    public function index(){ return Inertia::render('Apps/Procurement/Settings/VendorDocumentRequirements/Index',['requirements'=>VendorDocumentRequirement::with('documentType')->paginate(20),'documentTypes'=>DocumentType::orderBy('name')->get(['id','code','name'])]); }
    public function store(Request $r){ VendorDocumentRequirement::create($r->validate(['vendor_type'=>'nullable|string|max:100','document_type_id'=>'required|exists:document_types,id','is_required'=>'boolean','is_critical'=>'boolean','blocks_transaction'=>'boolean','requires_expiry_date'=>'boolean','warning_days_before_expiry'=>'nullable|integer','is_active'=>'boolean'])); return back(); }
    public function update(Request $r, VendorDocumentRequirement $requirement){ $requirement->update($r->validate(['vendor_type'=>'nullable|string|max:100','document_type_id'=>'required|exists:document_types,id','is_required'=>'boolean','is_critical'=>'boolean','blocks_transaction'=>'boolean','requires_expiry_date'=>'boolean','warning_days_before_expiry'=>'nullable|integer','is_active'=>'boolean'])); return back(); }
    public function destroy(VendorDocumentRequirement $requirement){ $requirement->delete(); return back(); }
}
