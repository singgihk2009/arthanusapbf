<?php

namespace App\Http\Controllers\Apps\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'pending' => DB::table('integration_outbox')->whereIn('status', ['ready', 'processing', 'sent'])->count(),
            'error' => DB::table('integration_outbox')->where('status', 'failed')->count(),
            'posted_today' => DB::table('integration_outbox')->where('status', 'acked')->whereDate('updated_at', now()->toDateString())->count(),
        ];

        $transactions = $this->outboxRowsQuery()
            ->orderByDesc('integration_outbox.id')
            ->paginate(20)
            ->through(fn ($row) => $this->normalizeOutboxRow($row));

        return Inertia::render('Apps/Integration/Index', [
            'stats' => $stats,
            'transactions' => $transactions,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = sprintf('finance-hub-integration-%s.csv', now()->format('Ymd_His'));
        $headers = [
            'Outbox ID',
            'Source Type',
            'Source ID',
            'Trx No',
            'Trx Type',
            'Status',
            'GL Reference No',
            'Error Message',
            'Event',
            'Outbox Status',
            'Outbox Attempts',
            'Outbox Last Error',
            'Created At',
            'Updated At',
        ];

        return response()->streamDownload(function () use ($headers): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            fputcsv($output, $headers);

            $this->outboxRowsQuery()
                ->orderByDesc('integration_outbox.id')
                ->chunk(500, function ($rows) use ($output): void {
                    foreach ($rows as $rawRow) {
                        $row = $this->normalizeOutboxRow($rawRow);

                        fputcsv($output, [
                            $row['id'],
                            $row['aggregate_type'],
                            $row['aggregate_id'],
                            $row['trx_no'],
                            $row['trx_type'],
                            $row['gl_status'],
                            $row['gl_reference_no'],
                            $row['gl_error_message'],
                            $row['event_type'],
                            $row['outbox_status'],
                            $row['outbox_attempts'],
                            $row['outbox_last_error'],
                            $row['created_at'],
                            $row['updated_at'],
                        ]);
                    }
                });

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function retry(int $transactionId): RedirectResponse
    {
        $outbox = DB::table('integration_outbox')->where('id', $transactionId)->first();
        abort_unless($outbox, 404);

        DB::transaction(function () use ($outbox): void {
            DB::table('integration_outbox')->where('id', $outbox->id)->update([
                'status' => 'ready',
                'last_error' => null,
                'available_at' => now(),
                'updated_at' => now(),
            ]);

            if ($outbox->aggregate_type === 'inv_transaction') {
                DB::table('inv_transactions')->where('id', $outbox->aggregate_id)->update([
                    'gl_status' => 'pending',
                    'gl_error_message' => null,
                    'updated_at' => now(),
                ]);
            }
        });

        $this->sendFinanceHubEvent((int) $outbox->id);

        return back()->with('success', 'Transaksi ditandai untuk retry posting Finance Hub.');
    }

    private function outboxRowsQuery()
    {
        return DB::table('integration_outbox')
            ->leftJoin('inv_transactions', function ($join): void {
                $join->on('inv_transactions.id', '=', 'integration_outbox.aggregate_id')
                    ->where('integration_outbox.aggregate_type', '=', 'inv_transaction');
            })
            ->leftJoin('vendor_invoices', function ($join): void {
                $join->on('vendor_invoices.id', '=', 'integration_outbox.aggregate_id')
                    ->where('integration_outbox.aggregate_type', '=', 'vendor_invoice');
            })
            ->leftJoin('vendors', 'vendors.id', '=', 'vendor_invoices.vendor_id')
            ->select([
                'integration_outbox.id',
                'integration_outbox.event_type',
                'integration_outbox.aggregate_type',
                'integration_outbox.aggregate_id',
                'integration_outbox.status as outbox_status',
                'integration_outbox.attempts as outbox_attempts',
                'integration_outbox.last_error as outbox_last_error',
                'integration_outbox.created_at',
                'integration_outbox.updated_at',
                'inv_transactions.trx_no as inv_trx_no',
                'inv_transactions.trx_type as inv_trx_type',
                'inv_transactions.gl_status as inv_gl_status',
                'inv_transactions.gl_reference_no as inv_gl_reference_no',
                'inv_transactions.gl_error_message as inv_gl_error_message',
                'vendor_invoices.invoice_no_internal as vendor_invoice_no_internal',
                'vendor_invoices.vendor_invoice_no',
                'vendor_invoices.status as vendor_invoice_status',
                'vendors.vendor_name',
                'vendors.name as vendor_name_fallback',
            ]);
    }

    private function normalizeOutboxRow(object $row): array
    {
        if ($row->aggregate_type === 'vendor_invoice') {
            $vendorName = $row->vendor_name ?: $row->vendor_name_fallback;
            $documentNo = $row->vendor_invoice_no_internal ?: $row->vendor_invoice_no ?: ('Vendor Invoice #'.$row->aggregate_id);

            return [
                'id' => $row->id,
                'aggregate_type' => $row->aggregate_type,
                'aggregate_id' => $row->aggregate_id,
                'trx_no' => $documentNo,
                'trx_type' => $vendorName ? 'Vendor Invoice - '.$vendorName : 'Vendor Invoice',
                'gl_status' => $row->vendor_invoice_status ?: '-',
                'event_type' => $row->event_type,
                'outbox_status' => $row->outbox_status,
                'outbox_attempts' => $row->outbox_attempts,
                'outbox_last_error' => $row->outbox_last_error,
                'gl_reference_no' => null,
                'gl_error_message' => $row->outbox_last_error,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return [
            'id' => $row->id,
            'aggregate_type' => $row->aggregate_type,
            'aggregate_id' => $row->aggregate_id,
            'trx_no' => $row->inv_trx_no ?: ucfirst(str_replace('_', ' ', (string) $row->aggregate_type)).' #'.$row->aggregate_id,
            'trx_type' => $row->inv_trx_type ?: $row->aggregate_type,
            'gl_status' => $row->inv_gl_status ?: '-',
            'event_type' => $row->event_type,
            'outbox_status' => $row->outbox_status,
            'outbox_attempts' => $row->outbox_attempts,
            'outbox_last_error' => $row->outbox_last_error,
            'gl_reference_no' => $row->inv_gl_reference_no,
            'gl_error_message' => $row->inv_gl_error_message ?: $row->outbox_last_error,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    private function sendFinanceHubEvent(int $outboxId): void
    {
        $outbox = DB::table('integration_outbox')->where('id', $outboxId)->first();

        if (! $outbox) {
            return;
        }

        $eventsUrl = $outbox->aggregate_type === 'vendor_invoice'
            ? $this->vendorInvoiceFinanceHubEventsUrl()
            : config('services.finance_hub.events_url');
        $clientKey = config('services.finance_hub.client_key');
        $clientSecret = config('services.finance_hub.client_secret');

        if (! $eventsUrl || ! $clientKey || ! $clientSecret) {
            $this->markOutboxFailed(
                (int) $outbox->id,
                'Konfigurasi Finance Hub belum lengkap untuk source '.$outbox->aggregate_type.'. Pastikan endpoint, client key, dan client secret tersedia.'
            );

            if ($outbox->aggregate_type === 'inv_transaction') {
                DB::table('inv_transactions')->where('id', $outbox->aggregate_id)->update([
                    'gl_status' => 'error',
                    'gl_error_message' => 'Konfigurasi Finance Hub belum lengkap.',
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $eventsUrl = $outbox->aggregate_type === 'vendor_invoice'
            ? config('services.finance_hub.vendor_invoice_events_url')
            : config('services.finance_hub.events_url');
        $clientKey = config('services.finance_hub.client_key');
        $clientSecret = config('services.finance_hub.client_secret');

        if (! $eventsUrl || ! $clientKey || ! $clientSecret) {
            return;
        }

        $payload = json_decode((string) $outbox->payload_json, true) ?: [];
        $payload['client_key'] = $clientKey;
        $payload['client_secret'] = $clientSecret;

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

                if ($outbox->aggregate_type === 'inv_transaction') {
                    DB::table('inv_transactions')->where('id', $outbox->aggregate_id)->update([
                        'gl_status' => 'sent',
                        'gl_error_message' => null,
                        'updated_at' => now(),
                    ]);
                }

                return;
            }

            $message = sprintf('Finance Hub HTTP %s: %s', $response->status(), mb_strimwidth($response->body(), 0, 500));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
        }

        $this->markOutboxFailed((int) $outbox->id, $message);

        if ($outbox->aggregate_type === 'inv_transaction') {
            DB::table('inv_transactions')->where('id', $outbox->aggregate_id)->update([
                'gl_status' => 'error',
                'gl_error_message' => $message,
                'updated_at' => now(),
            ]);
        }
    }

    private function vendorInvoiceFinanceHubEventsUrl(): ?string
    {
        $configuredUrl = config('services.finance_hub.vendor_invoice_events_url');
        if (is_string($configuredUrl) && trim($configuredUrl) !== '') {
            return trim($configuredUrl);
        }

        $baseUrl = config('services.finance_hub.base_url');
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            return rtrim(trim($baseUrl), '/').'/api/integrations/vendor-invoices/events';
        }

        return null;
    }

    private function markOutboxFailed(int $outboxId, string $message): void
    {
        DB::table('integration_outbox')->where('id', $outboxId)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => $message,
            'updated_at' => now(),
        ]);
    }
}
