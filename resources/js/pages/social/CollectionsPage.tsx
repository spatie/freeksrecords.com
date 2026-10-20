import { Head, Link, usePage } from '@inertiajs/react';

import { CollectionPreview } from '@/modules/social/CollectionPreview';
import { Pagination } from '@/modules/social/Pagination';
import type { CollectionsProps, SocialProps } from '@/modules/social/types';

export function CollectionsPage({ collectors }: CollectionsProps) {
    const user = usePage<SocialProps>().props.auth.user;
    return (
        <>
            <Head title="Public collections" />
            <div className="page-heading">
                <p className="page-eyebrow">Public</p>
                <h1>Public collections.</h1>
                <p>Collections shared with everyone.</p>
            </div>
            <div className="collection-previews">
                {collectors.data.length ? (
                    collectors.data.map((collector) => (
                        <CollectionPreview
                            key={collector.id}
                            collector={collector}
                        />
                    ))
                ) : (
                    <section className="empty panel">
                        <h2>No public collections yet</h2>
                        <p>
                            Collections are private by default. They’ll appear
                            here when their owners choose Everyone.
                        </p>
                        <Link href={user ? '/settings' : '/register'}>
                            {user
                                ? 'Manage my collection’s visibility'
                                : 'Create account'}
                        </Link>
                    </section>
                )}
            </div>
            <Pagination {...collectors} />
        </>
    );
}
