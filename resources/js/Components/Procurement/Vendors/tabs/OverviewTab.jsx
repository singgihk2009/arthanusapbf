import VendorSummaryCards from '../VendorSummaryCards';

export default function Tab({ data, summary }) {
  return <div className='space-y-4'>
    <VendorSummaryCards summary={summary} />
    <pre className='text-xs overflow-auto bg-gray-50 p-3 rounded'>{JSON.stringify(data, null, 2)}</pre>
  </div>;
}
