<?php
namespace App\Http\Controllers\Apps\Procurement;
use App\Http\Controllers\Controller; use Inertia\Inertia;
class GoodsReceiptController extends Controller { public function index(){ return Inertia::render('Apps/Procurement/GoodsReceipts/Index'); } public function create(){ return Inertia::render('Apps/Procurement/GoodsReceipts/Form'); } }
