const formatCurrency = (value) => {
  const amount = Number(value ?? 0);

  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
};

const formatDate = (value) => value
  ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
  : '-';

const prettifyReferenceType = (value) => String(value ?? '-')
  .replaceAll('_', ' ')
  .replaceAll('-', ' ')
  .replace(/\b\w/g, (char) => char.toUpperCase());

export default function LedgerTab({ data }) {
  const ledger = data?.ledger;
  const rows = ledger?.data ?? [];

  const totalDebit = rows.reduce((sum, row) => sum + Number(row?.debit ?? 0), 0);
  const totalCredit = rows.reduce((sum, row) => sum + Number(row?.credit ?? 0), 0);
  const endingBalance = rows.length ? Number(rows[rows.length - 1]?.balance ?? 0) : 0;

  return (
    <div className='space-y-4'>
      <div className='grid grid-cols-1 md:grid-cols-3 gap-3 text-sm'>
        <SummaryCard label='Total Debit (Page)' value={formatCurrency(totalDebit)} tone='text-green-700' />
        <SummaryCard label='Total Credit (Page)' value={formatCurrency(totalCredit)} tone='text-red-700' />
        <SummaryCard label='Ending Balance (Page)' value={formatCurrency(endingBalance)} tone='text-indigo-700' />
      </div>

      <div className='overflow-auto border rounded'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-50'>
            <tr>
              {['Date', 'Reference', 'Description', 'Status', 'Debit', 'Credit', 'Balance'].map((header) => (
                <th key={header} className={`px-3 py-2 ${['Debit', 'Credit', 'Balance'].includes(header) ? 'text-right' : 'text-left'}`}>
                  {header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr className='border-t'>
                <td colSpan={7} className='px-3 py-4 text-center text-gray-500'>
                  Belum ada mutasi ledger untuk vendor ini.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id} className='border-t'>
                  <td className='px-3 py-2 whitespace-nowrap'>{formatDate(row.transaction_date)}</td>
                  <td className='px-3 py-2 whitespace-nowrap'>
                    <div className='font-medium'>{prettifyReferenceType(row.reference_type)}</div>
                    <div className='text-xs text-gray-500'>Ref: #{row.reference_id ?? '-'}</div>
                  </td>
                  <td className='px-3 py-2'>{row.description || '-'}</td>
                  <td className='px-3 py-2'>{String(row.status ?? '-').toUpperCase()}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(row.debit)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(row.credit)}</td>
                  <td className='px-3 py-2 text-right font-semibold'>{formatCurrency(row.balance)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

const SummaryCard = ({ label, value, tone }) => (
  <div className='p-3 rounded border bg-white'>
    <div className='text-gray-500'>{label}</div>
    <div className={`font-semibold ${tone}`}>{value}</div>
  </div>
);
