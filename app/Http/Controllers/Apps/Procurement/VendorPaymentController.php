<?php
namespace App\Http\Controllers\Apps\Procurement;
use App\Http\Controllers\Controller; use Inertia\Inertia;
class VendorPaymentController extends Controller { public function index(){ return Inertia::render('Apps/Procurement/VendorPayments/Index'); } public function create(){ return Inertia::render('Apps/Procurement/VendorPayments/Form'); } }
