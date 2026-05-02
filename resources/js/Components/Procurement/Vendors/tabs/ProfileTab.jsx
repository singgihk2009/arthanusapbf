import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';

const fields = ['vendor_name','vendor_type','address','postal_code','village','district','city','province','phone','fax','email','npwp','nib_number','company_license_number','cdakb_cpakb_certificate_number'];

export default function Tab({ data, vendor }) {
  const { vendor: profile } = data;
  const { data: form, setData, put, delete: destroy, processing, errors, reset } = useForm({
    vendor_name: '', vendor_type: '', address: '', postal_code: '', village: '', district: '', city: '', province: '',
    phone: '', fax: '', email: '', npwp: '', nib_number: '', company_license_number: '', cdakb_cpakb_certificate_number: '',
  });

  useEffect(() => {
    if (!profile) return;
    fields.forEach((key) => setData(key, profile[key] ?? ''));
  }, [profile]);

  const submit = (e) => {
    e.preventDefault();
    put(`/apps/procurement/vendors/${vendor.id}/profile`, { preserveScroll: true });
  };

  const clearProfile = () => {
    destroy(`/apps/procurement/vendors/${vendor.id}/profile`, { preserveScroll: true });
    reset();
  };

  return <form onSubmit={submit} className='grid grid-cols-1 md:grid-cols-2 gap-3'>
    {fields.map((key) => <div key={key}>
      <label className='text-sm font-medium capitalize'>{key.replaceAll('_', ' ')}</label>
      <input value={form[key]} onChange={e => setData(key, e.target.value)} className='w-full mt-1 rounded border-gray-300' />
      {errors[key] && <p className='text-xs text-red-500 mt-1'>{errors[key]}</p>}
    </div>)}
    <div className='md:col-span-2 flex gap-2 pt-2'>
      <button disabled={processing} className='px-4 py-2 rounded bg-indigo-600 text-white'>Simpan Profile</button>
      <button type='button' onClick={clearProfile} disabled={processing} className='px-4 py-2 rounded bg-red-600 text-white'>Hapus Data Profile</button>
    </div>
  </form>;
}
