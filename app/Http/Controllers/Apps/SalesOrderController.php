<?php
namespace App\Http\Controllers\Apps;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Inertia\Inertia;
class SalesOrderController extends Controller{
public function index(){return Inertia::render('Apps/Sales/SalesOrders/Index');}
public function create(){return Inertia::render('Apps/Sales/SalesOrders/Form');}
public function store(Request $r){return back()->with('success','Sales order saved');}
public function show(string $id){return Inertia::render('Apps/Sales/SalesOrders/Show',['id'=>$id]);}
public function edit(string $id){return Inertia::render('Apps/Sales/SalesOrders/Form',['id'=>$id]);}
public function update(Request $r,string $id){return back()->with('success','Updated');}
public function destroy(string $id){return back()->with('success','Deleted');}
public function submit(string $id){return back()->with('success','Submitted');}
public function approve(string $id){return back()->with('success','Approved');}
public function cancel(string $id){return back()->with('success','Cancelled');}
public function createShipment(string $id){return redirect()->route('apps.shipments.create-from-sale',$id);} }
