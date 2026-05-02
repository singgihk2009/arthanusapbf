const items = [
  ['Outstanding AP', 'outstanding_ap'],
  ['Total Purchase YTD', 'total_purchase_ytd'],
  ['Last Purchase Date', 'last_purchase_date'],
  ['Open PO', 'open_po'],
  ['Unpaid Invoice', 'unpaid_invoice'],
  ['Expiring Documents', 'expiring_documents'],
];
export default function VendorSummaryCards({ summary }) {
  return <div className='grid grid-cols-1 md:grid-cols-3 gap-3'>{items.map(([label, key]) => <div key={key} className='border rounded-lg p-4 bg-white'><p className='text-xs text-gray-500'>{label}</p><p className='text-lg font-semibold'>{summary?.[key] ?? '-'}</p></div>)}</div>
}
