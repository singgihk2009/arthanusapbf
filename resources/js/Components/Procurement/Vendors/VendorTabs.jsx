import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import OverviewTab from './tabs/OverviewTab';
import ProfileTab from './tabs/ProfileTab';
import LegalTab from './tabs/LegalTab';
import ContactsTab from './tabs/ContactsTab';
import DocumentsTab from './tabs/DocumentsTab';
import PurchaseOrdersTab from './tabs/PurchaseOrdersTab';
import ReceivingsTab from './tabs/ReceivingsTab';
import InvoicesTab from './tabs/InvoicesTab';
import PaymentsTab from './tabs/PaymentsTab';
import LedgerTab from './tabs/LedgerTab';
import AuditLogTab from './tabs/AuditLogTab';

const tabs=[['overview','Overview'],['profile','Profile'],['legal','Legal'],['contacts','Contacts'],['documents','Documents'],['purchase-orders','PO'],['receivings','Receivings'],['invoices','Invoices'],['payments','Payments'],['ledger','Ledger'],['audit-logs','Audit Log']];
const components={'overview':OverviewTab,'profile':ProfileTab,'legal':LegalTab,'contacts':ContactsTab,'documents':DocumentsTab,'purchase-orders':PurchaseOrdersTab,'receivings':ReceivingsTab,'invoices':InvoicesTab,'payments':PaymentsTab,'ledger':LedgerTab,'audit-logs':AuditLogTab};

export default function VendorTabs({vendor,currentTab}){const [data,setData]=useState({}); const [loading,setLoading]=useState(false);
useEffect(()=>{let c=false; setLoading(true); fetch(`/apps/procurement/vendors/${vendor.id}/${currentTab}`).then(r=>r.json()).then(d=>!c&&setData(d)).finally(()=>!c&&setLoading(false)); return ()=>c=true;},[vendor.id,currentTab]);
const TabComp=useMemo(()=>components[currentTab]||OverviewTab,[currentTab]);
return <div className='bg-white border rounded-lg'>
<div className='flex flex-wrap gap-2 border-b p-3'>{tabs.map(([key,label])=><button key={key} onClick={()=>router.get(`/apps/procurement/vendors/${vendor.id}`,{tab:key},{preserveState:true,preserveScroll:true})} className={`px-3 py-1 rounded ${currentTab===key?'bg-indigo-600 text-white':'bg-gray-100'}`}>{label}</button>)}</div>
<div className='p-4'>{loading?<p className='text-sm text-gray-500'>Loading...</p>:<TabComp data={data} vendor={vendor} />}</div>
</div>}
