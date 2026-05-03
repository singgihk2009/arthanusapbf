import Form from './Form';

export default function Edit({ document, categories }) {
  return <Form title='Edit Regulatory Document' action={route('apps.master-data.regulatory-documents.update', document.id)} method='put' document={document} categories={categories} />;
}
