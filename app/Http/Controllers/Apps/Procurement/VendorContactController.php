<?php
namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\VendorContactRequest;
use App\Models\Core\PartyContact;
use App\Models\Procurement\Vendor;
use App\Services\Procurement\VendorContactService;
use Illuminate\Support\Facades\Schema;

class VendorContactController extends Controller
{
    public function __construct(protected VendorContactService $service) {}

    public function index(Vendor $vendor)
    {
        $party = $this->service->ensureVendorParty($vendor);
        $contacts = $party->partyContacts()->with('contact.user')->latest()->get();
        return response()->json(['contacts' => $contacts]);
    }

    public function store(VendorContactRequest $request, Vendor $vendor)
    {
        $party = $this->service->ensureVendorParty($vendor);
        $this->service->createContactForParty($party, $request->validated());
        return back()->with('success', 'Contact added');
    }

    public function update(VendorContactRequest $request, Vendor $vendor, PartyContact $partyContact)
    {
        $party = $this->service->ensureVendorParty($vendor);
        abort_unless($partyContact->party_id === $party->id, 404);
        $data = $request->validated();
        if (($data['is_primary'] ?? false) === true) $party->partyContacts()->where('status', 'active')->update(['is_primary' => false]);
        $partyContact->contact->update($data);
        $payload = ['is_primary' => (bool)($data['is_primary'] ?? false), 'can_login' => (bool)($data['can_login'] ?? false), 'status' => $data['status'] ?? $partyContact->status, 'notes' => $data['notes'] ?? null];
        if (Schema::hasColumn('party_contacts', 'contact_role')) $payload['contact_role'] = $data['contact_role'] ?? null;
        $partyContact->update($payload);
        return back()->with('success', 'Contact updated');
    }

    public function destroy(Vendor $vendor, PartyContact $partyContact)
    {
        $party = $this->service->ensureVendorParty($vendor);
        abort_unless($partyContact->party_id === $party->id, 404);
        $partyContact->update(['status' => 'inactive']);
        return back()->with('success', 'Contact removed from vendor');
    }

    public function setPrimary(Vendor $vendor, PartyContact $partyContact)
    {
        $party = $this->service->ensureVendorParty($vendor);
        abort_unless($partyContact->party_id === $party->id, 404);
        $this->service->setPrimary($party, $partyContact);
        return back();
    }

    public function toggleStatus(Vendor $vendor, PartyContact $partyContact)
    {
        $party = $this->service->ensureVendorParty($vendor); abort_unless($partyContact->party_id === $party->id, 404);
        $partyContact->update(['status' => $partyContact->status === 'active' ? 'inactive' : 'active']);
        return back();
    }

    public function toggleCanLogin(Vendor $vendor, PartyContact $partyContact)
    {
        $party = $this->service->ensureVendorParty($vendor); abort_unless($partyContact->party_id === $party->id, 404);
        $partyContact->update(['can_login' => !$partyContact->can_login]);
        return back();
    }

    public function createUserLogin(Vendor $vendor, PartyContact $partyContact)
    {
        $party = $this->service->ensureVendorParty($vendor); abort_unless($partyContact->party_id === $party->id, 404);
        return response()->json($this->service->createUserLoginPlaceholder($partyContact));
    }
}
