<?php
namespace App\Http\Controllers\Apps;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Inertia\Inertia;
class ShipmentController extends Controller{
public function index(){return Inertia::render('Apps/Sales/Shipments/Index');}
public function create(){return Inertia::render('Apps/Sales/Shipments/Form');}
public function createFromSale(string $saleId){return Inertia::render('Apps/Sales/Shipments/Form',['saleId'=>$saleId]);}
public function store(Request $r){return back()->with('success','Saved');}
public function show(string $id){return Inertia::render('Apps/Sales/Shipments/Show',['id'=>$id]);}
public function edit(string $id){return Inertia::render('Apps/Sales/Shipments/Form',['id'=>$id]);}
public function update(Request $r,string $id){return back()->with('success','Updated');}
public function destroy(string $id){return back()->with('success','Deleted');}
public function post(string $id){return back()->with('success','Posted');}
public function cancel(string $id){return back()->with('success','Cancelled');}}
