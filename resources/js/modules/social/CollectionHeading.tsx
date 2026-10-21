import type { ReactNode } from 'react';

import { CollectionSharing } from '@/modules/social/CollectionSharing';
import type { Viewer } from '@/modules/social/types';

export function CollectionHeading({
    title,
    user,
    id,
    children,
}: {
    title: string;
    user?: Viewer | null;
    id?: string;
    children?: ReactNode;
}) {
    return (
        <div className="collector-heading">
            <h1 id={id}>{title}</h1>
            {user && <CollectionSharing key={user.id} user={user} />}
            {children}
        </div>
    );
}
