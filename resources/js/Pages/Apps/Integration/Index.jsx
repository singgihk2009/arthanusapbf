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
                <h1 className="text-xl font-semibold">Integration - Finance Hub</h1>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <Card title="Pending/Sent" value={stats.pending} />
                    <Card title="Error" value={stats.error} />
                    <Card title="Posted Hari Ini" value={stats.posted_today} />
                </div>

                <div className="overflow-x-auto bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left border-b border-gray-200 dark:border-gray-800">
                                <th className="px-3 py-2">Trx No</th>
                                <th className="px-3 py-2">Type</th>
                                <th className="px-3 py-2">Status</th>
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
                                    <td className="px-3 py-2">{row.gl_reference_no || '-'}</td>
                                    <td className="px-3 py-2 text-red-500">{row.gl_error_message || '-'}</td>
                                    <td className="px-3 py-2">
                                        {row.gl_status === 'error' && <button className="text-blue-600" onClick={() => retry(row.id)}>Retry</button>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="text-xs text-gray-500">Total: {transactions.total}</div>
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
