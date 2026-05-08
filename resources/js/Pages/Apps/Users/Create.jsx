import React from 'react'
import { Head, usePage, useForm } from '@inertiajs/react'
import Card from '@/Components/Card'
import AppLayout from '@/Layouts/AppLayout'
import { IconUsersPlus, IconPencilPlus } from '@tabler/icons-react'
import Input from '@/Components/Input'
import Button from '@/Components/Button'
import Checkbox from '@/Components/Checkbox'
import toast from 'react-hot-toast'

export default function Create() {
    const { roles, warehouses } = usePage().props;
    const {data, setData, post, errors} = useForm({ name:'', email:'', password:'', password_confirmation:'', selectedRoles:[], warehouse_ids:[], default_warehouse_id:'' });

    const setSelectedRoles = (e) => {
        let items = [...data.selectedRoles];
        if (items.includes(e.target.value)) items = items.filter((n) => n !== e.target.value); else items.push(e.target.value);
        setData('selectedRoles', items);
    }
    const setSelectedWarehouse = (e) => {
        const value = String(e.target.value);
        let items = [...data.warehouse_ids];
        if (items.includes(value)) items = items.filter((id) => id !== value); else items.push(value);
        setData('warehouse_ids', items);
        if (!items.includes(String(data.default_warehouse_id))) setData('default_warehouse_id', items[0] ?? '');
    }

    const saveUser = (e) => {
        e.preventDefault();
        if (stockkeeperWarehouseInvalid) return;
        post(route('apps.users.store'), { onSuccess: () => toast.success('Data berhasil disimpan') });
    }
    const isStockkeeper = data.selectedRoles.some((r) => r.toLowerCase() === 'stockkeeper');
    const stockkeeperWarehouseInvalid = isStockkeeper && data.warehouse_ids.length === 0;

    return (<><Head title={'Tambah Data Pengguna'}/><Card title={'Tambah Data Pengguna'} icon={<IconUsersPlus size={20} strokeWidth={1.5}/>} footer={<Button type={'submit'} label={'Simpan'} icon={<IconPencilPlus size={20} strokeWidth={1.5}/>} variant={'gray'} disabled={stockkeeperWarehouseInvalid} className={stockkeeperWarehouseInvalid ? 'opacity-50 cursor-not-allowed' : ''} />} form={saveUser}>
        <div className='mb-4 flex gap-4'><div className='w-1/2'><Input type='text' label='Nama Pengguna' value={data.name} onChange={e=>setData('name',e.target.value)} errors={errors.name}/></div><div className='w-1/2'><Input type='email' label='Email Pengguna' value={data.email} onChange={e=>setData('email',e.target.value)} errors={errors.email}/></div></div>
        <div className='mb-4 flex gap-4'><div className='w-1/2'><Input type='password' label='Kata Sandi' value={data.password} onChange={e=>setData('password',e.target.value)} errors={errors.password}/></div><div className='w-1/2'><Input type='password' label='Konfirmasi Kata Sandi' value={data.password_confirmation} onChange={e=>setData('password_confirmation',e.target.value)} /></div></div>
        <div className='p-4 rounded-lg border bg-gray-100'><div className='font-semibold text-sm mb-2'>Akses Group</div>{roles.map((role, i) => <Checkbox label={role.name} value={role.name} onChange={setSelectedRoles} key={i} defaultChecked={data.selectedRoles.includes(role.name)}/>)}{errors.selectedRoles && <div className='text-xs text-red-500 mt-2'>{errors.selectedRoles}</div>}</div>
        <div className='p-4 rounded-lg border bg-gray-100 mt-4'><div className='font-semibold text-sm mb-2'>Assigned Warehouses</div>{warehouses.map((wh) => <Checkbox key={wh.id} label={`${wh.code} - ${wh.name}`} value={String(wh.id)} onChange={setSelectedWarehouse} defaultChecked={data.warehouse_ids.includes(String(wh.id))} />)}{errors.warehouse_ids && <div className='text-xs text-red-500 mt-2'>{errors.warehouse_ids}</div>}
            <div className='mt-3'><label className='text-sm font-medium'>Default Warehouse</label><select className='mt-1 w-full border rounded px-3 py-2' value={data.default_warehouse_id} onChange={(e)=>setData('default_warehouse_id', e.target.value)}><option value=''>Pilih default warehouse</option>{warehouses.filter((wh)=>data.warehouse_ids.includes(String(wh.id))).map((wh)=><option key={wh.id} value={wh.id}>{wh.code} - {wh.name}</option>)}</select>{errors.default_warehouse_id && <div className='text-xs text-red-500 mt-2'>{errors.default_warehouse_id}</div>}</div>
            {isStockkeeper && <p className={`text-xs mt-2 ${stockkeeperWarehouseInvalid ? 'text-red-600 font-semibold' : 'text-gray-500'}`}>{stockkeeperWarehouseInvalid ? 'Stockkeeper harus memilih minimal 1 warehouse sebelum menyimpan.' : 'Role Stockkeeper wajib minimal 1 warehouse.'}</p>}
        </div>
    </Card></>)
}
Create.layout = page => <AppLayout children={page}/>
