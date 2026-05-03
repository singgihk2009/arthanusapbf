<?php
namespace App\Services\Procurement;

use App\Models\Core\Contact;
use App\Models\Core\Party;
use App\Models\Core\PartyContact;
use App\Models\Procurement\Vendor;
use Illuminate\Support\Facades\DB;

class VendorContactService
{
    public function ensureVendorParty(Vendor $vendor): Party
    {
        if ($vendor->party_id) return $vendor->party;
        $party = Party::create(['party_type' => 'vendor', 'name' => $vendor->vendor_name ?: $vendor->name ?: 'Unknown Vendor', 'code' => $vendor->vendor_code, 'status' => strtolower($vendor->status ?? 'active')]);
        $vendor->update(['party_id' => $party->id]);
        return $party;
    }

    public function createUserLoginPlaceholder(PartyContact $partyContact): array
    {
        return ['message' => 'Placeholder: create user login from contact not implemented yet.', 'party_contact_id' => $partyContact->id];
    }

    public function setPrimary(Party $party, PartyContact $partyContact): void
    {
        DB::transaction(function () use ($party, $partyContact) {
            $party->partyContacts()->where('status', 'active')->update(['is_primary' => false]);
            $partyContact->update(['is_primary' => true]);
        });
    }

    public function createContactForParty(Party $party, array $data): PartyContact
    {
        return DB::transaction(function () use ($party, $data) {
            if (($data['is_primary'] ?? false) === true) $party->partyContacts()->where('status', 'active')->update(['is_primary' => false]);
            $contact = Contact::create($data);
            return PartyContact::create(['party_id' => $party->id, 'contact_id' => $contact->id, 'contact_role' => $data['contact_role'] ?? null, 'is_primary' => (bool)($data['is_primary'] ?? false), 'can_login' => (bool)($data['can_login'] ?? false), 'status' => $data['status'] ?? 'active', 'notes' => $data['notes'] ?? null]);
        });
    }
}
