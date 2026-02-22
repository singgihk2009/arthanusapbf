<?php

namespace App\Http\Controllers\Apps\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
