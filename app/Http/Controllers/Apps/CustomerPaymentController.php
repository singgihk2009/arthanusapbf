<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPaymentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Apps/Sales/CustomerPayments/Index');
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Apps/Sales/CustomerPayments/Form');
    }

    public function store(Request $request)
    {
        return back()->with('success', 'Saved');
    }

    public function show(string $id): Response
    {
        return Inertia::render('Apps/Sales/CustomerPayments/Show', ['id' => $id]);
    }

    public function edit(string $id): Response
    {
        return Inertia::render('Apps/Sales/CustomerPayments/Form', ['id' => $id]);
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
