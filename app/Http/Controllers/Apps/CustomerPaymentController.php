<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            'cashAccounts' => $this->cashAccounts(),
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
            'cash_account_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->validCashAccount((int) $value)) {
                    $fail('Cash account tidak valid, tidak aktif, atau belum terhubung ke Master COA.');
                }
            }],
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
                if ($settlement < -0.01) {
                    throw ValidationException::withMessages(['allocations' => "Settlement untuk invoice {$invoice->number} tidak boleh negatif."]);
                }

                if ($allocation['amount_applied'] - (float) $invoice->balance_due > 0.01) {
                    throw ValidationException::withMessages(['allocations' => "Alokasi untuk invoice {$invoice->number} melebihi balance due."]);
                }
            }

            $cashAmount = (float) $allocations->sum('amount_applied');
            $discountTaken = (float) $allocations->sum('discount_taken');
            $whtAmount = (float) $allocations->sum('wht_amount');
            $otherDeductionAmount = (float) $allocations->sum('other_deduction_amount') + (float) $allocations->sum('writeoff_amount');
            $grossSettlementAmount = $cashAmount + $discountTaken - $whtAmount - $otherDeductionAmount;

            $payload = [
                'number' => $this->generateNumber(),
                'customer_id' => (int) $validated['customer_id'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'cash_account_id' => $validated['cash_account_id'],
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
        $paymentId = DB::transaction(function () use ($id): int {
            $payment = DB::table('customer_payments')->lockForUpdate()->where('id', $id)->first();
            abort_unless($payment, 404);
            if ($payment->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya draft payment yang bisa diposting.']);
            }
            if (Schema::hasColumn('customer_payments', 'cash_account_id') && ! $this->validCashAccount((int) ($payment->cash_account_id ?? 0))) {
                throw ValidationException::withMessages(['cash_account_id' => 'Cash account wajib dipilih dan harus aktif sebelum payment diposting.']);
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
                    - (float) ($allocation->wht_amount ?? 0)
                    - (float) ($allocation->other_deduction_amount ?? 0)
                    - (float) $allocation->writeoff_amount;

                if ($settlement < -0.01) {
                    throw ValidationException::withMessages(['allocations' => "Settlement untuk invoice {$invoice->number} tidak boleh negatif."]);
                }

                if ((float) $allocation->amount_applied - (float) $invoice->balance_due > 0.01) {
                    throw ValidationException::withMessages(['allocations' => "Alokasi untuk invoice {$invoice->number} melebihi balance due."]);
                }

                $nextPaid = (float) $invoice->amount_paid + (float) $allocation->amount_applied;
                $nextBalance = max(0, (float) $invoice->balance_due - (float) $allocation->amount_applied);
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

            $this->upsertCustomerCollectionFinanceHubOutbox((int) $id);

            return (int) $id;
        });

        $this->sendCustomerCollectionFinanceHubEvent($paymentId);

        return back()->with('success', 'Payment posted dan event Finance Hub Customer Collection dibuat.');
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


    private function cashAccounts()
    {
        if (! Schema::hasTable('cash_accounts')) {
            return collect();
        }

        $companyId = auth()->user()?->company_id ?? 1;

        return DB::table('cash_accounts as ca')
            ->leftJoin('chart_of_accounts as coa', 'coa.id', '=', 'ca.chart_of_account_id')
            ->where('ca.company_id', $companyId)
            ->where('ca.is_active', true)
            ->whereNull('ca.deleted_at')
            ->orderByDesc('ca.is_default')
            ->orderBy('ca.cash_type')
            ->orderBy('ca.code')
            ->get([
                'ca.id',
                'ca.code',
                'ca.name',
                'ca.cash_type',
                'ca.currency_code',
                DB::raw('COALESCE(coa.account_code, "") as gl_account_code'),
            ])
            ->map(fn (object $account): array => [
                'id' => (int) $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'cash_type' => $account->cash_type,
                'currency_code' => $account->currency_code,
                'gl_account_code' => $account->gl_account_code,
                'label' => trim("{$account->code} - {$account->name} ({$account->cash_type}/{$account->currency_code})"),
            ]);
    }


    private function defaultCashAccountId(): ?int
    {
        if (! Schema::hasTable('cash_accounts')) {
            return null;
        }

        $companyId = auth()->user()?->company_id ?? 1;
        $id = DB::table('cash_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->orderBy('cash_type')
            ->orderBy('code')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function validCashAccount(int $cashAccountId): bool
    {
        if ($cashAccountId <= 0 || ! Schema::hasTable('cash_accounts')) {
            return false;
        }

        $companyId = auth()->user()?->company_id ?? 1;

        return DB::table('cash_accounts as ca')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'ca.chart_of_account_id')
            ->where('ca.id', $cashAccountId)
            ->where('ca.company_id', $companyId)
            ->where('ca.is_active', true)
            ->whereNull('ca.deleted_at')
            ->where('coa.is_active', true)
            ->exists();
    }

    private function upsertCustomerCollectionFinanceHubOutbox(int $paymentId): void
    {
        if (! Schema::hasTable('integration_outbox')) {
            return;
        }

        $payload = $this->buildCustomerCollectionFinanceHubPayload($paymentId);
        $encoded = json_encode($payload);
        $hash = hash('sha256', (string) $encoded);

        DB::table('integration_outbox')->updateOrInsert(
            ['idempotency_key' => $payload['idempotency_key']],
            [
                'event_type' => $payload['event_name'],
                'aggregate_type' => 'customer_invoice_collection',
                'aggregate_id' => $paymentId,
                'payload_json' => $encoded,
                'payload_hash' => $hash,
                'status' => 'ready',
                'available_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function buildCustomerCollectionFinanceHubPayload(int $paymentId): array
    {
        $payment = DB::table('customer_payments as cp')
            ->leftJoin('customers as c', 'c.id', '=', 'cp.customer_id')
            ->leftJoin('cash_accounts as ca', 'ca.id', '=', 'cp.cash_account_id')
            ->leftJoin('chart_of_accounts as coa', 'coa.id', '=', 'ca.chart_of_account_id')
            ->where('cp.id', $paymentId)
            ->select([
                'cp.*',
                DB::raw('COALESCE(c.customer_code, "") as customer_code'),
                DB::raw('COALESCE(c.customer_name, "") as customer_name'),
                DB::raw('ca.id as cash_account_id'),
                DB::raw('ca.code as cash_account_code'),
                DB::raw('ca.name as cash_account_name'),
                DB::raw('ca.cash_type as cash_account_type'),
                DB::raw('ca.currency_code as cash_account_currency_code'),
                DB::raw('coa.account_code as gl_account_code'),
            ])
            ->first();

        abort_unless($payment, 404);

        $postedAt = $payment->posted_at ? \Carbon\Carbon::parse($payment->posted_at) : now();
        $postingDate = (string) ($payment->payment_date ?: $postedAt->toDateString());
        $documentNo = (string) $payment->number;

        $allocations = DB::table('customer_payment_allocations as cpa')
            ->join('customer_invoices as ci', 'ci.id', '=', 'cpa.customer_invoice_id')
            ->where('cpa.customer_payment_id', $paymentId)
            ->orderBy('cpa.id')
            ->get([
                'cpa.*',
                'ci.number as invoice_no',
                'ci.grand_total as invoice_amount',
            ]);

        $deductions = $allocations
            ->filter(fn (object $allocation): bool => (float) ($allocation->other_deduction_amount ?? 0) + (float) ($allocation->writeoff_amount ?? 0) > 0)
            ->map(fn (object $allocation): array => [
                'code' => (float) ($allocation->writeoff_amount ?? 0) > 0 ? 'WRITEOFF' : 'OTHER-DEDUCTION',
                'description' => 'Potongan customer invoice '.$allocation->invoice_no,
                'amount' => (float) ($allocation->other_deduction_amount ?? 0) + (float) ($allocation->writeoff_amount ?? 0),
            ])
            ->values()
            ->all();

        $otherCharges = $allocations
            ->filter(fn (object $allocation): bool => (float) ($allocation->discount_taken ?? 0) > 0)
            ->map(fn (object $allocation): array => [
                'code' => 'OTHER-CHARGE',
                'description' => 'Biaya lainnya collection invoice '.$allocation->invoice_no,
                'amount' => (float) ($allocation->discount_taken ?? 0),
            ])
            ->values()
            ->all();

        return [
            'source_module' => 'sales',
            'event_name' => 'customer.invoice.collection.posted',
            'event_datetime' => $postedAt->copy()->timezone(config('app.timezone', 'UTC'))->toIso8601String(),
            'idempotency_key' => 'customer-invoice-collection:'.$documentNo.':posted:v1',
            'source_document_type' => 'customer_invoice_collection',
            'source_document_id' => $documentNo,
            'source_document_no' => $documentNo,
            'schema_version' => 'v1',
            'payload' => [
                'transaction_type' => 'customer.invoice.collection',
                'currency_code' => (string) ($payment->cash_account_currency_code ?: 'IDR'),
                'exchange_rate' => 1,
                'posting_date' => $postingDate,
                'entry_date' => $postedAt->toDateString(),
                'reference_no' => $documentNo,
                'description' => 'Customer invoice collection '.$documentNo,
                'gl_account_code' => $payment->gl_account_code,
                'source_cash_account' => $payment->cash_account_id ? [
                    'id' => (int) $payment->cash_account_id,
                    'code' => (string) $payment->cash_account_code,
                    'name' => (string) $payment->cash_account_name,
                    'cash_type' => (string) $payment->cash_account_type,
                    'currency_code' => (string) ($payment->cash_account_currency_code ?: 'IDR'),
                ] : null,
                'customer' => [
                    'customer_code' => (string) $payment->customer_code,
                    'customer_name' => (string) $payment->customer_name,
                ],
                'amounts' => [
                    'invoice_total' => (float) ($payment->amount ?? 0),
                    'other_charge' => (float) ($payment->discount_taken ?? 0),
                    'withholding_tax_total' => (float) ($payment->wht_amount ?? 0),
                    'other_deduction' => (float) ($payment->other_deduction_amount ?? 0),
                    'bank_charge' => (float) ($payment->bank_charge ?? 0),
                ],
                'invoice_lines' => $allocations->map(fn (object $allocation): array => [
                    'invoice_no' => (string) $allocation->invoice_no,
                    'invoice_amount' => (float) $allocation->invoice_amount,
                    'collection_amount' => (float) $allocation->amount_applied,
                    'withholding_tax' => (float) ($allocation->wht_amount ?? 0),
                ])->values()->all(),
                'deductions' => $deductions,
                'other_charges' => $otherCharges,
            ],
        ];
    }

    private function sendCustomerCollectionFinanceHubEvent(int $paymentId): void
    {
        if (! Schema::hasTable('integration_outbox')) {
            return;
        }

        $outbox = DB::table('integration_outbox')
            ->where('aggregate_type', 'customer_invoice_collection')
            ->where('aggregate_id', $paymentId)
            ->first();

        if (! $outbox || in_array($outbox->status, ['sent', 'acked'], true)) {
            return;
        }

        $eventsUrl = $this->customerCollectionFinanceHubEventsUrl();
        $clientKey = config('services.finance_hub.client_key');
        $clientSecret = config('services.finance_hub.client_secret');

        if (! $eventsUrl || ! $clientKey || ! $clientSecret) {
            $this->markCustomerCollectionFinanceHubOutboxFailed((int) $outbox->id, 'Konfigurasi Finance Hub Customer Collection belum lengkap. Pastikan FINANCE_HUB_BASE_URL, FINANCE_HUB_CLIENT_KEY, dan FINANCE_HUB_CLIENT_SECRET tersedia.');
            return;
        }

        $eventPayload = json_decode((string) $outbox->payload_json, true) ?: [];
        $payload = array_merge([
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
        ], $eventPayload);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.finance_hub.timeout', 10))
                ->post($eventsUrl, $payload);

            if ($response->successful()) {
                DB::table('integration_outbox')->where('id', $outbox->id)->update([
                    'status' => 'sent',
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            $message = sprintf('Finance Hub HTTP %s: %s', $response->status(), mb_strimwidth($response->body(), 0, 500));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
        }

        $this->markCustomerCollectionFinanceHubOutboxFailed((int) $outbox->id, $message);
    }

    private function customerCollectionFinanceHubEventsUrl(): ?string
    {
        $configuredUrl = config('services.finance_hub.customer_collection_events_url');
        if (is_string($configuredUrl) && trim($configuredUrl) !== '') {
            return trim($configuredUrl);
        }

        $baseUrl = config('services.finance_hub.base_url');
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            return rtrim(trim($baseUrl), '/').'/api/integrations/events';
        }

        return null;
    }

    private function markCustomerCollectionFinanceHubOutboxFailed(int $outboxId, string $message): void
    {
        DB::table('integration_outbox')->where('id', $outboxId)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => $message,
            'updated_at' => now(),
        ]);
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
                'cash_account_id' => $this->defaultCashAccountId(),
            ],
        ];
    }

    private function allocationSettlement(array $allocation): float
    {
        return (float) $allocation['amount_applied']
            + (float) $allocation['discount_taken']
            - (float) $allocation['wht_amount']
            - (float) $allocation['other_deduction_amount']
            - (float) $allocation['writeoff_amount'];
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
