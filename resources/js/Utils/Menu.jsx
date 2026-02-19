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
    IconListCheck,
    IconPackageExport,
    IconPackageImport,
    IconReceipt,
    IconReportAnalytics,
    IconRulerMeasure,
    IconShoppingCart,
    IconStack2,
} from '@tabler/icons-react';
import React from 'react';

export default function Menu() {
    const { url } = usePage();

    const menuNavigation = [
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
                            href: '/apps/dashboard',
                            icon: <IconExchange size={20} strokeWidth={1.5} />,
                            active: url === '/apps/dashboard',
                            permissions: true,
                        },
                        {
                            title: 'Barcode',
                            href: '/apps/dashboard',
                            icon: <IconFileBarcode size={20} strokeWidth={1.5} />,
                            active: url === '/apps/dashboard',
                            permissions: true,
                        },
                        {
                            title: 'Min Stock',
                            href: '/apps/dashboard',
                            icon: <IconListCheck size={20} strokeWidth={1.5} />,
                            active: url === '/apps/dashboard',
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
                            href: '/apps/dashboard',
                            icon: <IconClipboardCheck size={20} strokeWidth={1.5} />,
                            active: url === '/apps/dashboard',
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
                            href: '/apps/master-data/items',
                            icon: <IconBox size={20} strokeWidth={1.5} />,
                            active: url.startsWith('/apps/master-data/items'),
                            permissions: true,
                        },
                    ],
                },
                {
                    title: 'Transfer Antar Gudang',
                    href: '/apps/dashboard',
                    active: url.startsWith('/apps/dashboard'),
                    icon: <IconArrowsExchange size={20} strokeWidth={1.5} />,
                    permissions: true,
                },
                {
                    title: 'Adjustment & Stock Opname',
                    href: '/apps/dashboard',
                    active: url.startsWith('/apps/dashboard'),
                    icon: <IconClipboardCheck size={20} strokeWidth={1.5} />,
                    permissions: true,
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
                            title: 'Stock Mutasi',
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
