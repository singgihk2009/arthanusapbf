<?php
namespace App\Http\Controllers\Apps\Procurement;
use App\Http\Controllers\Controller; use Inertia\Inertia;
class VendorInvoiceController extends Controller { public function index(){ return Inertia::render('Apps/Procurement/VendorInvoices/Index'); } public function create(){ return Inertia::render('Apps/Procurement/VendorInvoices/Form'); } }
