import Form from './Form';

export default function Create({ categories }) {
  return <Form title='Tambah Regulatory Document' action={route('apps.master-data.regulatory-documents.store')} categories={categories} />;
}
