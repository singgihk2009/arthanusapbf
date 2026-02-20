import Create from './Create';
import { usePage } from '@inertiajs/react';

export default function Edit() {
    const { entry, lines } = usePage().props;

    return <Create initial={{ ...entry, lines }} isEdit />;
}
