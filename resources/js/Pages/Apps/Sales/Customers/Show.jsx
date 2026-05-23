import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const tabs = ['Overview', 'Sales Orders', 'Shipments', 'Invoices', 'Payments', 'Ledger Placeholder'];

export default function Show() {
    const [activeTab, setActiveTab] = useState('Overview');

    const tabContent = useMemo(() => {
        if (activeTab === 'Ledger Placeholder') {
            return 'Customer Ledger will be available in Phase 2.';
        }

        return `${activeTab} content placeholder.`;
    }, [activeTab]);

    return (
        <AppLayout>
            <Head title="Customer Details" />

            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Customer Details</h1>

                <div className="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div className="border-b border-gray-200 dark:border-gray-800">
                        <nav className="flex flex-wrap gap-2 p-4">
                            {tabs.map((tab) => (
                                <button
                                    key={tab}
                                    type="button"
                                    onClick={() => setActiveTab(tab)}
                                    className={`rounded-md px-3 py-2 text-sm font-medium transition ${
                                        activeTab === tab
                                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'
                                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    {tab}
                                </button>
                            ))}
                        </nav>
                    </div>

                    <div className="p-4 text-sm text-gray-700 dark:text-gray-300">{tabContent}</div>
                </div>
            </div>
        </AppLayout>
    );
}
