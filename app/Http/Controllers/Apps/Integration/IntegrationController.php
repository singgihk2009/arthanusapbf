<?php

namespace App\Http\Controllers\Apps\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
            ->orderByDesc('id')
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
            'Warehouse ID',
            'Status',
            'GL Reference No',
            'Error Message',
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
                ->select([
                    'id',
                    'trx_no',
                    'trx_type',
                    'trx_date',
                    'warehouse_id',
                    'gl_status',
                    'gl_reference_no',
                    'gl_error_message',
                    'created_at',
                    'updated_at',
                ])
                ->orderByDesc('id')
                ->chunk(500, function ($transactions) use ($output): void {
                    foreach ($transactions as $trx) {
                        fputcsv($output, [
                            $trx->id,
                            $trx->trx_no,
                            $trx->trx_type,
                            $trx->trx_date,
                            $trx->warehouse_id,
                            $trx->gl_status,
                            $trx->gl_reference_no,
                            $trx->gl_error_message,
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

        return back()->with('success', 'Transaksi ditandai untuk retry posting Finance Hub.');
    }
}
