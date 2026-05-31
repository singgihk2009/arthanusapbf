<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->user = User::factory()->create(['company_id' => 1]);
    actingAs($this->user);
});

it('sends vendor invoice posted event to finance hub when approved', function () {
    config()->set('services.finance_hub.base_url', 'https://finance-hub.test');
    config()->set('services.finance_hub.vendor_invoice_events_url', 'https://finance-hub.test/api/integrations/vendor-invoices/events');
    config()->set('services.finance_hub.client_key', 'ALL-SRJHZSUQOHRP');
    config()->set('services.finance_hub.client_secret', 'secret-test');

    Http::fake([
        'finance-hub.test/*' => Http::response(['message' => 'ok'], 200),
    ]);

    $vendorId = DB::table('vendors')->insertGetId([
        'company_id' => 1,
        'vendor_code' => 'V-FIN-001',
        'vendor_name' => 'Vendor Finance',
        'name' => 'Vendor Finance',
        'currency_code' => 'IDR',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = DB::table('vendor_invoices')->insertGetId([
        'company_id' => 1,
        'vendor_id' => $vendorId,
        'invoice_no_internal' => 'VI-0001',
        'vendor_invoice_no' => 'SUP-INV-0001',
        'invoice_date' => '2026-05-30',
        'due_date' => '2026-06-30',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'subtotal' => 6400000,
        'tax_amount' => 704000,
        'discount_amount' => 200000,
        'freight_amount' => 100000,
        'grand_total' => 7004000,
        'wht_tax_amount' => 128000,
        'net_payable_amount' => 6876000,
        'paid_amount' => 0,
        'outstanding_amount' => 6876000,
        'status' => 'DRAFT',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.procurement.vendor-invoices.approve', $invoiceId))->assertRedirect();

    expect(DB::table('vendor_invoices')->where('id', $invoiceId)->value('status'))->toBe('POSTED');

    Http::assertSent(function ($request) use ($invoiceId) {
        $payload = $request->data();

        return $request->url() === 'https://finance-hub.test/api/integrations/vendor-invoices/events'
            && $payload['client_key'] === 'ALL-SRJHZSUQOHRP'
            && $payload['client_secret'] === 'secret-test'
            && $payload['event_name'] === 'vendor.invoice.posted'
            && $payload['idempotency_key'] === 'VI-POSTED-VI-0001'
            && $payload['source_document_type'] === 'vendor_invoice'
            && $payload['source_document_id'] === (string) $invoiceId
            && $payload['source_document_no'] === 'VI-0001'
            && $payload['payload']['transaction_type'] === 'vendor.invoice.standard'
            && $payload['payload']['currency_code'] === 'IDR'
            && $payload['payload']['posting_date'] === '2026-05-30'
            && $payload['payload']['reference_no'] === 'VI-0001'
            && $payload['payload']['amounts']['invoice'] === 6400000.0
            && $payload['payload']['amounts']['tax'] === 704000.0
            && $payload['payload']['amounts']['freight'] === 100000.0
            && $payload['payload']['amounts']['withholding_tax'] === 128000.0
            && $payload['payload']['amounts']['purchase_discount'] === 200000.0
            && $payload['payload']['amounts']['payable_total'] === 6876000.0;
    });

    $outbox = DB::table('integration_outbox')->where('aggregate_type', 'vendor_invoice')->where('aggregate_id', $invoiceId)->first();

    expect($outbox)->not->toBeNull()
        ->and($outbox->event_type)->toBe('vendor.invoice.posted')
        ->and($outbox->idempotency_key)->toBe('VI-POSTED-VI-0001')
        ->and($outbox->status)->toBe('sent');

    get(route('apps.integration.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Apps/Integration/Index')
        ->where('transactions.data.0.aggregate_type', 'vendor_invoice')
        ->where('transactions.data.0.aggregate_id', $invoiceId)
        ->where('transactions.data.0.trx_no', 'VI-0001')
        ->where('transactions.data.0.event_type', 'vendor.invoice.posted')
        ->where('transactions.data.0.outbox_status', 'sent')
    );
});

it('falls back to finance hub base url when vendor invoice endpoint config is empty', function () {
    config()->set('services.finance_hub.base_url', 'https://finance-hub.test');
    config()->set('services.finance_hub.vendor_invoice_events_url', null);
    config()->set('services.finance_hub.client_key', 'ALL-SRJHZSUQOHRP');
    config()->set('services.finance_hub.client_secret', 'secret-test');

    Http::fake([
        'finance-hub.test/*' => Http::response(['message' => 'ok'], 200),
    ]);

    $vendorId = DB::table('vendors')->insertGetId([
        'company_id' => 1,
        'vendor_code' => 'V-FIN-002',
        'vendor_name' => 'Vendor Finance Fallback',
        'name' => 'Vendor Finance Fallback',
        'currency_code' => 'IDR',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = DB::table('vendor_invoices')->insertGetId([
        'company_id' => 1,
        'vendor_id' => $vendorId,
        'invoice_no_internal' => 'VI-FALLBACK-0001',
        'vendor_invoice_no' => 'SUP-FALLBACK-0001',
        'invoice_date' => '2026-05-31',
        'due_date' => '2026-06-30',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'subtotal' => 1000000,
        'tax_amount' => 110000,
        'discount_amount' => 0,
        'freight_amount' => 0,
        'grand_total' => 1110000,
        'wht_tax_amount' => 0,
        'net_payable_amount' => 1110000,
        'paid_amount' => 0,
        'outstanding_amount' => 1110000,
        'status' => 'DRAFT',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.procurement.vendor-invoices.approve', $invoiceId))->assertRedirect();

    Http::assertSent(fn ($request) => $request->url() === 'https://finance-hub.test/api/integrations/vendor-invoices/events'
        && $request->data()['event_name'] === 'vendor.invoice.posted'
        && $request->data()['idempotency_key'] === 'VI-POSTED-VI-FALLBACK-0001');

    expect(DB::table('integration_outbox')->where('aggregate_type', 'vendor_invoice')->where('aggregate_id', $invoiceId)->value('status'))->toBe('sent');
});

it('retries vendor invoice outbox using finance hub base url fallback when endpoint config is empty', function () {
    config()->set('services.finance_hub.base_url', 'https://finance-hub.test');
    config()->set('services.finance_hub.vendor_invoice_events_url', null);
    config()->set('services.finance_hub.client_key', 'ALL-SRJHZSUQOHRP');
    config()->set('services.finance_hub.client_secret', 'secret-test');

    Http::fake([
        'finance-hub.test/*' => Http::response(['message' => 'ok'], 200),
    ]);

    $payload = [
        'event_name' => 'vendor.invoice.posted',
        'event_datetime' => now()->utc()->toJSON(),
        'idempotency_key' => 'VI-POSTED-RETRY-FALLBACK-0001',
        'source_document_type' => 'vendor_invoice',
        'source_document_id' => '9999',
        'source_document_no' => 'RETRY-FALLBACK-0001',
        'schema_version' => 'v1',
        'payload' => [
            'transaction_type' => 'vendor.invoice.standard',
            'currency_code' => 'IDR',
            'posting_date' => '2026-05-31',
            'entry_date' => '2026-05-31',
            'reference_no' => 'RETRY-FALLBACK-0001',
            'description' => 'Vendor Invoice RETRY-FALLBACK-0001',
            'amounts' => [
                'invoice' => 1000000.0,
                'tax' => 110000.0,
                'freight' => 0.0,
                'withholding_tax' => 0.0,
                'purchase_discount' => 0.0,
                'payable_total' => 1110000.0,
            ],
        ],
    ];

    $outboxId = DB::table('integration_outbox')->insertGetId([
        'event_type' => 'vendor.invoice.posted',
        'aggregate_type' => 'vendor_invoice',
        'aggregate_id' => 9999,
        'idempotency_key' => $payload['idempotency_key'],
        'payload_json' => json_encode($payload),
        'payload_hash' => hash('sha256', json_encode($payload)),
        'status' => 'failed',
        'attempts' => 1,
        'last_error' => 'Konfigurasi Finance Hub belum lengkap untuk source vendor_invoice.',
        'available_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.integration.retry', $outboxId))->assertRedirect();

    Http::assertSent(fn ($request) => $request->url() === 'https://finance-hub.test/api/integrations/vendor-invoices/events'
        && $request->data()['client_key'] === 'ALL-SRJHZSUQOHRP'
        && $request->data()['client_secret'] === 'secret-test'
        && $request->data()['event_name'] === 'vendor.invoice.posted'
        && $request->data()['idempotency_key'] === 'VI-POSTED-RETRY-FALLBACK-0001');

    $outbox = DB::table('integration_outbox')->where('id', $outboxId)->first();

    expect($outbox->status)->toBe('sent')
        ->and((int) $outbox->attempts)->toBe(2)
        ->and($outbox->last_error)->toBeNull();
});
