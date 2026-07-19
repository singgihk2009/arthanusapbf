import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, router, useForm } from '@inertiajs/react';

const toDateInputValue = (value) => {
  if (!value) return '';
  const text = String(value);
  return text.length >= 10 ? text.slice(0, 10) : text;
};

export default function Edit({ employee, departments = [], positions = [] }) {
  const { data, setData, put, errors, processing } = useForm({
    employee_code: employee?.employee_code || '',
    full_name: employee?.full_name || '',
    nik: employee?.nik || '',
    gender: employee?.gender || '',
    birth_place: employee?.birth_place || '',
    birth_date: toDateInputValue(employee?.birth_date),
    email: employee?.email || '',
    phone: employee?.phone || '',
    address: employee?.address || '',
    department_id: employee?.department_id ? String(employee.department_id) : '',
    position_id: employee?.position_id ? String(employee.position_id) : '',
    join_date: toDateInputValue(employee?.join_date),
    employment_status: employee?.employment_status || '',
    is_active: Boolean(employee?.is_active ?? true),
  });

  const submit = (event) => {
    event.preventDefault();
    put(route('apps.human-resource.employees.update', employee.id), {
      onSuccess: () => router.get(route('apps.human-resource.employees.show', employee.id)),
    });
  };

  return (
    <>
      <Head title={`Edit ${employee?.full_name || 'Employee'}`} />
      <Card
        title={`Edit Employee - ${employee?.full_name || '-'}`}
        form={submit}
        footer={(
          <div className='flex flex-wrap items-center gap-2'>
            <Button type='submit' label='Simpan Perubahan' variant='gray' disabled={processing} />
            <Button type='button' label='Kembali' variant='orange' onClick={() => router.get(route('apps.human-resource.employees.show', employee.id))} disabled={processing} />
          </div>
        )}
      >
        <div className='grid grid-cols-1 gap-4 md:grid-cols-2'>
          <Input label='Employee Code' value={data.employee_code} onChange={(e) => setData('employee_code', e.target.value)} errors={errors.employee_code} />
          <Input label='Full Name' value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} errors={errors.full_name} />
          <Input label='NIK' value={data.nik} onChange={(e) => setData('nik', e.target.value)} errors={errors.nik} />
          <div>
            <label className='mb-1 block text-sm text-gray-600'>Gender</label>
            <select value={data.gender} onChange={(e) => setData('gender', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'>
              <option value=''>-</option>
              <option value='male'>Male</option>
              <option value='female'>Female</option>
            </select>
            {errors.gender && <small className='text-xs text-red-500'>{errors.gender}</small>}
          </div>
          <Input label='Birth Place' value={data.birth_place} onChange={(e) => setData('birth_place', e.target.value)} errors={errors.birth_place} />
          <Input label='Birth Date' type='date' value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} errors={errors.birth_date} />
          <Input label='Email' value={data.email} onChange={(e) => setData('email', e.target.value)} errors={errors.email} />
          <Input label='Phone' value={data.phone} onChange={(e) => setData('phone', e.target.value)} errors={errors.phone} />
          <div>
            <label className='mb-1 block text-sm text-gray-600'>Department</label>
            <select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'>
              <option value=''>-</option>
              {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
            </select>
            {errors.department_id && <small className='text-xs text-red-500'>{errors.department_id}</small>}
          </div>
          <div>
            <label className='mb-1 block text-sm text-gray-600'>Position / Jabatan Dokumen</label>
            <select value={data.position_id} onChange={(e) => setData('position_id', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'>
              <option value=''>-</option>
              {positions.map((position) => <option key={position.id} value={position.id}>{position.name}</option>)}
            </select>
            {errors.position_id && <small className='text-xs text-red-500'>{errors.position_id}</small>}
          </div>
          <Input label='Join Date' type='date' value={data.join_date} onChange={(e) => setData('join_date', e.target.value)} errors={errors.join_date} />
          <Input label='Employment Status' value={data.employment_status} onChange={(e) => setData('employment_status', e.target.value)} errors={errors.employment_status} />
          <div className='md:col-span-2'>
            <label className='mb-1 block text-sm text-gray-600'>Address</label>
            <textarea value={data.address} onChange={(e) => setData('address', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' rows={3} />
            {errors.address && <small className='text-xs text-red-500'>{errors.address}</small>}
          </div>
          <label className='inline-flex items-center gap-2 text-sm text-gray-700'>
            <input type='checkbox' checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className='rounded border-gray-300 text-indigo-600 focus:ring-indigo-500' />
            Employee Active
          </label>
        </div>
      </Card>
    </>
  );
}

Edit.layout = (page) => <AppLayout children={page} />;
