<?php
namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentType;
use App\Services\CompanyProfileService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CompanyProfileController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:setup.company_profile.view', only: ['index']),
            new Middleware('permission:setup.company_profile.update', only: ['update']),
            new Middleware('permission:setup.company_profile.upload_logo', only: ['uploadLogo', 'deleteLogo']),
        ];
    }
    public function index(CompanyProfileService $service)
    {
        $profile = $service->getDefaultCompanyProfile();
        $party = $profile->party;
        $documents = Document::with('documentType')->where('owner_type', 'party')->where('owner_id', $party->id)->latest()->get();
        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get(['id','name','code']);

        return Inertia::render('Setup/CompanyProfile/Index', [
            'companyProfile' => $profile,
            'party' => $party,
            'documents' => $documents,
            'documentTypes' => $documentTypes,
        ]);
    }

    public function update(Request $request, CompanyProfileService $service)
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'pbf_license_number' => ['nullable', 'string', 'max:100'],
            'idak_license_number' => ['nullable', 'string', 'max:100'],
            'cdob_other_license_number' => ['nullable', 'string', 'max:100'],
            'cdob_ccp_license_number' => ['nullable', 'string', 'max:100'],
            'invoice_footer' => ['nullable', 'string'],
            'invoice_terms' => ['nullable', 'string'],
        ]);

        $profile = $service->getDefaultCompanyProfile();
        $profile->update($data);
        $profile->party->update(['name' => $data['legal_name']]);
        return back()->with('success', 'Company profile updated.');
    }

    public function uploadLogo(Request $request, CompanyProfileService $service)
    {
        $request->validate(['logo' => ['required','image','mimes:jpg,jpeg,png,webp','max:2048']]);
        $profile = $service->getDefaultCompanyProfile();
        if ($profile->logo_path) Storage::disk('public')->delete($profile->logo_path);
        $profile->update(['logo_path' => $request->file('logo')->store('company-logos', 'public')]);
        return back()->with('success', 'Logo uploaded.');
    }

    public function deleteLogo(CompanyProfileService $service)
    {
        $profile = $service->getDefaultCompanyProfile();
        if ($profile->logo_path) Storage::disk('public')->delete($profile->logo_path);
        $profile->update(['logo_path' => null]);
        return back();
    }
}
