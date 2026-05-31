<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->user = User::factory()->create(['company_id' => 7]);
    actingAs($this->user);
});

it('imports finance hub coa export using active user company id', function () {
    $csv = implode("\n", [
        'parent_code,code,name,alias_name,normal_balance,is_active,allow_manual_posting,allow_reconciliation,requires_dimension,is_control_account',
        '1110,1110-010,Petty Cash,Kas Kecil,debit,1,1,0,0,0',
        '1120,1120-010,Bank BCA,Bank BCA,debit,1,1,0,0,0',
        '2100,2100-010,Account Payable,Hutang Usaha,credit,0,1,0,0,0',
    ]);

    $file = UploadedFile::fake()->createWithContent('coa.csv', $csv);

    post(route('apps.master-data.chart-of-accounts.import'), ['file' => $file])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DB::table('chart_of_accounts')->where('company_id', 7)->count())->toBe(3)
        ->and(DB::table('chart_of_accounts')->where('company_id', 7)->where('account_code', '1110-010')->value('account_name'))->toBe('Petty Cash')
        ->and(DB::table('chart_of_accounts')->where('company_id', 7)->where('account_code', '2100-010')->value('account_type'))->toBe('liability')
        ->and((bool) DB::table('chart_of_accounts')->where('company_id', 7)->where('account_code', '2100-010')->value('is_active'))->toBeFalse();
});

it('rejects vendor payment cash account outside active coa company and sends valid cash account coa payload', function () {
    config()->set('services.finance_hub.base_url', 'https://finance-hub.test');
    config()->set('services.finance_hub.vendor_payment_events_url', 'https://finance-hub.test/api/integrations/vendor-payments/events');
    config()->set('services.finance_hub.client_key', 'ALL-SRJHZSUQOHRP');
    config()->set('services.finance_hub.client_secret', 'secret-test');

    Http::fake([
        'finance-hub.test/*' => Http::response(['message' => 'ok'], 200),
    ]);

    $validCoaId = DB::table('chart_of_accounts')->insertGetId([
        'company_id' => 7,
        'account_code' => '1120-010',
        'account_name' => 'Bank BCA',
        'account_type' => 'asset',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invalidCoaId = DB::table('chart_of_accounts')->insertGetId([
        'company_id' => 8,
        'account_code' => '1120-999',
        'account_name' => 'Bank Other Company',
        'account_type' => 'asset',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $validCashAccountId = DB::table('cash_accounts')->insertGetId([
        'company_id' => 7,
        'chart_of_account_id' => $validCoaId,
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

    $invalidCashAccountId = DB::table('cash_accounts')->insertGetId([
        'company_id' => 8,
        'chart_of_account_id' => $invalidCoaId,
        'code' => 'OTHER-IDR',
        'name' => 'Other Company Bank',
        'cash_type' => 'BANK',
        'currency_code' => 'IDR',
        'is_active' => true,
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $vendorId = DB::table('vendors')->insertGetId([
        'company_id' => 7,
        'vendor_code' => 'V-COA-001',
        'vendor_name' => 'Vendor COA',
        'name' => 'Vendor COA',
        'currency_code' => 'IDR',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = DB::table('vendor_invoices')->insertGetId([
        'company_id' => 7,
        'vendor_id' => $vendorId,
        'invoice_no_internal' => 'VI-COA-0001',
        'vendor_invoice_no' => 'SUP-COA-0001',
        'invoice_date' => '2026-05-31',
        'due_date' => '2026-06-30',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'subtotal' => 1000000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'freight_amount' => 0,
        'grand_total' => 1000000,
        'wht_tax_amount' => 0,
        'net_payable_amount' => 1000000,
        'paid_amount' => 0,
        'outstanding_amount' => 1000000,
        'status' => 'POSTED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.procurement.vendors.payments.store', $vendorId), [
        'payment_date' => '2026-05-31',
        'payment_method' => 'BANK_TRANSFER',
        'cash_account_id' => $invalidCashAccountId,
        'lines' => [[
            'vendor_invoice_id' => $invoiceId,
            'payment_amount' => 100000,
            'wht_amount' => 0,
        ]],
    ])->assertSessionHasErrors('cash_account_id');

    $paymentId = DB::table('vendor_payments')->insertGetId([
        'company_id' => 7,
        'vendor_id' => $vendorId,
        'payment_no' => 'VP-COA-0001',
        'payment_number' => 'VP-COA-0001',
        'payment_date' => '2026-05-31',
        'payment_method' => 'BANK_TRANSFER',
        'cash_account_id' => $validCashAccountId,
        'currency_code' => 'IDR',
        'currency' => 'IDR',
        'exchange_rate' => 1,
        'total_amount' => 100000,
        'allocated_amount' => 100000,
        'unapplied_amount' => 0,
        'total_invoice_amount' => 100000,
        'total_wht_amount' => 0,
        'stamp_duty_amount' => 0,
        'freight_amount' => 0,
        'bank_charge_amount' => 0,
        'total_additional_cost' => 0,
        'net_vendor_payment_amount' => 100000,
        'total_cash_out_amount' => 100000,
        'status' => 'PAID',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vendor_payment_lines')->insert([
        'vendor_payment_id' => $paymentId,
        'vendor_invoice_id' => $invoiceId,
        'invoice_number' => 'VI-COA-0001',
        'invoice_date' => '2026-05-31',
        'invoice_total_amount' => 1000000,
        'invoice_outstanding_amount' => 1000000,
        'payment_amount' => 100000,
        'wht_amount' => 0,
        'net_payment_amount' => 100000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.procurement.vendors.payments.post', [$vendorId, $paymentId]))->assertRedirect();

    Http::assertSent(function ($request) use ($validCashAccountId) {
        $payload = $request->data();

        return $request->url() === 'https://finance-hub.test/api/integrations/vendor-payments/events'
            && $payload['event_name'] === 'vendor.payment.posted'
            && $payload['payload']['source_cash_account']['id'] === $validCashAccountId
            && $payload['payload']['source_cash_account']['code'] === 'BCA-IDR'
            && $payload['payload']['gl_account_code'] === '1120-010';
    });
});
