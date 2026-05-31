<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->user = User::factory()->create(['company_id' => 7]);
    actingAs($this->user);
});

it('creates and sends vendor payment finance hub outbox when approved', function () {
    config()->set('services.finance_hub.base_url', 'https://finance-hub.test');
    config()->set('services.finance_hub.vendor_payment_events_url', 'https://finance-hub.test/api/integrations/vendor-payments/events');
    config()->set('services.finance_hub.client_key', 'ALL-SRJHZSUQOHRP');
    config()->set('services.finance_hub.client_secret', 'secret-test');

    Http::fake([
        'finance-hub.test/*' => Http::response(['message' => 'ok'], 200),
    ]);

    $coaId = DB::table('chart_of_accounts')->insertGetId([
        'company_id' => 7,
        'account_code' => '1120-010',
        'account_name' => 'Bank BCA',
        'account_type' => 'asset',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cashAccountId = DB::table('cash_accounts')->insertGetId([
        'company_id' => 7,
        'chart_of_account_id' => $coaId,
        'code' => 'BCA-IDR',
        'name' => 'Bank BCA IDR',
        'cash_type' => 'BANK',
        'bank_name' => 'BCA',
        'currency_code' => 'IDR',
        'is_active' => true,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $vendorId = DB::table('vendors')->insertGetId([
        'company_id' => 7,
        'vendor_code' => 'V-PAY-001',
        'vendor_name' => 'Vendor Payment Test',
        'name' => 'Vendor Payment Test',
        'currency_code' => 'IDR',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = DB::table('vendor_invoices')->insertGetId([
        'company_id' => 7,
        'vendor_id' => $vendorId,
        'invoice_no_internal' => 'VI-PAY-0001',
        'vendor_invoice_no' => 'SUP-PAY-0001',
        'invoice_date' => '2026-05-26',
        'due_date' => '2026-06-25',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'subtotal' => 13875000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'freight_amount' => 0,
        'grand_total' => 13875000,
        'wht_tax_amount' => 0,
        'net_payable_amount' => 13875000,
        'paid_amount' => 0,
        'outstanding_amount' => 13875000,
        'status' => 'POSTED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paymentId = DB::table('vendor_payments')->insertGetId([
        'company_id' => 7,
        'vendor_id' => $vendorId,
        'payment_no' => 'VPY-202605-00001',
        'payment_number' => 'VPY-202605-00001',
        'payment_date' => '2026-05-26',
        'payment_method' => 'BANK_TRANSFER',
        'cash_account_id' => $cashAccountId,
        'currency_code' => 'IDR',
        'currency' => 'IDR',
        'exchange_rate' => 1,
        'total_amount' => 13875000,
        'allocated_amount' => 13875000,
        'unapplied_amount' => 0,
        'total_invoice_amount' => 13875000,
        'total_wht_amount' => 0,
        'stamp_duty_amount' => 0,
        'freight_amount' => 0,
        'bank_charge_amount' => 0,
        'total_additional_cost' => 0,
        'net_vendor_payment_amount' => 13875000,
        'total_cash_out_amount' => 13875000,
        'status' => 'SUBMITTED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vendor_payment_lines')->insert([
        'vendor_payment_id' => $paymentId,
        'vendor_invoice_id' => $invoiceId,
        'invoice_number' => 'VI-PAY-0001',
        'invoice_date' => '2026-05-26',
        'invoice_total_amount' => 13875000,
        'invoice_outstanding_amount' => 13875000,
        'payment_amount' => 13875000,
        'wht_amount' => 0,
        'net_payment_amount' => 13875000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.procurement.vendors.payments.approve', [$vendorId, $paymentId]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Http::assertSent(function ($request) use ($cashAccountId) {
        $payload = $request->data();

        return $request->url() === 'https://finance-hub.test/api/integrations/vendor-payments/events'
            && $payload['event_name'] === 'vendor.payment.posted'
            && $payload['source_document_no'] === 'VPY-202605-00001'
            && $payload['payload']['cash_account_id'] === $cashAccountId
            && $payload['payload']['amounts']['invoice_payment_total'] === 13875000.0;
    });

    $outbox = DB::table('integration_outbox')
        ->where('aggregate_type', 'vendor_payment')
        ->where('aggregate_id', $paymentId)
        ->first();

    expect($outbox)->not->toBeNull()
        ->and($outbox->event_type)->toBe('vendor.payment.posted')
        ->and($outbox->idempotency_key)->toBe('VP-POSTED-VPY-202605-00001')
        ->and($outbox->status)->toBe('sent')
        ->and(DB::table('vendor_payments')->where('id', $paymentId)->value('status'))->toBe('APPROVED');
});
