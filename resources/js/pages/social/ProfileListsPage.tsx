import { Head, Link } from '@inertiajs/react';

import { CollectionTabs } from '@/modules/lists/CollectionTabs';
import { RecordListPreview } from '@/modules/lists/RecordListPreview';
import type { ListPreview } from '@/modules/lists/types';
import { CollectionHeading } from '@/modules/social/CollectionHeading';
import { Pagination } from '@/modules/social/Pagination';
import type { Pagination as Page, Person } from '@/modules/social/types';

export function ProfileListsPage({
    person,
    lists,
    listCount,
}: {
    person: Person;
    lists: Page<ListPreview>;
    listCount: number;
}) {
    return (
        <>
            <Head title={`${person.name}’s lists`} />
            <CollectionHeading title={`${person.name}’s collection`}>
                <p>@{person.handle}</p>
            </CollectionHeading>
            <CollectionTabs
                active="lists"
                person={person}
                listCount={listCount}
            />
            <section aria-labelledby="profile-lists-title">
                <div className="section-heading lists-section-heading">
                    <h2 id="profile-lists-title">
                        {person.name}’s lists{' '}
                        <span className="muted">({listCount})</span>
                    </h2>
                </div>
                {lists.data.length > 0 ? (
                    <div className="collection-previews list-cards">
                        {lists.data.map((list) => (
                            <RecordListPreview key={list.id} list={list} />
                        ))}
                    </div>
                ) : (
                    <div className="list-empty">
                        <h3>No shared lists to explore yet.</h3>
                        <p>Lists shared with you will appear here.</p>
                        <Link href={person.profileUrl}>
                            Back to {person.name}’s collection
                        </Link>
                    </div>
                )}
            </section>
            <Pagination {...lists} />
        </>
    );
}
