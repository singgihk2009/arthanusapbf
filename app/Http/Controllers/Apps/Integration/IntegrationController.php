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
            'pending' => DB::table('inv_transactions')->whereIn('gl_status', ['pending', 'sent'])->count(),
            'error' => DB::table('inv_transactions')->where('gl_status', 'error')->count(),
            'posted_today' => DB::table('inv_transactions')->where('gl_status', 'posted')->whereDate('gl_posted_at', now()->toDateString())->count(),
        ];

        $transactions = DB::table('inv_transactions')
            ->leftJoin('integration_outbox', function ($join): void {
                $join->on('integration_outbox.aggregate_id', '=', 'inv_transactions.id')
                    ->where('integration_outbox.aggregate_type', '=', 'inv_transaction');
            })
            ->select([
                'inv_transactions.*',
                'integration_outbox.event_type',
                'integration_outbox.status as outbox_status',
                'integration_outbox.attempts as outbox_attempts',
                'integration_outbox.last_error as outbox_last_error',
                'integration_outbox.updated_at as outbox_updated_at',
            ])
            ->orderByDesc('inv_transactions.id')
            ->paginate(20);

        return Inertia::render('Apps/Integration/Index', [
            'stats' => $stats,
            'transactions' => $transactions,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = sprintf('finance-hub-integration-%s.csv', now()->format('Ymd_His'));
        $headers = [
            'ID',
            'Trx No',
            'Trx Type',
            'Trx Date',
            'Status',
            'GL Reference No',
            'Error Message',
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

            DB::table('inv_transactions')
                ->leftJoin('integration_outbox', function ($join): void {
                    $join->on('integration_outbox.aggregate_id', '=', 'inv_transactions.id')
                        ->where('integration_outbox.aggregate_type', '=', 'inv_transaction');
                })
                ->select([
                    'inv_transactions.id',
                    'inv_transactions.trx_no',
                    'inv_transactions.trx_type',
                    'inv_transactions.trx_date',
                    'inv_transactions.gl_status',
                    'inv_transactions.gl_reference_no',
                    'inv_transactions.gl_error_message',
                    'integration_outbox.status as outbox_status',
                    'integration_outbox.attempts as outbox_attempts',
                    'integration_outbox.last_error as outbox_last_error',
                    'inv_transactions.created_at',
                    'inv_transactions.updated_at',
                ])
                ->orderByDesc('inv_transactions.id')
                ->chunk(500, function ($transactions) use ($output): void {
                    foreach ($transactions as $trx) {
                        fputcsv($output, [
                            $trx->id,
                            $trx->trx_no,
                            $trx->trx_type,
                            $trx->trx_date,
                            $trx->gl_status,
                            $trx->gl_reference_no,
                            $trx->gl_error_message,
                            $trx->outbox_status,
                            $trx->outbox_attempts,
                            $trx->outbox_last_error,
                            $trx->created_at,
                            $trx->updated_at,
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
        $trx = DB::table('inv_transactions')->where('id', $transactionId)->first();
        abort_unless($trx, 404);

        DB::transaction(function () use ($transactionId): void {
            DB::table('inv_transactions')->where('id', $transactionId)->update([
                'gl_status' => 'pending',
                'gl_error_message' => null,
                'updated_at' => now(),
            ]);

            DB::table('integration_outbox')
                ->where('aggregate_type', 'inv_transaction')
                ->where('aggregate_id', $transactionId)
                ->update([
                    'status' => 'ready',
                    'last_error' => null,
                    'available_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        $this->sendFinanceHubEvent($transactionId);

        return back()->with('success', 'Transaksi ditandai untuk retry posting Finance Hub.');
    }

    private function sendFinanceHubEvent(int $transactionId): void
    {
        $eventsUrl = config('services.finance_hub.events_url');
        $clientKey = config('services.finance_hub.client_key');
        $clientSecret = config('services.finance_hub.client_secret');

        if (! $eventsUrl || ! $clientKey || ! $clientSecret) {
            return;
        }

        $outbox = DB::table('integration_outbox')
            ->where('aggregate_type', 'inv_transaction')
            ->where('aggregate_id', $transactionId)
            ->first();

        if (! $outbox) {
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
                DB::table('inv_transactions')->where('id', $transactionId)->update([
                    'gl_status' => 'sent',
                    'gl_error_message' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            $message = sprintf('Finance Hub HTTP %s: %s', $response->status(), mb_strimwidth($response->body(), 0, 500));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
        }

        DB::table('integration_outbox')->where('id', $outbox->id)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => $message,
            'updated_at' => now(),
        ]);
        DB::table('inv_transactions')->where('id', $transactionId)->update([
            'gl_status' => 'error',
            'gl_error_message' => $message,
            'updated_at' => now(),
        ]);
    }
}
