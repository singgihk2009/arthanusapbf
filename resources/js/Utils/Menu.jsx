import { usePage } from '@inertiajs/react';
import {
    IconArrowsExchange,
    IconBox,
    IconBuildingWarehouse,
    IconBuildingFactory2,
    IconCategory,
    IconClipboardCheck,
    IconExchange,
    IconFileBarcode,
    IconFileDescription,
    IconLayoutDashboard,
    IconListCheck,
    IconPackageExport,
    IconPackageImport,
    IconPlugConnected,
    IconPhoto,
    IconReceipt,
    IconReportAnalytics,
    IconRulerMeasure,
    IconShoppingCart,
    IconStack2,
    IconUsers,
    IconBuilding,
    IconRoute,
} from '@tabler/icons-react';

export default function Menu() {
    const { url, props } = usePage();
    const roles = props?.auth?.roles ?? [];
    const normalizedRoles = roles.map((role) => String(role).toLowerCase());
    const isStockkeeper = normalizedRoles.includes('stockkeeper');
    const isInventoryReportsAccess = normalizedRoles.includes('inventory-reports-access');

    const menuNavigation = [
        {
            title: 'MAIN MENU',
            permissions: true,
            details: [
                {
                    title: 'Dashboard',
                    href: '/apps/dashboard',
                    active: url.startsWith('/apps/dashboard'),
                    icon: <IconLayoutDashboard size={20} strokeWidth={1.5} />,
                    permissions: true,
                },
            ],
        },
        {
            title: 'MASTER',
            permissions: true,
            details: [
                { title: 'Warehouse', href: '/apps/master-data/warehouses', icon: <IconBuildingWarehouse size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/warehouses'), permissions: true },
                { title: 'Kategory', href: '/apps/master-data/categories', icon: <IconCategory size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/categories'), permissions: true },
                { title: 'UOM', href: '/apps/master-data/uoms', icon: <IconRulerMeasure size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/uoms'), permissions: true },
                { title: 'Conversion', href: '/apps/master-data/conversions', icon: <IconExchange size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/conversions'), permissions: true },
                { title: 'Barcode', href: '/apps/master-data/barcodes', icon: <IconFileBarcode size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/barcodes'), permissions: true },
                { title: 'Picture', href: '/apps/master-data/pictures', icon: <IconPhoto size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/pictures'), permissions: true },
                { title: 'Regulatory Source', href: '/apps/master-data/regulatory-sources', icon: <IconStack2 size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/regulatory-sources'), permissions: true },
                { title: 'Regulatory Product', href: '/apps/master-data/regulatory-products', icon: <IconStack2 size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/regulatory-products'), permissions: true },
                { title: 'Regulatory Document', href: '/apps/master-data/regulatory-documents', icon: <IconFileDescription size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/regulatory-documents'), permissions: true },
                { title: 'Minimum Stock', href: '/apps/master-data/min-stocks', icon: <IconListCheck size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/min-stocks'), permissions: true },
                { title: 'Fasilitas', href: '/apps/master-data/facility-schemes', icon: <IconBuildingFactory2 size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/facility-schemes'), permissions: true },
            ],
        },
        {
            title: 'PROCUREMENT',
            permissions: true,
            details: [
                { title: 'Vendors', href: '/apps/procurement/vendors', icon: <IconUsers size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/procurement/vendors'), permissions: true },
                { title: 'Purchase Order', href: '/apps/procurement/purchase-orders', icon: <IconReceipt size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/procurement/purchase-orders'), permissions: true },
                { title: 'Goods Receipt', href: '/apps/procurement/goods-receipts', icon: <IconPackageImport size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/procurement/goods-receipts'), permissions: true },
                { title: 'Purchase Return', href: '/apps/dashboard', icon: <IconPackageExport size={20} strokeWidth={1.5} />, active: false, permissions: true },
                { title: 'Vendor Invoice', href: '/apps/procurement/vendor-invoices', icon: <IconFileBarcode size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/procurement/vendor-invoices'), permissions: true },
                { title: 'Payment to Vendor', href: '/apps/procurement/vendor-payments', icon: <IconShoppingCart size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/procurement/vendor-payments'), permissions: true },
                { title: 'Vendor Ledger', href: '/apps/procurement/vendor-ledgers', icon: <IconReceipt size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/procurement/vendor-ledgers'), permissions: true },
            ],
        },
        {
            title: 'INVENTORY',
            permissions: true,
            details: [
                { title: 'Manual Receiving', href: '/apps/inbound/receiving', icon: <IconClipboardCheck size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/inbound/receiving') && !url.includes('source=po'), permissions: true },
                { title: 'Dispatch', href: '/apps/outbound/internal-usage', icon: <IconBox size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/outbound/internal-usage'), permissions: true },
                { title: 'Transfer Antar Gudang', href: '/apps/transfer/warehouse', icon: <IconArrowsExchange size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/transfer/warehouse'), permissions: true },
                { title: 'Stock Adjustment', href: '/apps/outbound/stock-adjustment', icon: <IconExchange size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/outbound/stock-adjustment'), permissions: true },
                { title: 'Stock Opname', href: '/apps/outbound/stock-opname', icon: <IconClipboardCheck size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/outbound/stock-opname'), permissions: true },
                { title: 'Saldo Awal', href: '/apps/inventory/opening-balance', icon: <IconClipboardCheck size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/inventory/opening-balance'), permissions: true },
                { title: 'Item', href: '/apps/master-data/items', icon: <IconBox size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/master-data/items') || url.startsWith('/apps/inventory/item-cards'), permissions: true },
            ],
        },
        {
            title: 'HUMAN RESOURCE',
            permissions: true,
            details: [
                { title: 'Employee', href: '/apps/human-resource/employees', icon: <IconUsers size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/human-resource/employees'), permissions: true },
            ],
        },
        {
            title: 'SALES',
            permissions: true,
            details: [
                { title: 'Sales Dashboard', href: '/apps/sales/dashboard', icon: <IconLayoutDashboard size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/sales/dashboard'), permissions: true },
                { title: 'Customers', href: '/apps/customers', icon: <IconUsers size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/customers'), permissions: true },
                { title: 'Price Lists', href: '/apps/price-lists', icon: <IconReceipt size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/price-lists'), permissions: true },
                { title: 'Sales Orders', href: '/apps/sales-orders', icon: <IconShoppingCart size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/sales-orders'), permissions: true },
                { title: 'Shipments', href: '/apps/shipments', icon: <IconPackageExport size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/shipments'), permissions: true },
                { title: 'Customer Invoices', href: '/apps/customer-invoices', icon: <IconFileDescription size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/customer-invoices'), permissions: true },
                { title: 'Customer Payments', href: '/apps/customer-payments', icon: <IconReceipt size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/customer-payments'), permissions: true },
                { title: 'Order Tracking', href: '/apps/sales/order-tracking', icon: <IconRoute size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/sales/order-tracking'), permissions: true },
            ],
        },
        {
            title: 'REPORT',
            permissions: true,
            details: [
                { title: 'Barang Masuk', href: '/apps/reports/inventory?type=incoming-items', icon: <IconReportAnalytics size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/reports/inventory') && url.includes('incoming-items'), permissions: true },
            ],
        },
        {
            title: 'SETUP',
            permissions: true,
            details: [
                { title: 'Company Profile', href: '/apps/setup/company-profile', icon: <IconBuilding size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/setup/company-profile'), permissions: true },
                { title: 'User Management', href: '/apps/users', icon: <IconUsers size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/users'), permissions: true },
            ],
        },
        {
            title: 'INTEGRATION',
            permissions: true,
            details: [
                { title: 'Finance Hub Posting', href: '/apps/integration', icon: <IconPlugConnected size={20} strokeWidth={1.5} />, active: url.startsWith('/apps/integration'), permissions: true },
            ],
        },
    ];

    if (isInventoryReportsAccess) {
        return menuNavigation.filter((section) => section.title === 'REPORT');
    }

    if (isStockkeeper) {
        return menuNavigation.filter((section) => section.title === 'INVENTORY');
    }

    return menuNavigation;
}
