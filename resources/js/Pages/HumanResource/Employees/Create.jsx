import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, router, useForm } from '@inertiajs/react';

export default function Create() {
  const { data, setData, post, errors, processing, reset } = useForm({
    employee_code: '',
    full_name: '',
    nik: '',
    email: '',
    phone: '',
    create_login: false,
    login_email: '',
    login_password: '',
  });

  const submit = (e) => {
    e.preventDefault();
    post('/apps/human-resource/employees');
  };

  const closeForm = () => router.get('/apps/human-resource/employees');

  return (
    <>
      <Head title='Create Employee' />
      <Card
        title='Create Employee'
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
          <Input label='Code' value={data.employee_code} onChange={(e) => setData('employee_code', e.target.value)} errors={errors.employee_code} />
          <Input label='Nama' value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} errors={errors.full_name} />
          <Input label='NIK' value={data.nik} onChange={(e) => setData('nik', e.target.value)} errors={errors.nik} />
          <Input label='Email' value={data.email} onChange={(e) => setData('email', e.target.value)} errors={errors.email} />
          <Input label='Phone' value={data.phone} onChange={(e) => setData('phone', e.target.value)} errors={errors.phone} />
        </div>

        <div className='mt-4 border-t border-gray-200 pt-4 dark:border-gray-800'>
          <label className='inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200'>
            <input
              type='checkbox'
              checked={data.create_login}
              onChange={(e) => setData('create_login', e.target.checked)}
              className='rounded border-gray-300 text-indigo-600 focus:ring-indigo-500'
            />
            Buat akun login untuk employee ini
          </label>

          {data.create_login && (
            <div className='mt-3 grid grid-cols-1 gap-3 md:grid-cols-2'>
              <Input label='Login Email' value={data.login_email} onChange={(e) => setData('login_email', e.target.value)} errors={errors.login_email} />
              <Input label='Login Password' type='password' value={data.login_password} onChange={(e) => setData('login_password', e.target.value)} errors={errors.login_password} />
            </div>
          )}
        </div>
      </Card>
    </>
  );
}

Create.layout = (page) => <AppLayout children={page} />;
