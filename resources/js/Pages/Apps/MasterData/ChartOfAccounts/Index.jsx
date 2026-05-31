import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { IconDatabaseOff, IconFileImport } from '@tabler/icons-react';

const typeLabels = {
  asset: 'Asset',
  liability: 'Liability',
  equity: 'Equity',
  revenue: 'Revenue',
  expense: 'Expense',
  other_income: 'Other Income',
  other_expense: 'Other Expense',
};

export default function Index() {
  const { accounts, filters, stats, flash } = usePage().props;
  const { data, setData, post, processing, errors, reset } = useForm({ file: null });

  const submitImport = (event) => {
    event.preventDefault();
    post(route('apps.master-data.chart-of-accounts.import'), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => reset('file'),
    });
  };

  const changeStatus = (status) => {
    router.get(route('apps.master-data.chart-of-accounts.index'), { ...filters, status }, { preserveScroll: true, preserveState: true });
  };

  return (
    <AppLayout>
      <Head title='Master Chart of Account' />
      <div className='space-y-5 p-6'>
        <div className='flex flex-col gap-3 md:flex-row md:items-center md:justify-between'>
          <div>
            <h1 className='text-xl font-semibold'>Master Chart of Account</h1>
            <p className='text-sm text-gray-500'>Import COA hasil export Finance Hub. Company ID otomatis mengikuti user aktif.</p>
          </div>
          <div className='w-full md:w-1/3'><Search url={route('apps.master-data.chart-of-accounts.index')} placeholder='Cari kode / nama COA...' /></div>
        </div>

        {flash?.success && <div className='rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700'>{flash.success}</div>}

        <div className='grid gap-4 md:grid-cols-3'>
          <div className='rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-950'><div className='text-xs text-gray-500'>Total COA</div><div className='text-2xl font-semibold'>{stats.total}</div></div>
          <div className='rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-950'><div className='text-xs text-gray-500'>Active</div><div className='text-2xl font-semibold text-green-600'>{stats.active}</div></div>
          <div className='rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-950'><div className='text-xs text-gray-500'>Inactive</div><div className='text-2xl font-semibold text-gray-500'>{stats.inactive}</div></div>
        </div>

        <form onSubmit={submitImport} className='rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-950'>
          <div className='grid gap-3 md:grid-cols-[1fr_auto] md:items-end'>
            <div>
              <label className='text-sm font-medium'>Import COA dari Finance Hub</label>
              <input type='file' accept='.xlsx,.csv,.txt' onChange={(e) => setData('file', e.target.files?.[0] ?? null)} className='mt-1 block w-full rounded border p-2 text-sm' />
              <p className='mt-1 text-xs text-gray-500'>Kolom wajib: code, name, is_active. Kolom lain seperti parent_code, alias_name, normal_balance, dan flag posting akan diabaikan pada tahap awal.</p>
              {errors.file && <div className='mt-1 text-xs text-red-600'>{errors.file}</div>}
            </div>
            <button type='submit' disabled={!data.file || processing} className='inline-flex items-center justify-center gap-2 rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50'>
              <IconFileImport size={16} strokeWidth={1.5} /> {processing ? 'Mengimport...' : 'Import COA'}
            </button>
          </div>
        </form>

        <div className='flex flex-wrap items-center justify-between gap-3'>
          <div className='flex gap-2 text-sm'>
            {[['active', 'Active'], ['all', 'Semua'], ['inactive', 'Inactive']].map(([value, label]) => (
              <button key={value} type='button' onClick={() => changeStatus(value)} className={`rounded border px-3 py-1 ${filters.status === value ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'bg-white text-gray-600'}`}>{label}</button>
            ))}
          </div>
          <Link href={route('apps.master-data.cash-accounts.index')} className='inline-flex items-center gap-2 rounded border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50'>Lanjut ke Cash Account</Link>
        </div>

        <Table.Card title='Data Chart of Account'>
          <Table>
            <Table.Thead>
              <tr>
                <Table.Th>Kode</Table.Th>
                <Table.Th>Nama</Table.Th>
                <Table.Th>Tipe</Table.Th>
                <Table.Th>Status</Table.Th>
              </tr>
            </Table.Thead>
            <Table.Tbody>
              {accounts.data.length ? accounts.data.map((account) => (
                <tr key={account.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                  <Table.Td><div className='font-medium'>{account.account_code}</div></Table.Td>
                  <Table.Td>{account.account_name}</Table.Td>
                  <Table.Td>{typeLabels[account.account_type] || account.account_type}</Table.Td>
                  <Table.Td><span className={`rounded px-2 py-1 text-xs ${account.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>{account.is_active ? 'Active' : 'Inactive'}</span></Table.Td>
                </tr>
              )) : <Table.Empty colSpan={4} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white'/><span className='text-gray-500'>Belum ada COA untuk company ini.</span></>} />}
            </Table.Tbody>
          </Table>
          {accounts.last_page !== 1 && <Pagination links={accounts.links} />}
        </Table.Card>
      </div>
    </AppLayout>
  );
}
