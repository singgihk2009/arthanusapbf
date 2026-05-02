import AppLayout from '@/Layouts/AppLayout';
import { usePage } from '@inertiajs/react';
import VendorHeader from '@/Components/Procurement/Vendors/VendorHeader';
import VendorSummaryCards from '@/Components/Procurement/Vendors/VendorSummaryCards';
import VendorTabs from '@/Components/Procurement/Vendors/VendorTabs';

export default function Show() {
  const { vendor, currentTab, summary } = usePage().props;
  return <AppLayout>
    <div className='p-6 space-y-4'>
      <VendorHeader vendor={vendor} />
      <VendorSummaryCards summary={summary} />
      <VendorTabs vendor={vendor} currentTab={currentTab || 'overview'} />
    </div>
  </AppLayout>
}
