import AppLayout from '@/Layouts/AppLayout';
import { usePage } from '@inertiajs/react';
import VendorHeader from '@/Components/Procurement/Vendors/VendorHeader';
import VendorTabs from '@/Components/Procurement/Vendors/VendorTabs';

export default function Show() {
  const { vendor, currentTab, summary, documentTypes = [] } = usePage().props;
  return <AppLayout>
    <div className='p-6 space-y-4'>
      <VendorHeader vendor={vendor} />
      <VendorTabs vendor={vendor} currentTab={currentTab || 'overview'} summary={summary} documentTypes={documentTypes} />
    </div>
  </AppLayout>
}
