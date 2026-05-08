import React from 'react'
import { Head, usePage, useForm } from '@inertiajs/react'
import Card from '@/Components/Card'
import AppLayout from '@/Layouts/AppLayout'
import { IconUsersPlus, IconPencilPlus } from '@tabler/icons-react'
import Input from '@/Components/Input'
import Button from '@/Components/Button'
import Checkbox from '@/Components/Checkbox'
import toast from 'react-hot-toast'

export default function Edit() {
    const { roles, user, warehouses, assignedWarehouseIds, defaultWarehouseId } = usePage().props;
    const {data, setData, post, errors} = useForm({ name:user.name, email:user.email, password:'', password_confirmation:'', selectedRoles:user.roles.map(r=>r.name), warehouse_ids:assignedWarehouseIds ?? [], default_warehouse_id:defaultWarehouseId ?? '', _method:'PUT' });

    const toggle = (key, value) => { let items=[...data[key]]; if (items.includes(value)) items = items.filter((n)=>n!==value); else items.push(value); setData(key, items); }
    const isStockkeeper = data.selectedRoles.some((r) => r.toLowerCase() === 'stockkeeper');
    const stockkeeperWarehouseInvalid = isStockkeeper && data.warehouse_ids.length === 0;
    const updateUser = (e) => { e.preventDefault(); if (stockkeeperWarehouseInvalid) return; post(route('apps.users.update', user.id), { onSuccess: () => toast.success('Data berhasil disimpan') }); }

    return (<><Head title={'Ubah Data Pengguna'}/><Card title={'Ubah Data Pengguna'} icon={<IconUsersPlus size={20} strokeWidth={1.5}/>} footer={<Button type={'submit'} label={'Simpan'} icon={<IconPencilPlus size={20} strokeWidth={1.5}/>} variant={'gray'} disabled={stockkeeperWarehouseInvalid} className={stockkeeperWarehouseInvalid ? 'opacity-50 cursor-not-allowed' : ''} />} form={updateUser}>
        <div className='mb-4 flex gap-4'><div className='w-1/2'><Input type='text' label='Nama Pengguna' value={data.name} onChange={e=>setData('name',e.target.value)} errors={errors.name}/></div><div className='w-1/2'><Input type='email' label='Email Pengguna' value={data.email} disabled /></div></div>
        <div className='mb-4 flex gap-4'><div className='w-1/2'><Input type='password' label='Kata Sandi' value={data.password} onChange={e=>setData('password',e.target.value)} errors={errors.password}/></div><div className='w-1/2'><Input type='password' label='Konfirmasi Kata Sandi' value={data.password_confirmation} onChange={e=>setData('password_confirmation',e.target.value)} errors={errors.password_confirmation}/></div></div>
        <div className='p-4 rounded-lg border bg-gray-100'><div className='font-semibold text-sm mb-2'>Akses Group</div>{roles.map((role, i) => <Checkbox key={i} label={role.name} value={role.name} onChange={()=>toggle('selectedRoles', role.name)} defaultChecked={data.selectedRoles.includes(role.name)} />)}</div>
        <div className='p-4 rounded-lg border bg-gray-100 mt-4'><div className='font-semibold text-sm mb-2'>Assigned Warehouses</div>{warehouses.map((wh) => <Checkbox key={wh.id} label={`${wh.code} - ${wh.name}`} value={String(wh.id)} onChange={()=>toggle('warehouse_ids', String(wh.id))} defaultChecked={data.warehouse_ids.includes(String(wh.id))} />)}
            <div className='mt-3'><label className='text-sm font-medium'>Default Warehouse</label><select className='mt-1 w-full border rounded px-3 py-2' value={data.default_warehouse_id} onChange={(e)=>setData('default_warehouse_id', e.target.value)}><option value=''>Pilih default warehouse</option>{warehouses.filter((wh)=>data.warehouse_ids.includes(String(wh.id))).map((wh)=><option key={wh.id} value={wh.id}>{wh.code} - {wh.name}</option>)}</select></div>
            {errors.warehouse_ids && <div className='text-xs text-red-500 mt-2'>{errors.warehouse_ids}</div>}
            {errors.default_warehouse_id && <div className='text-xs text-red-500 mt-2'>{errors.default_warehouse_id}</div>}
        </div>
    </Card></>)
}
Edit.layout = page => <AppLayout children={page}/>
