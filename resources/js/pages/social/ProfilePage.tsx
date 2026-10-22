import { Head, Link, usePage } from '@inertiajs/react';

import { CollectionHeading } from '@/modules/social/CollectionHeading';
import { ProfileShelf } from '@/modules/social/ProfileShelf';
import type { ProfileProps, SocialProps } from '@/modules/social/types';

export function ProfilePage({
    person,
    records,
    visible,
    groups,
    browse,
}: ProfileProps) {
    const user = usePage<SocialProps>().props.auth.user;
    const owner = user?.id === person.id;
    return (
        <>
            <Head title={`${person.name}’s collection`} />
            <CollectionHeading
                title={
                    owner ? 'Your collection' : `${person.name}’s collection`
                }
                user={owner ? user : null}
            >
                {!owner && <p>@{person.handle}</p>}
                {!owner && user?.verified && (
                    <Link
                        className="collection-visibility"
                        href={`/friends?q=${encodeURIComponent(person.handle)}`}
                    >
                        Manage friendship
                    </Link>
                )}
            </CollectionHeading>
            {!visible ? (
                <section className="empty panel">
                    <h2>A private collection</h2>
                    <p>
                        This collection isn’t shared with you. Only the
                        collector’s name and username are public.
                    </p>
                </section>
            ) : records && (records.data.length > 0 || browse.query) ? (
                <ProfileShelf
                    key={person.id}
                    records={records}
                    groups={groups}
                    browse={browse}
                />
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
