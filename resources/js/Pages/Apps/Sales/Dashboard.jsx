import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

const cards = [
    { title: 'Sales Orders Today', value: '-' },
    { title: 'Pending Shipments', value: '-' },
    { title: 'Posted Invoices', value: '-' },
    { title: 'Payments Received', value: '-' },
];

export default function Dashboard() {
    return (
        <AppLayout>
            <Head title="Sales Dashboard" />

            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Sales Dashboard</h1>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <div key={card.title} className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p className="text-sm text-gray-500 dark:text-gray-400">{card.title}</p>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">{card.value}</p>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
