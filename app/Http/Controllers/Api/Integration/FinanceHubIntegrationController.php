<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceHubIntegrationController extends Controller
{
    public function pullOutbox(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 50), 200));

        $events = DB::table('integration_outbox')
            ->where('status', 'ready')
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get(['id as outbox_id', 'idempotency_key', 'payload_json', 'payload_hash']);

        return response()->json(['data' => $events]);
    }

    public function markSent(int $id): JsonResponse
    {
        DB::table('integration_outbox')->where('id', $id)->update([
            'status' => 'sent',
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'ok']);
    }

    public function markPosted(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string'],
            'source_id' => ['required', 'integer'],
            'gl_reference_no' => ['required', 'string'],
            'gl_posted_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated): void {
            DB::table('integration_inbox')->updateOrInsert(
                ['idempotency_key' => $validated['idempotency_key']],
                [
                    'source_app' => 'finance',
                    'message_type' => 'GL_POSTED',
                    'payload_json' => json_encode($validated),
                    'processed_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('inv_transactions')->where('id', $validated['source_id'])->update([
                'gl_status' => 'posted',
                'gl_reference_no' => $validated['gl_reference_no'],
                'gl_posted_at' => $validated['gl_posted_at'],
                'gl_error_message' => null,
                'updated_at' => now(),
            ]);

            DB::table('integration_outbox')->where('idempotency_key', $validated['idempotency_key'])->update([
                'status' => 'acked',
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'ok']);
    }

    public function markError(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string'],
            'source_id' => ['required', 'integer'],
            'message' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($validated): void {
            DB::table('integration_inbox')->updateOrInsert(
                ['idempotency_key' => $validated['idempotency_key']],
                [
                    'source_app' => 'finance',
                    'message_type' => 'GL_ERROR',
                    'payload_json' => json_encode($validated),
                    'processed_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('inv_transactions')->where('id', $validated['source_id'])->update([
                'gl_status' => 'error',
                'gl_error_message' => $validated['message'],
                'updated_at' => now(),
            ]);

            DB::table('integration_outbox')->where('idempotency_key', $validated['idempotency_key'])->update([
                'status' => 'failed',
                'last_error' => $validated['message'],
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'ok']);
    }
}
