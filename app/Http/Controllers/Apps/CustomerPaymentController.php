<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPaymentController extends Controller
{
    public function index(): Response
    {
        $payments = Schema::hasTable('customer_payments')
            ? DB::table('customer_payments as cp')
                ->leftJoin('customers as c', 'c.id', '=', 'cp.customer_id')
                ->orderByDesc('cp.id')
                ->paginate(15, [
                    'cp.id',
                    'cp.number',
                    'cp.payment_date',
                    'cp.payment_method',
                    'cp.amount',
                    'cp.bank_charge',
                    'cp.discount_taken',
                    DB::raw(Schema::hasColumn('customer_payments', 'wht_amount') ? 'cp.wht_amount' : '0 as wht_amount'),
                    DB::raw(Schema::hasColumn('customer_payments', 'other_deduction_amount') ? 'cp.other_deduction_amount' : '0 as other_deduction_amount'),
                    DB::raw(Schema::hasColumn('customer_payments', 'gross_settlement_amount') ? 'cp.gross_settlement_amount' : '(cp.amount + cp.discount_taken) as gross_settlement_amount'),
                    'cp.status',
                    DB::raw('COALESCE(c.customer_name, "-") as customer_name'),
                ])
            : collect();

        return Inertia::render('Apps/Sales/CustomerPayments/Index', ['payments' => $payments]);
    }

    public function create(Request $request): Response
    {
        $invoiceIds = $this->parseInvoiceIds($request->query('invoice_ids', $request->query('invoice_id')));

        return Inertia::render('Apps/Sales/CustomerPayments/Form', [
            'paymentDraft' => $invoiceIds ? $this->buildDraftFromInvoices($invoiceIds) : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'exists:customer_invoices,id'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'bank_account_id' => ['nullable', 'integer'],
            'bank_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.customer_invoice_id' => ['required', 'integer', 'exists:customer_invoices,id'],
            'allocations.*.amount_applied' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.discount_taken' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.wht_amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.other_deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.writeoff_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payment = DB::transaction(function () use ($validated): object {
            $invoiceIds = collect($validated['invoice_ids'])->map(fn ($id) => (int) $id)->unique()->values();
            $invoices = DB::table('customer_invoices')
                ->lockForUpdate()
                ->whereIn('id', $invoiceIds)
                ->whereIn('status', ['posted', 'partially_paid', 'overdue'])
                ->get()
                ->keyBy('id');

            if ($invoices->count() !== $invoiceIds->count()) {
                throw ValidationException::withMessages(['invoice_ids' => 'Semua invoice harus posted/partially paid/overdue.']);
            }

            if ($invoices->pluck('customer_id')->unique()->count() !== 1 || (int) $invoices->first()->customer_id !== (int) $validated['customer_id']) {
                throw ValidationException::withMessages(['invoice_ids' => 'Invoice pembayaran harus untuk customer yang sama.']);
            }

            $allocations = collect($validated['allocations'])
                ->map(function (array $allocation): array {
                    return [
                        'customer_invoice_id' => (int) $allocation['customer_invoice_id'],
                        'amount_applied' => round((float) ($allocation['amount_applied'] ?? 0), 2),
                        'discount_taken' => round((float) ($allocation['discount_taken'] ?? 0), 2),
                        'wht_amount' => round((float) ($allocation['wht_amount'] ?? 0), 2),
                        'other_deduction_amount' => round((float) ($allocation['other_deduction_amount'] ?? 0), 2),
                        'writeoff_amount' => round((float) ($allocation['writeoff_amount'] ?? 0), 2),
                    ];
                })
                ->filter(fn (array $allocation): bool => $allocation['amount_applied'] + $allocation['discount_taken'] + $allocation['wht_amount'] + $allocation['other_deduction_amount'] + $allocation['writeoff_amount'] > 0)
                ->values();

            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages(['allocations' => 'Isi minimal satu alokasi pembayaran atau potongan.']);
            }

            foreach ($allocations as $allocation) {
                $invoice = $invoices->get($allocation['customer_invoice_id']);
                if (! $invoice) {
                    throw ValidationException::withMessages(['allocations' => 'Alokasi invoice tidak valid.']);
                }

                $settlement = $this->allocationSettlement($allocation);
                if ($settlement - (float) $invoice->balance_due > 0.01) {
                    throw ValidationException::withMessages(['allocations' => "Alokasi untuk invoice {$invoice->number} melebihi balance due."]);
                }
            }

            $cashAmount = (float) $allocations->sum('amount_applied');
            $discountTaken = (float) $allocations->sum('discount_taken');
            $whtAmount = (float) $allocations->sum('wht_amount');
            $otherDeductionAmount = (float) $allocations->sum('other_deduction_amount') + (float) $allocations->sum('writeoff_amount');
            $grossSettlementAmount = $cashAmount + $discountTaken + $whtAmount + $otherDeductionAmount;

            $payload = [
                'number' => $this->generateNumber(),
                'customer_id' => (int) $validated['customer_id'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'amount' => $cashAmount,
                'bank_charge' => (float) ($validated['bank_charge'] ?? 0),
                'discount_taken' => $discountTaken,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('customer_payments', 'wht_amount')) {
                $payload['wht_amount'] = $whtAmount;
            }
            if (Schema::hasColumn('customer_payments', 'other_deduction_amount')) {
                $payload['other_deduction_amount'] = $otherDeductionAmount;
            }
            if (Schema::hasColumn('customer_payments', 'gross_settlement_amount')) {
                $payload['gross_settlement_amount'] = $grossSettlementAmount;
            }

            $paymentId = DB::table('customer_payments')->insertGetId($payload);

            foreach ($allocations as $allocation) {
                $allocationPayload = [
                    'customer_payment_id' => $paymentId,
                    'customer_invoice_id' => $allocation['customer_invoice_id'],
                    'amount_applied' => $allocation['amount_applied'],
                    'discount_taken' => $allocation['discount_taken'],
                    'writeoff_amount' => $allocation['writeoff_amount'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('customer_payment_allocations', 'wht_amount')) {
                    $allocationPayload['wht_amount'] = $allocation['wht_amount'];
                }
                if (Schema::hasColumn('customer_payment_allocations', 'other_deduction_amount')) {
                    $allocationPayload['other_deduction_amount'] = $allocation['other_deduction_amount'];
                }

                DB::table('customer_payment_allocations')->insert($allocationPayload);
            }

            return (object) ['id' => $paymentId];
        });

        return redirect()->route('apps.customer-payments.show', $payment->id)->with('success', 'Draft payment berhasil dibuat.');
    }

    public function show(string $id): Response
    {
        $payment = DB::table('customer_payments as cp')
            ->leftJoin('customers as c', 'c.id', '=', 'cp.customer_id')
            ->where('cp.id', $id)
            ->select(['cp.*', DB::raw('COALESCE(c.customer_name, "-") as customer_name'), DB::raw('COALESCE(c.customer_code, "-") as customer_code')])
            ->first();

        abort_unless($payment, 404);

        $allocations = DB::table('customer_payment_allocations as cpa')
            ->join('customer_invoices as ci', 'ci.id', '=', 'cpa.customer_invoice_id')
            ->where('cpa.customer_payment_id', $id)
            ->orderBy('cpa.id')
            ->get([
                'cpa.*',
                'ci.number as invoice_number',
                'ci.invoice_date',
                'ci.due_date',
                'ci.grand_total',
                'ci.balance_due',
            ]);

        return Inertia::render('Apps/Sales/CustomerPayments/Show', ['payment' => $payment, 'allocations' => $allocations]);
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
        DB::transaction(function () use ($id): void {
            $payment = DB::table('customer_payments')->lockForUpdate()->where('id', $id)->first();
            abort_unless($payment, 404);
            if ($payment->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya draft payment yang bisa diposting.']);
            }

            $allocations = DB::table('customer_payment_allocations')->where('customer_payment_id', $id)->get();
            foreach ($allocations as $allocation) {
                $invoice = DB::table('customer_invoices')->lockForUpdate()->where('id', $allocation->customer_invoice_id)->first();
                abort_unless($invoice, 404);
                if (! in_array($invoice->status, ['posted', 'partially_paid', 'overdue'], true)) {
                    throw ValidationException::withMessages(['allocations' => "Invoice {$invoice->number} tidak bisa dibayar."]);
                }

                $settlement = (float) $allocation->amount_applied
                    + (float) $allocation->discount_taken
                    + (float) ($allocation->wht_amount ?? 0)
                    + (float) ($allocation->other_deduction_amount ?? 0)
                    + (float) $allocation->writeoff_amount;

                if ($settlement - (float) $invoice->balance_due > 0.01) {
                    throw ValidationException::withMessages(['allocations' => "Alokasi untuk invoice {$invoice->number} melebihi balance due."]);
                }

                $nextPaid = (float) $invoice->amount_paid + (float) $allocation->amount_applied;
                $nextBalance = max(0, (float) $invoice->balance_due - $settlement);
                $nextStatus = $nextBalance <= 0.01 ? 'paid' : 'partially_paid';

                DB::table('customer_invoices')->where('id', $invoice->id)->update([
                    'amount_paid' => $nextPaid,
                    'balance_due' => $nextBalance,
                    'status' => $nextStatus,
                    'updated_at' => now(),
                ]);
            }

            DB::table('customer_payments')->where('id', $id)->update([
                'status' => 'posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Payment posted.');
    }

    public function cancel(string $id)
    {
        $payment = DB::table('customer_payments')->where('id', $id)->first();
        abort_unless($payment, 404);
        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya draft payment yang bisa dibatalkan tanpa reversal.']);
        }

        DB::table('customer_payments')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);

        return back()->with('success', 'Cancelled');
    }

    private function parseInvoiceIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return collect($raw)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        }

        return collect(explode(',', (string) $raw))->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
    }

    private function buildDraftFromInvoices(array $invoiceIds): array
    {
        $invoiceIds = collect($invoiceIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($invoiceIds->isEmpty()) {
            throw ValidationException::withMessages(['invoice_ids' => 'Pilih minimal satu invoice.']);
        }

        $invoices = DB::table('customer_invoices')
            ->whereIn('id', $invoiceIds)
            ->whereIn('status', ['posted', 'partially_paid', 'overdue'])
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get();

        if ($invoices->count() !== $invoiceIds->count()) {
            throw ValidationException::withMessages(['invoice_ids' => 'Semua invoice harus posted/partially paid/overdue.']);
        }

        $customerIds = $invoices->pluck('customer_id')->unique();
        if ($customerIds->count() !== 1) {
            throw ValidationException::withMessages(['invoice_ids' => 'Invoice gabungan payment harus untuk customer yang sama.']);
        }

        $customer = DB::table('customers')->where('id', $customerIds->first())->first();

        return [
            'customer' => $customer,
            'invoices' => $invoices->map(fn (object $invoice): array => [
                'id' => (int) $invoice->id,
                'number' => $invoice->number,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'grand_total' => (float) $invoice->grand_total,
                'amount_paid' => (float) $invoice->amount_paid,
                'balance_due' => (float) $invoice->balance_due,
            ])->values()->all(),
            'defaults' => [
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Transfer Bank',
            ],
        ];
    }

    private function allocationSettlement(array $allocation): float
    {
        return (float) $allocation['amount_applied']
            + (float) $allocation['discount_taken']
            + (float) $allocation['wht_amount']
            + (float) $allocation['other_deduction_amount']
            + (float) $allocation['writeoff_amount'];
    }

    private function generateNumber(): string
    {
        return DB::transaction(function (): string {
            $prefix = 'PAY-'.now()->format('Ym').'-';
            $last = DB::table('customer_payments')
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('number');
            $sequence = $last ? ((int) substr((string) $last, -5)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
