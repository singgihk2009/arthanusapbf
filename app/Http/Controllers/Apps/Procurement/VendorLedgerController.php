<?php
namespace App\Http\Controllers\Apps\Procurement;
use App\Http\Controllers\Controller; use Inertia\Inertia;
class VendorLedgerController extends Controller { public function index(){ return Inertia::render('Apps/Procurement/VendorLedgers/Index'); } public function create(){ return Inertia::render('Apps/Procurement/VendorLedgers/Form'); } }
