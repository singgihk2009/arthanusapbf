import React, { useEffect, useState } from 'react'
import { usePage } from '@inertiajs/react';
import { IconAlignLeft, IconMoon, IconSun } from '@tabler/icons-react'
import AuthDropdown from '@/Components/AuthDropdown';
import Notification from '@/Components/Notification';

export default function Navbar({ toggleSidebar, themeSwitcher, darkMode }) {
    // destruct auth from props
    const { auth } = usePage().props;

    // define state isMobile
    const [isMobile, setIsMobile] = useState(false);

    // define useEffect
    useEffect(() => {
        // define handle resize window
        const handleResize = () => {
          setIsMobile(window.innerWidth <= 768);
        };

        // define event listener
        window.addEventListener('resize', handleResize);

        // call handle resize window
        handleResize();

        // remove event listener
        return () => {
          window.removeEventListener('resize', handleResize);
        };
    }, [])

    return (
        <div className='py-8 px-4 md:px-6 flex justify-between items-center min-w-full sticky top-0 z-20 h-16 border-b bg-white dark:border-gray-900 dark:bg-gray-950'>
            <div className='flex items-center gap-4'>
                <button className='text-gray-700 dark:text-gray-400 hidden md:block' onClick={toggleSidebar}>
                    <IconAlignLeft size={18} strokeWidth={1.5}/>
                </button>
            </div>
            <div className='flex items-center gap-4'>
                <div className='flex flex-row items-center gap-1 border-r-2 border-double px-4 dark:border-gray-900'>
                    <div className='flex flex-row gap-2'>
                        <button className='p-2 rounded-md text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-900 ' onClick={themeSwitcher}>
                           {darkMode ? <IconSun strokeWidth={1.5} size={18}/> : <IconMoon strokeWidth={1.5} size={18}/> }
                        </button>
                        <Notification/>
                    </div>
                </div>
                <AuthDropdown auth={auth} isMobile={isMobile}/>
            </div>
        </div>
    )
}
