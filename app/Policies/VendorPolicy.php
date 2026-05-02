<?php

namespace App\Policies;

use App\Models\Procurement\Vendor;
use App\Models\User;

class VendorPolicy
{
    public function view(User $user, Vendor $vendor): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Vendor $vendor): bool { return true; }
    public function delete(User $user, Vendor $vendor): bool { return true; }
    public function submitQualification(User $user, Vendor $vendor): bool { return true; }
    public function approveQualification(User $user, Vendor $vendor): bool { return true; }
    public function rejectQualification(User $user, Vendor $vendor): bool { return true; }
    public function verifyDocument(User $user, Vendor $vendor): bool { return true; }
    public function downloadDocument(User $user, Vendor $vendor): bool { return true; }
}
