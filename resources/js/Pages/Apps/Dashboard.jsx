import Table from '@/Components/Table';
import Widget from '@/Components/Widget';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import {
    IconAlertTriangle,
    IconArchive,
    IconArrowsTransferUp,
    IconBuildingWarehouse,
    IconClipboardList,
    IconDownload,
    IconPackage,
    IconReportSearch,
    IconTrendingDown,
    IconTrendingUp,
} from '@tabler/icons-react';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { Bar } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

export default function Dashboard({ kpi, stock_by_warehouse, low_stock_items, movement_trend }) {
    const chartData = {
        labels: movement_trend.map((row) => row.label),
        datasets: [
            {
                label: 'Inbound Qty',
                data: movement_trend.map((row) => row.inbound_qty),
                backgroundColor: 'rgba(34, 197, 94, 0.6)',
            },
            {
                label: 'Outbound Qty',
                data: movement_trend.map((row) => row.outbound_qty),
                backgroundColor: 'rgba(239, 68, 68, 0.6)',
            },
        ],
    };

    const quickTools = [
        {
            label: 'Lihat Alert Minimum Stock',
            description: 'Pantau item yang sudah mencapai batas minimum.',
            href: route('apps.reports.inventory.index', { type: 'minimum-stock-alerts' }),
            icon: IconAlertTriangle,
        },
        {
            label: 'Posting Opening Balance',
            description: 'Perbarui saldo awal stok dari template import.',
            href: route('apps.inventory.opening-balance.index'),
            icon: IconArchive,
        },
        {
            label: 'Transfer Antar Gudang',
            description: 'Pindahkan stok antar gudang secara terkontrol.',
            href: route('apps.transfer.warehouse.index'),
            icon: IconArrowsTransferUp,
        },
        {
            label: 'Inventory Report Lengkap',
            description: 'Analisis stock balance, stock card, dan expired soon.',
            href: route('apps.reports.inventory.index'),
            icon: IconReportSearch,
        },
    ];

    return (
        <>
            <Head title="Dashboard Gudang" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h1 className="text-lg font-semibold text-gray-800 dark:text-gray-100">Dashboard Manajemen Stok Gudang</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Ringkasan data stok real-time untuk membantu tim gudang mengambil keputusan lebih cepat dan akurat.
                    </p>
                    <p className="mt-2 text-xs text-gray-400">Sinkronisasi data terakhir: {kpi.last_sync_at}</p>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Widget
                        title="Total Gudang"
                        subtitle="Gudang aktif terdaftar"
                        color="bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200"
                        icon={<IconBuildingWarehouse size="20" strokeWidth="1.5" />}
                        total={numberFormatter.format(kpi.warehouses)}
                    />
                    <Widget
                        title="Total Item"
                        subtitle="Master item tersedia"
                        color="bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200"
                        icon={<IconPackage size="20" strokeWidth="1.5" />}
                        total={numberFormatter.format(kpi.items)}
                    />
                    <Widget
                        title="On Hand Qty"
                        subtitle="Total stok saat ini"
                        color="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200"
                        icon={<IconClipboardList size="20" strokeWidth="1.5" />}
                        total={numberFormatter.format(kpi.on_hand_qty)}
                    />
                    <Widget
                        title="Inbound Hari Ini"
                        subtitle="Barang masuk"
                        color="bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200"
                        icon={<IconTrendingUp size="20" strokeWidth="1.5" />}
                        total={numberFormatter.format(kpi.inbound_today)}
                    />
                    <Widget
                        title="Outbound Hari Ini"
                        subtitle="Barang keluar"
                        color="bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200"
                        icon={<IconTrendingDown size="20" strokeWidth="1.5" />}
                        total={numberFormatter.format(kpi.outbound_today)}
                    />
                    <Widget
                        title="Alert Minimum Stock"
                        subtitle="Item perlu restock"
                        color="bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200"
                        icon={<IconAlertTriangle size="20" strokeWidth="1.5" />}
                        total={numberFormatter.format(kpi.low_stock_count)}
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950 lg:col-span-2">
                        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200">Tren Pergerakan Stok (6 Bulan)</h2>
                        <div className="mt-3">
                            <Bar data={chartData} options={{ responsive: true, maintainAspectRatio: true }} />
                        </div>
                    </div>

                    <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200">Quick Tools Gudang</h2>
                        <div className="mt-3 space-y-2">
                            {quickTools.map((tool) => {
                                const Icon = tool.icon;
                                return (
                                    <Link
                                        key={tool.label}
                                        href={tool.href}
                                        className="block rounded-lg border border-gray-200 p-3 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-800 dark:hover:border-blue-700 dark:hover:bg-blue-900/20"
                                    >
                                        <div className="flex items-start gap-3">
                                            <span className="rounded-md bg-blue-100 p-2 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">
                                                <Icon size={18} strokeWidth={1.8} />
                                            </span>
                                            <div>
                                                <p className="text-sm font-medium text-gray-800 dark:text-gray-100">{tool.label}</p>
                                                <p className="mt-1 text-xs text-gray-500">{tool.description}</p>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Table.Card title="Top Gudang Berdasarkan Total Stok">
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Gudang</Table.Th>
                                    <Table.Th className="text-right">On Hand Qty</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {stock_by_warehouse.length ? stock_by_warehouse.map((row) => (
                                    <tr key={row.warehouse} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td>{row.warehouse}</Table.Td>
                                        <Table.Td className="text-right">{numberFormatter.format(row.on_hand_qty)}</Table.Td>
                                    </tr>
                                )) : (
                                    <Table.Empty colSpan={2} message="Belum ada data stok per gudang." />
                                )}
                            </Table.Tbody>
                        </Table>
                    </Table.Card>

                    <Table.Card title="Prioritas Restock (Minimum Stock)">
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Gudang</Table.Th>
                                    <Table.Th>Item</Table.Th>
                                    <Table.Th className="text-right">On Hand</Table.Th>
                                    <Table.Th className="text-right">Min Stock</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {low_stock_items.length ? low_stock_items.map((row, index) => (
                                    <tr key={`${row.warehouse}-${index}`} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td>{row.warehouse}</Table.Td>
                                        <Table.Td>{row.item}</Table.Td>
                                        <Table.Td className="text-right text-rose-600">{numberFormatter.format(row.on_hand_qty)}</Table.Td>
                                        <Table.Td className="text-right">{numberFormatter.format(row.min_stock_qty)}</Table.Td>
                                    </tr>
                                )) : (
                                    <Table.Empty colSpan={4} message="Belum ada item yang menyentuh batas minimum stok." />
                                )}
                            </Table.Tbody>
                        </Table>
                    </Table.Card>
                </div>

                <div className="flex justify-end">
                    <Link
                        href={route('apps.inventory.opening-balance.template.excel')}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                    >
                        <IconDownload size={16} strokeWidth={1.8} />
                        Unduh Template Opening Balance
                    </Link>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page) => <AppLayout children={page} />;
