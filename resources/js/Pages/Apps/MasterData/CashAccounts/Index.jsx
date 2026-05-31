import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash, IconX } from '@tabler/icons-react';
import { useState } from 'react';

const blankForm = {
  chart_of_account_id: '',
  code: '',
  name: '',
  cash_type: 'BANK',
  bank_name: '',
  account_number: '',
  account_holder_name: '',
  currency_code: 'IDR',
  is_active: true,
  is_default: false,
  notes: '',
};

const cashTypeLabels = {
  CASH: 'Cash',
  BANK: 'Bank',
  CASH_EQUIVALENT: 'Cash Equivalent',
};

export default function Index() {
  const { cashAccounts, chartAccounts, flash } = usePage().props;
  const [editingAccount, setEditingAccount] = useState(null);
  const { data, setData, post, put, delete: destroy, reset, processing, errors, clearErrors } = useForm(blankForm);

  const openCreate = () => {
    setEditingAccount(null);
    clearErrors();
    reset();
  };

  const openEdit = (account) => {
    setEditingAccount(account);
    clearErrors();
    setData({
      chart_of_account_id: account.chart_of_account_id || '',
      code: account.code || '',
      name: account.name || '',
      cash_type: account.cash_type || 'BANK',
      bank_name: account.bank_name || '',
      account_number: account.account_number || '',
      account_holder_name: account.account_holder_name || '',
      currency_code: account.currency_code || 'IDR',
      is_active: Boolean(account.is_active),
      is_default: Boolean(account.is_default),
      notes: account.notes || '',
    });
  };

  const submit = (event) => {
    event.preventDefault();
    if (editingAccount) {
      put(route('apps.master-data.cash-accounts.update', editingAccount.id), { preserveScroll: true, onSuccess: openCreate });
      return;
    }

    post(route('apps.master-data.cash-accounts.store'), { preserveScroll: true, onSuccess: openCreate });
  };

  const remove = (account) => {
    if (!window.confirm(`Hapus Cash Account ${account.code}?`)) return;
    destroy(route('apps.master-data.cash-accounts.destroy', account.id), { preserveScroll: true });
  };

  return (
    <AppLayout>
      <Head title='Master Cash Account' />
      <div className='space-y-5 p-6'>
        <div className='flex flex-col gap-3 md:flex-row md:items-center md:justify-between'>
          <div>
            <h1 className='text-xl font-semibold'>Master Cash Account</h1>
            <p className='text-sm text-gray-500'>Daftarkan akun kas, bank, dan cash equivalent yang terhubung ke COA company.</p>
          </div>
          <div className='w-full md:w-1/3'><Search url={route('apps.master-data.cash-accounts.index')} placeholder='Cari cash account / COA...' /></div>
        </div>

        {flash?.success && <div className='rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700'>{flash.success}</div>}

        <div className='grid gap-5 lg:grid-cols-[minmax(0,1fr)_420px]'>
          <Table.Card title='Data Cash Account'>
            <Table>
              <Table.Thead>
                <tr>
                  <Table.Th>Kode</Table.Th>
                  <Table.Th>Nama</Table.Th>
                  <Table.Th>Tipe</Table.Th>
                  <Table.Th>COA Ledger</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th className='w-28'></Table.Th>
                </tr>
              </Table.Thead>
              <Table.Tbody>
                {cashAccounts.data.length ? cashAccounts.data.map((account) => (
                  <tr key={account.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                    <Table.Td>
                      <div className='font-medium'>{account.code}</div>
                      {account.is_default && <div className='text-xs text-indigo-600'>Default</div>}
                    </Table.Td>
                    <Table.Td>
                      <div>{account.name}</div>
                      <div className='text-xs text-gray-500'>{[account.bank_name, account.account_number].filter(Boolean).join(' - ') || '-'}</div>
                    </Table.Td>
                    <Table.Td>{cashTypeLabels[account.cash_type] || account.cash_type}</Table.Td>
                    <Table.Td>{account.chart_of_account ? `${account.chart_of_account.account_code} - ${account.chart_of_account.account_name}` : '-'}</Table.Td>
                    <Table.Td>
                      <span className={`rounded px-2 py-1 text-xs ${account.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>{account.is_active ? 'Active' : 'Inactive'}</span>
                    </Table.Td>
                    <Table.Td>
                      <div className='flex gap-2'>
                        <Button type='button' onClick={() => openEdit(account)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant='orange' />
                        <Button type='button' onClick={() => remove(account)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant='rose' />
                      </div>
                    </Table.Td>
                  </tr>
                )) : <Table.Empty colSpan={6} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white'/><span className='text-gray-500'>Belum ada cash account.</span></>} />}
              </Table.Tbody>
            </Table>
            {cashAccounts.last_page !== 1 && <Pagination links={cashAccounts.links} />}
          </Table.Card>

          <form onSubmit={submit} className='rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-950'>
            <div className='mb-4 flex items-center justify-between'>
              <h2 className='font-semibold'>{editingAccount ? 'Edit Cash Account' : 'Tambah Cash Account'}</h2>
              {editingAccount ? <button type='button' onClick={openCreate} className='text-gray-500'><IconX size={18} /></button> : <IconCirclePlus size={18} />}
            </div>

            <div className='space-y-3'>
              <div>
                <label className='text-sm font-medium'>COA Ledger</label>
                <select value={data.chart_of_account_id} onChange={(e) => setData('chart_of_account_id', e.target.value)} className='mt-1 w-full rounded border p-2'>
                  <option value=''>Pilih COA company...</option>
                  {chartAccounts.map((account) => <option key={account.id} value={account.id}>{account.label}</option>)}
                </select>
                {errors.chart_of_account_id && <div className='mt-1 text-xs text-red-600'>{errors.chart_of_account_id}</div>}
                {!chartAccounts.length && <div className='mt-1 text-xs text-amber-600'>Belum ada COA active untuk company ini. Tambahkan data pada tabel chart_of_accounts terlebih dahulu.</div>}
              </div>

              <div className='grid grid-cols-2 gap-3'>
                <div><label className='text-sm font-medium'>Kode</label><input value={data.code} onChange={(e) => setData('code', e.target.value)} className='mt-1 w-full rounded border p-2' />{errors.code && <div className='mt-1 text-xs text-red-600'>{errors.code}</div>}</div>
                <div><label className='text-sm font-medium'>Currency</label><input maxLength='3' value={data.currency_code} onChange={(e) => setData('currency_code', e.target.value.toUpperCase())} className='mt-1 w-full rounded border p-2' />{errors.currency_code && <div className='mt-1 text-xs text-red-600'>{errors.currency_code}</div>}</div>
              </div>

              <div><label className='text-sm font-medium'>Nama Cash Account</label><input value={data.name} onChange={(e) => setData('name', e.target.value)} className='mt-1 w-full rounded border p-2' />{errors.name && <div className='mt-1 text-xs text-red-600'>{errors.name}</div>}</div>

              <div>
                <label className='text-sm font-medium'>Tipe</label>
                <select value={data.cash_type} onChange={(e) => setData('cash_type', e.target.value)} className='mt-1 w-full rounded border p-2'>
                  <option value='CASH'>Cash</option>
                  <option value='BANK'>Bank</option>
                  <option value='CASH_EQUIVALENT'>Cash Equivalent</option>
                </select>
              </div>

              <div className='grid grid-cols-1 gap-3 md:grid-cols-2'>
                <input value={data.bank_name} onChange={(e) => setData('bank_name', e.target.value)} placeholder='Nama bank / kas' className='rounded border p-2' />
                <input value={data.account_number} onChange={(e) => setData('account_number', e.target.value)} placeholder='No rekening' className='rounded border p-2' />
              </div>
              <input value={data.account_holder_name} onChange={(e) => setData('account_holder_name', e.target.value)} placeholder='Atas nama' className='w-full rounded border p-2' />
              <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder='Notes' className='w-full rounded border p-2' rows='3' />

              <div className='flex gap-4 text-sm'>
                <label className='flex items-center gap-2'><input type='checkbox' checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Active</label>
                <label className='flex items-center gap-2'><input type='checkbox' checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} /> Default</label>
              </div>

              <button type='submit' disabled={processing || !chartAccounts.length} className='w-full rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50'>{editingAccount ? 'Update Cash Account' : 'Simpan Cash Account'}</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
