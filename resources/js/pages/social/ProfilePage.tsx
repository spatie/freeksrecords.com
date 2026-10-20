import { Head, Link, usePage } from '@inertiajs/react';

import { CollectionSharing } from '@/modules/social/CollectionSharing';
import { ProfileShelf } from '@/modules/social/ProfileShelf';
import type { ProfileProps, SocialProps } from '@/modules/social/types';

export function ProfilePage({ person, records, visible }: ProfileProps) {
    const user = usePage<SocialProps>().props.auth.user;
    const owner = user?.id === person.id;
    return (
        <>
            <Head title={`${person.name}’s collection`} />
            <div className="collector-heading">
                <h1>{person.name}’s collection</h1>
                <p>@{person.handle}</p>
                {owner && user ? (
                    <CollectionSharing key={person.id} user={user} />
                ) : (
                    user?.verified && (
                        <Link
                            className="collection-visibility"
                            href={`/friends?q=${encodeURIComponent(person.handle)}`}
                        >
                            Manage friendship
                        </Link>
                    )
                )}
            </div>
            {!visible ? (
                <section className="empty panel">
                    <h2>A private collection</h2>
                    <p>
                        This collection isn’t shared with you. Only the
                        collector’s name and username are public.
                    </p>
                </section>
            ) : records?.length ? (
                <ProfileShelf key={person.id} records={records} />
            ) : (
                <section className="empty panel">
                    <h2>No records in this collection yet.</h2>
                    {owner && (
                        <p>
                            {user?.importedAt
                                ? 'Add records on Discogs, then sync your collection in Settings.'
                                : 'Import your Discogs collection to add your records.'}
                        </p>
                    )}
                    {owner && (
                        <Link
                            className="button"
                            href={user?.importedAt ? '/settings' : '/feed'}
                        >
                            {user?.importedAt
                                ? 'Manage Discogs connection'
                                : 'Import from Discogs'}
                        </Link>
                    )}
                </section>
            )}
        </>
    );
}
