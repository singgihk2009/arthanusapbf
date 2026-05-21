import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Edit({ invoice }) {
  return (
    <AppLayout>
      <Head title='Edit Vendor Invoice' />
      <div className='p-6 space-y-4'>
        <h1 className='text-xl font-semibold'>Edit Vendor Invoice</h1>
        <p className='text-sm text-gray-600'>
          Invoice <strong>{invoice.invoice_no_internal}</strong> saat ini bisa dibuka untuk proses edit lanjutan.
        </p>
        <div>
          <Link href={`/apps/procurement/vendor-invoices/${invoice.id}`} className='px-3 py-2 bg-gray-100 rounded'>View Detail</Link>
        </div>
      </div>
    </AppLayout>
  );
}
