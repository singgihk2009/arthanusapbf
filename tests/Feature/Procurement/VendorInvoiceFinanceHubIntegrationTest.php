<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
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
});
