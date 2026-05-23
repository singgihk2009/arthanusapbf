<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        return Inertia::render('Apps/Sales/Customers/Index');
    }

    public function create(Request $request)
    {
        return Inertia::render('Apps/Sales/Customers/Form');
    }

    public function store(Request $request)
    {
        return back()->with('success', 'Saved');
    }

    public function show(string $id)
    {
        return Inertia::render('Apps/Sales/Customers/Show', ['id' => $id]);
    }

    public function edit(string $id)
    {
        return Inertia::render('Apps/Sales/Customers/Form', ['id' => $id]);
    }

    public function update(Request $request, string $id)
    {
        return back()->with('success', 'Updated');
    }

    public function destroy(string $id)
    {
        return back()->with('success', 'Deleted');
    }

    public function post(string $id)
    {
        return back()->with('success', 'Posted');
    }

    public function cancel(string $id)
    {
        return back()->with('success', 'Cancelled');
    }
}
