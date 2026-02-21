import { usePage } from '@inertiajs/react';
import {
    IconAlertTriangle,
    IconArrowsExchange,
    IconBox,
    IconBuildingWarehouse,
    IconCategory,
    IconClipboardCheck,
    IconExchange,
    IconFileBarcode,
    IconLayoutDashboard,
    IconListCheck,
    IconPackageExport,
    IconPackageImport,
    IconReceipt,
    IconReportAnalytics,
    IconRulerMeasure,
    IconShoppingCart,
    IconStack2,
    IconUsers,
} from '@tabler/icons-react';
import React from 'react';

export default function Menu() {
    const { url } = usePage();

    const menuNavigation = [
        {
            title: 'Main Menu',
            permissions: true,
            details: [
                {
                    title: 'Dashboard',
                    href: '/apps/dashboard',
                    active: url.startsWith('/apps/dashboard'),
                    icon: <IconLayoutDashboard size={20} strokeWidth={1.5} />,
                    permissions: true,
                },
                {
                    title: 'User Management',
                    href: '/apps/users',
                    active: url.startsWith('/apps/users'),
                    icon: <IconUsers size={20} strokeWidth={1.5} />,
                    permissions: true,
                },
            ],
        },
        {
            title: 'Inventory Management',
            permissions: true,
            details: [
                {
                    title: 'Master',
                    icon: <IconStack2 size={20} strokeWidth={1.5} />,
                    permissions: true,
                    subdetails: [
                        {
                            title: 'Warehouse',
                            href: '/apps/master-data/warehouses',
                            icon: <IconBuildingWarehouse size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/warehouses'),
                            permissions: true,
                        },
                        {
                            title: 'Category',
                            href: '/apps/master-data/categories',
                            icon: <IconCategory size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/categories'),
                            permissions: true,
                        },
                        {
                            title: 'Item',
                            href: '/apps/master-data/items',
                            icon: <IconBox size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/items'),
                            permissions: true,
                        },
                        {
                            title: 'UOM',
                            href: '/apps/master-data/uoms',
                            icon: <IconRulerMeasure size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/uoms'),
                            permissions: true,
                        },
                        {
                            title: 'Conversion',
                            href: '/apps/master-data/conversions',
                            icon: <IconExchange size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/conversions'),
                            permissions: true,
                        },
                        {
                            title: 'Barcode',
                            href: '/apps/master-data/barcodes',
                            icon: <IconFileBarcode size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/barcodes'),
                            permissions: true,
                        },
                        {
                            title: 'Min Stock',
                            href: '/apps/master-data/min-stocks',
                            icon: <IconListCheck size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/min-stocks'),
                            permissions: true,
                        },
                    ],
                },
                {
                    title: 'Inbound',
                    icon: <IconPackageImport size={20} strokeWidth={1.5} />,
                    permissions: true,
                    subdetails: [
                        {
                            title: 'Purchase Order (PO)',
                            href: '/apps/dashboard',
                            icon: <IconReceipt size={20} strokeWidth={1.5} />,
                            active: url === '/apps/dashboard',
                            permissions: true,
                        },
                        {
                            title: 'Receiving',
                            href: '/apps/inbound/receiving',
                            icon: <IconClipboardCheck size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/inbound/receiving'),
                            permissions: true,
                        },
                    ],
                },
                {
                    title: 'Outbound',
                    icon: <IconPackageExport size={20} strokeWidth={1.5} />,
                    permissions: true,
                    subdetails: [
                        {
                            title: 'Sales',
                            href: '/apps/dashboard',
                            icon: <IconShoppingCart size={20} strokeWidth={1.5} />,
                            active: url === '/apps/dashboard',
                            permissions: true,
                        },
                        {
                            title: 'Internal Use',
                            href: '/apps/outbound/internal-usage',
                            icon: <IconBox size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/outbound/internal-usage'),
                            permissions: true,
                        },
                    ],
                },
                {
                    title: 'Transfer Antar Gudang',
                    href: '/apps/transfer/warehouse',
                    active: url.startsWith('/apps/transfer/warehouse'),
                    icon: <IconArrowsExchange size={20} strokeWidth={1.5} />,
                    permissions: true,
                },
                {
                    title: 'Adjustment & Stock Opname',
                    icon: <IconClipboardCheck size={20} strokeWidth={1.5} />,
                    permissions: true,
                    subdetails: [
                        {
                            title: 'Stock Adjustment',
                            href: '/apps/outbound/stock-adjustment',
                            icon: <IconExchange size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/outbound/stock-adjustment'),
                            permissions: true,
                        },
                        {
                            title: 'Stock Opname',
                            href: '/apps/outbound/stock-opname',
                            icon: <IconClipboardCheck size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/outbound/stock-opname'),
                            permissions: true,
                        },
                    ],
                },
                {
                    title: 'Saldo Awal',
                    href: '/apps/inventory/opening-balance',
                    active: url.startsWith('/apps/inventory/opening-balance'),
                    icon: <IconClipboardCheck size={20} strokeWidth={1.5} />,
                    permissions: true,
                },
                {
                    title: 'Reports',
                    icon: <IconReportAnalytics size={20} strokeWidth={1.5} />,
                    permissions: true,
                    subdetails: [
                        {
                            title: 'Stock Balance',
                            href: '/apps/reports/inventory?type=stock-balance',
                            icon: <IconStack2 size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/reports/inventory') && url.includes('stock-balance'),
                            permissions: true,
                        },
                        {
                            title: 'Kartu Stok',
                            href: '/apps/reports/inventory?type=stock-card',
                            icon: <IconArrowsExchange size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/reports/inventory') && url.includes('stock-card'),
                            permissions: true,
                        },
                    ],
                },
                {
                    title: 'Expired Tracking & Alert',
                    href: '/apps/dashboard',
                    active: url.startsWith('/apps/dashboard'),
                    icon: <IconAlertTriangle size={20} strokeWidth={1.5} />,
                    permissions: true,
                },
            ],
        },
    ];

    return menuNavigation;
}
