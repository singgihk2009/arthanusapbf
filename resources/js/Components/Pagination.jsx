import React from 'react';
import { Link } from '@inertiajs/react';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-react';

export default function Pagination({ links }) {
    const navStyle = 'p-1 text-sm border rounded-md bg-white text-gray-500 hover:bg-gray-100 dark:bg-gray-950 dark:text-gray-400 dark:hover:bg-gray-900 dark:border-gray-900';

    const resolveType = (label) => {
        if (label.includes('Previous') || label.includes('pagination.previous') || label.includes('&laquo;')) {
            return 'previous';
        }

        if (label.includes('Next') || label.includes('pagination.next') || label.includes('&raquo;')) {
            return 'next';
        }

        return 'number';
    };

    return (
        <ul className="mt-2 flex items-center justify-end gap-1 lg:mt-5">
            {links.map((item, i) => {
                if (!item.url) {
                    return null;
                }

                const type = resolveType(item.label);

                if (type === 'previous') {
                    return (
                        <Link key={i} className={navStyle} href={item.url} preserveScroll>
                            <IconChevronLeft size={20} strokeWidth={1.5} />
                        </Link>
                    );
                }

                if (type === 'next') {
                    return (
                        <Link key={i} className={navStyle} href={item.url} preserveScroll>
                            <IconChevronRight size={20} strokeWidth={1.5} />
                        </Link>
                    );
                }

                return (
                    <Link
                        key={i}
                        className={`px-2 py-1 text-sm border rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-900 dark:border-gray-900 ${item.active ? 'bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-50' : 'bg-white dark:bg-gray-950'}`}
                        href={item.url}
                        preserveScroll
                    >
                        {item.label}
                    </Link>
                );
            })}
        </ul>
    );
}
