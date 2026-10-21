import { router } from '@inertiajs/react';
import { useEffect } from 'react';

import { SocialRecordView } from '@/modules/social/SocialRecordView';
import type { RecordProps } from '@/modules/social/types';

export function RecordPage(props: RecordProps) {
    useEffect(() => {
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previous;
        };
    }, []);

    return (
        <SocialRecordView
            data={props}
            onBack={() => router.visit(props.collectionUrl)}
        />
    );
}
