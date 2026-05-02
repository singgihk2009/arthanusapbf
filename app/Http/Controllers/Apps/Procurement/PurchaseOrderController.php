<?php
namespace App\Http\Controllers\Apps\Procurement;
use App\Http\Controllers\Controller; use Inertia\Inertia;
class PurchaseOrderController extends Controller { public function index(){ return Inertia::render('Apps/Procurement/PurchaseOrders/Index'); } public function create(){ return Inertia::render('Apps/Procurement/PurchaseOrders/Form'); } }
