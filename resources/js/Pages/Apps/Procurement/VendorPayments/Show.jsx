import AppLayout from '@/Layouts/AppLayout';
import { usePage } from '@inertiajs/react';

export default function Show(){ const {vendor,payment}=usePage().props; return <AppLayout><div className='p-6 space-y-4'><h1 className='text-xl font-semibold'>Payment {payment.payment_no}</h1><div>Status: {payment.status}</div><div>Vendor: {vendor.vendor_name || vendor.name}</div><pre className='bg-gray-50 p-3 text-xs overflow-auto'>{JSON.stringify(payment,null,2)}</pre></div></AppLayout>; }
