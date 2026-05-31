import { Head, router } from '@inertiajs/react';
import React from 'react';

export default function IntegrationIndex({ stats, transactions }) {
    const retry = (id) => {
        if (!window.confirm('Retry posting transaksi ini?')) return;
        router.post(`/apps/integration/${id}/retry`);
    };

    return (
        <>
            <Head title="Integration" />
            <div className="p-6 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Integration - Finance Hub</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={route('apps.integration.export.csv')}
                            className="rounded-lg border border-emerald-600 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-950"
                        >
                            Export Data CSV
                        </a>
                        <a
                            href={route('apps.dashboard')}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900"
                        >
                            Kembali ke Menu Inventory
                        </a>
                    </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <Card title="Outbox Pending/Sent" value={stats.pending} />
                    <Card title="Outbox Error" value={stats.error} />
                    <Card title="Acked Hari Ini" value={stats.posted_today} />
                </div>

                <div className="overflow-x-auto bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left border-b border-gray-200 dark:border-gray-800">
                                <th className="px-3 py-2">Source No</th>
                                <th className="px-3 py-2">Source Type</th>
                                <th className="px-3 py-2">Source Status</th>
                                <th className="px-3 py-2">Event</th>
                                <th className="px-3 py-2">Outbox</th>
                                <th className="px-3 py-2">GL Ref</th>
                                <th className="px-3 py-2">Error</th>
                                <th className="px-3 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.map((row) => (
                                <tr key={row.id} className="border-b border-gray-100 dark:border-gray-800">
                                    <td className="px-3 py-2">{row.trx_no}</td>
                                    <td className="px-3 py-2">{row.trx_type}</td>
                                    <td className="px-3 py-2">{row.gl_status}</td>
                                    <td className="px-3 py-2">{row.event_type || '-'}</td>
                                    <td className="px-3 py-2">
                                        <div>{row.outbox_status || '-'}</div>
                                        <div className="text-xs text-gray-500">Attempt: {row.outbox_attempts ?? 0}</div>
                                    </td>
                                    <td className="px-3 py-2">{row.gl_reference_no || '-'}</td>
                                    <td className="px-3 py-2 text-red-500">{row.gl_error_message || row.outbox_last_error || '-'}</td>
                                    <td className="px-3 py-2">
                                        {(row.gl_status === 'error' || row.outbox_status === 'failed') && <button className="text-blue-600" onClick={() => retry(row.id)}>Retry</button>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="text-xs text-gray-500">Total outbox: {transactions.total}</div>
            </div>
        </>
    );
}

function Card({ title, value }) {
    return (
        <div className="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
            <div className="text-xs text-gray-500">{title}</div>
            <div className="text-2xl font-semibold">{value}</div>
        </div>
    );
}
