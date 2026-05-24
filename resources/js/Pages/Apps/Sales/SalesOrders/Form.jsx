import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';

const emptyLine = {
  item_id: '',
  uom_id: '',
  facility_scheme_id: '',
  qty_sold: 1,
  unit_price: 0,
  discount_percent: 0,
  tax_percent: 0,
  notes: '',
};

export default function Page({ customer, salesOrder, warehouses = [], items = [] }) {
  const isEdit = Boolean(salesOrder);

  const { data, setData, post, put, processing, errors } = useForm({
    warehouse_id: salesOrder?.warehouse_id || '',
    document_date: salesOrder?.document_date || new Date().toISOString().slice(0, 10),
    expected_delivery_date: salesOrder?.expected_delivery_date || '',
    price_list_id: salesOrder?.price_list_id || customer?.price_list_id || '',
    notes: salesOrder?.notes || '',
    lines: salesOrder?.lines || [emptyLine],
  });

  const setLine = (index, key, value) => {
    const lines = [...data.lines];
    lines[index] = { ...lines[index], [key]: value };
    setData('lines', lines);
  };

  const resolvePrice = async (index) => {
    const line = data.lines[index];
    if (!line.item_id) return;

    const { data: result } = await axios.get(route('apps.price-lists.resolve-price'), {
      params: {
        item_id: line.item_id,
        qty: line.qty_sold,
        uom_id: line.uom_id,
        date: data.document_date,
        price_list_id: data.price_list_id,
      },
    });

    const lines = [...data.lines];
    lines[index] = {
      ...lines[index],
      unit_price: result.price || 0,
      discount_percent: result.discount_percent || 0,
    };
    setData('lines', lines);
  };

  const save = (e) => {
    e.preventDefault();
    if (isEdit) {
      put(route('apps.sales-orders.update', salesOrder.id));
      return;
    }

    post(route('apps.customers.sales-orders.store', customer.id));
  };

  return (
    <>
      <Head title='Sales Order Form' />
      <Card
        title={`${isEdit ? 'Edit' : 'Create'} Sales Order`}
        form={save}
        footer={(
          <div className='flex flex-wrap items-center gap-2'>
            <Button type='submit' label='Save Draft' variant='gray' disabled={processing} />
            <Button
              type='button'
              label='Add Line'
              variant='orange'
              onClick={() => setData('lines', [...data.lines, { ...emptyLine }])}
              disabled={processing}
            />
            <Link
              href={route('apps.customers.show', customer?.id || salesOrder?.customer_id)}
              className='px-4 py-2 flex items-center gap-2 rounded-lg text-sm font-semibold bg-white text-rose-500 hover:bg-gray-100 border dark:bg-gray-950 dark:border-gray-800 dark:hover:bg-gray-900'
            >
              Cancel
            </Link>
          </div>
        )}
      >
        <div className='space-y-4'>
          <div className='text-sm text-gray-700 dark:text-gray-300'>
            Customer: <b>{customer?.customer_name}</b>
          </div>

          <div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
            <Input type='date' label='Document Date' value={data.document_date} onChange={(e) => setData('document_date', e.target.value)} />
            <Input
              type='date'
              label='Expected Delivery Date'
              value={data.expected_delivery_date}
              onChange={(e) => setData('expected_delivery_date', e.target.value)}
            />
          </div>

          <div className='overflow-x-auto'>
            <table className='w-full border text-sm'>
              <thead className='bg-gray-50 dark:bg-gray-900'>
                <tr>
                  <th className='border p-2 text-left'>Item</th>
                  <th className='border p-2 text-left'>Qty</th>
                  <th className='border p-2 text-left'>Price</th>
                </tr>
              </thead>
              <tbody>
                {data.lines.map((line, index) => (
                  <tr key={index}>
                    <td className='border p-2'>
                      <select
                        value={line.item_id}
                        onChange={(e) => setLine(index, 'item_id', e.target.value)}
                        onBlur={() => resolvePrice(index)}
                        className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'
                      >
                        <option value=''>Pilih Item</option>
                        {items.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className='border p-2'>
                      <input
                        type='number'
                        value={line.qty_sold}
                        onChange={(e) => setLine(index, 'qty_sold', e.target.value)}
                        className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'
                      />
                    </td>
                    <td className='border p-2'>
                      <input
                        type='number'
                        value={line.unit_price}
                        onChange={(e) => setLine(index, 'unit_price', e.target.value)}
                        className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {errors.lines && <small className='text-xs text-red-500'>{errors.lines}</small>}
        </div>
      </Card>
    </>
  );
}

Page.layout = (page) => <AppLayout children={page} />;
