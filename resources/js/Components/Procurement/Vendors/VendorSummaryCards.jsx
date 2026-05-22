const items = [
  ['Outstanding AP', 'outstanding_ap'],
  ['Total Purchase YTD', 'total_purchase_ytd'],
  ['Last Purchase Date', 'last_purchase_date'],
  ['Open PO', 'open_po'],
  ['Unpaid Invoice', 'unpaid_invoice'],
  ['Expiring Documents', 'expiring_documents'],
];

const amountFields = new Set(['outstanding_ap', 'total_purchase_ytd']);
const dateFields = new Set(['last_purchase_date']);

const formatAmount = (value) => {
  const amount = Number(value ?? 0);

  if (!Number.isFinite(amount)) return '0.00';

  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (value) => {
  if (!value) return '-';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
};

const displayValue = (key, value) => {
  if (amountFields.has(key)) return formatAmount(value);
  if (dateFields.has(key)) return formatDate(value);

  return value ?? '-';
};

export default function VendorSummaryCards({ summary }) {
  return <div className='grid grid-cols-1 md:grid-cols-3 gap-3'>{items.map(([label, key]) => <div key={key} className='border rounded-lg p-4 bg-white'><p className='text-xs text-gray-500'>{label}</p><p className='text-lg font-semibold'>{displayValue(key, summary?.[key])}</p></div>)}</div>
}
