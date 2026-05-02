export default function Tab({ data }) { return <pre className='text-xs overflow-auto bg-gray-50 p-3 rounded'>{JSON.stringify(data, null, 2)}</pre>; }
