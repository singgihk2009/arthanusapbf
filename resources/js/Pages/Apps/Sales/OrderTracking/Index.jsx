import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

const timelineSteps = ['Sales Order', 'Shipment', 'Invoice', 'Payment'];

export default function Index() {
    return (
        <AppLayout>
            <Head title="Order Tracking" />

            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Order Tracking</h1>

                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <label htmlFor="salesOrderNumber" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Search by Sales Order Number
                    </label>
                    <input
                        id="salesOrderNumber"
                        type="text"
                        placeholder="e.g. SO-2026-0001"
                        className="mt-2 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                    />
                </div>

                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Timeline</h2>
                    <ul className="mt-4 space-y-3">
                        {timelineSteps.map((step) => (
                            <li key={step} className="flex items-center gap-3">
                                <span className="inline-flex h-2.5 w-2.5 rounded-full bg-gray-400" />
                                <span className="text-sm text-gray-700 dark:text-gray-300">{step}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
