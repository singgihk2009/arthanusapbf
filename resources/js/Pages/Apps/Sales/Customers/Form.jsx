import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, router, useForm } from '@inertiajs/react';

export default function Form({ customer }) {
  const isEdit = Boolean(customer);

  const initialData = {
    customer_code: customer?.customer_code ?? '',
    customer_name: customer?.customer_name ?? '',
    customer_type: customer?.customer_type ?? '',
    id_kemenkes: customer?.id_kemenkes ?? '',
    contact_person: customer?.contact_person ?? '',
    phone: customer?.phone ?? '',
    email: customer?.email ?? '',
    address: customer?.address ?? '',
    city: customer?.city ?? '',
    province: customer?.province ?? '',
    postal_code: customer?.postal_code ?? '',
    country: customer?.country ?? 'Indonesia',
    npwp: customer?.npwp ?? '',
    payment_term_days: customer?.payment_term_days ?? 0,
    credit_limit: customer?.credit_limit ?? 0,
    status: customer?.status ?? 'active',
    notes: customer?.notes ?? '',
  };

  const { data, setData, post, put, processing, errors, reset } = useForm(initialData);

  const submit = (e) => {
    e.preventDefault();

    const submitAction = isEdit ? put : post;
    submitAction(isEdit ? route('apps.customers.update', customer.id) : route('apps.customers.store'));
  };

  const closeForm = () => router.get(route('apps.customers.index'));

  return (
    <>
      <Head title={isEdit ? 'Edit Customer' : 'Add Customer'} />
      <Card
        title='Master Customer'
        form={submit}
        footer={(
          <div className='flex flex-wrap items-center gap-2'>
            <Button type='submit' label='Simpan' variant='gray' disabled={processing} />
            <Button type='button' label='Cancel' variant='orange' onClick={() => reset()} disabled={processing} />
            <Button type='button' label='Close' variant='roseBlack' onClick={closeForm} disabled={processing} />
          </div>
        )}
      >
        <div className='grid grid-cols-1 gap-3 md:grid-cols-2'>
          <Input label='Customer Code' value={data.customer_code} onChange={(e) => setData('customer_code', e.target.value)} errors={errors.customer_code} />
          <Input label='Customer Name' value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} errors={errors.customer_name} />
          <Input label='Customer Type' value={data.customer_type} onChange={(e) => setData('customer_type', e.target.value)} errors={errors.customer_type} />
          <Input label='ID Kemenkes' value={data.id_kemenkes} onChange={(e) => setData('id_kemenkes', e.target.value)} errors={errors.id_kemenkes} />
          <Input label='Contact Person' value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} errors={errors.contact_person} />
          <Input label='Phone' value={data.phone} onChange={(e) => setData('phone', e.target.value)} errors={errors.phone} />
          <Input label='Email' type='email' value={data.email} onChange={(e) => setData('email', e.target.value)} errors={errors.email} />
          <Input label='Address' value={data.address} onChange={(e) => setData('address', e.target.value)} errors={errors.address} />
          <Input label='City' value={data.city} onChange={(e) => setData('city', e.target.value)} errors={errors.city} />
          <Input label='Province' value={data.province} onChange={(e) => setData('province', e.target.value)} errors={errors.province} />
          <Input label='Postal Code' value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} errors={errors.postal_code} />
          <Input label='Country' value={data.country} onChange={(e) => setData('country', e.target.value)} errors={errors.country} />
          <Input label='NPWP' value={data.npwp} onChange={(e) => setData('npwp', e.target.value)} errors={errors.npwp} />
          <Input label='Payment Term (Days)' type='number' value={data.payment_term_days} onChange={(e) => setData('payment_term_days', e.target.value)} errors={errors.payment_term_days} />
          <Input label='Credit Limit' type='number' value={data.credit_limit} onChange={(e) => setData('credit_limit', e.target.value)} errors={errors.credit_limit} />

          <div className='flex flex-col gap-2'>
            <label className='text-gray-600 text-sm'>Status</label>
            <select
              value={data.status}
              onChange={(e) => setData('status', e.target.value)}
              className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none focus:ring-0 bg-white text-gray-700 focus:border-gray-200 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-700 dark:border-gray-800'
            >
              <option value='active'>Active</option>
              <option value='inactive'>Inactive</option>
            </select>
            {errors.status && <small className='text-xs text-red-500'>{errors.status}</small>}
          </div>

          <Input label='Notes' value={data.notes} onChange={(e) => setData('notes', e.target.value)} errors={errors.notes} className='md:col-span-2' />
        </div>
      </Card>
    </>
  );
}

Form.layout = (page) => <AppLayout children={page} />;
