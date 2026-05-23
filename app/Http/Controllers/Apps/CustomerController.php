<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Sales\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search')->toString());
        $status = $request->string('status')->toString();

        $customers = Customer::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('customer_code', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('npwp', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('Apps/Sales/Customers/Index', ['customers' => $customers, 'filters' => compact('search', 'status')]);
    }

    public function create()
    {
        return Inertia::render('Apps/Sales/Customers/Form', ['customer' => null]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        if (blank($data['customer_code'] ?? null)) {
            $data['customer_code'] = $this->nextCustomerCode();
        }
        $data['country'] = $data['country'] ?? 'Indonesia';
        $data['payment_term_days'] = $data['payment_term_days'] ?? 0;
        $data['credit_limit'] = $data['credit_limit'] ?? 0;

        $customer = Customer::create($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        return Inertia::render('Apps/Sales/Customers/Show', [
            'customer' => $customer,
            'summary' => [
                'total_sales_orders' => Schema::hasTable('sales') ? $customer->salesOrders()?->count() ?? 0 : 0,
                'total_invoices' => Schema::hasTable('customer_invoices') ? $customer->invoices()?->count() ?? 0 : 0,
                'total_payments' => Schema::hasTable('customer_payments') ? $customer->payments()?->count() ?? 0 : 0,
                'outstanding_balance' => 0,
            ],
        ]);
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Apps/Sales/Customers/Form', ['customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $data['country'] = $data['country'] ?? 'Indonesia';
        $customer->update($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        if ((Schema::hasTable('sales') && ($customer->salesOrders()?->exists())) ||
            (Schema::hasTable('customer_invoices') && ($customer->invoices()?->exists())) ||
            (Schema::hasTable('customer_payments') && ($customer->payments()?->exists()))) {
            return back()->withErrors(['delete' => 'Customer cannot be deleted because it already has transactions.']);
        }

        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->string('q')->toString());
        $rows = Customer::query()->where('status', 'active')
            ->when($q !== '', fn ($query) => $query->where(fn ($s) => $s->where('customer_code', 'like', "%{$q}%")->orWhere('customer_name', 'like', "%{$q}%")))
            ->limit(20)
            ->get(['id', 'customer_code', 'customer_name', 'phone', 'city', 'payment_term_days', 'credit_limit', 'price_list_id']);

        return response()->json($rows);
    }

    private function nextCustomerCode(): string
    {
        return DB::transaction(function () {
            $last = Customer::query()->lockForUpdate()->orderByDesc('id')->first();
            $lastNumber = $last ? (int) preg_replace('/\D/', '', (string) $last->customer_code) : 0;
            return 'CUST-'.str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
        });
    }
}
