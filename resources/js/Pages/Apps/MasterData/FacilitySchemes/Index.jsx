import AppLayout from '@/Layouts/AppLayout';

export default function Index({ data }) {
  return <AppLayout title="Facility Schemes"><div className="p-4">Facility schemes loaded: {data?.total ?? 0}</div></AppLayout>;
}
