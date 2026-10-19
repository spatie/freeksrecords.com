import { createInertiaApp } from '@inertiajs/react';
import { initializeRecordHistory } from '@/lib/record-history';

if (typeof window !== 'undefined') initializeRecordHistory();

void createInertiaApp({
    title: (title) => title || 'Plaat Club',
    strictMode: true,
    progress: {
        color: '#4B5563',
    },
});
