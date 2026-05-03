import VendorSummaryCards from '../VendorSummaryCards';

export default function Tab({ summary }) {
  return <div className='space-y-4'>
    <VendorSummaryCards summary={summary} />
  </div>;
}
