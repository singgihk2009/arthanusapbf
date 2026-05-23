<?php
namespace App\Http\Controllers\Apps;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Inertia\Inertia;
class PriceListController extends Controller{
public function index(){return Inertia::render('Apps/Sales/PriceLists/Index');}
public function create(Request ){return Inertia::render('Apps/Sales/PriceLists/Form');}
public function store(Request ){return back()->with('success','Saved');}
public function show(string ){return Inertia::render('Apps/Sales/PriceLists/Show',['id'=>]);}
public function edit(string ){return Inertia::render('Apps/Sales/PriceLists/Form',['id'=>]);}
public function update(Request ,string ){return back()->with('success','Updated');}
public function destroy(string ){return back()->with('success','Deleted');}
public function post(string ){return back()->with('success','Posted');}
public function cancel(string ){return back()->with('success','Cancelled');}
}
