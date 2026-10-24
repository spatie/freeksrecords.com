import { Link, usePage } from '@inertiajs/react';

import type { Person, SocialProps } from '@/modules/social/types';
import '../../../css/record-lists.css';

export function CollectionTabs({
    active,
    person,
    listCount,
}: {
    active: 'records' | 'lists';
    person?: Person;
    listCount?: number;
}) {
    const user = usePage<SocialProps>().props.auth.user;
    const collector = person ?? user;
    if (!collector) return null;
    const owner = user?.id === collector.id;

    return (
        <nav
            className="collection-tabs"
            aria-label={
                owner ? 'My collection' : `${collector.name}’s collection`
            }
        >
            <Link
                href={collector.profileUrl}
                aria-current={active === 'records' ? 'page' : undefined}
            >
                Records
            </Link>
            <Link
                href={owner ? '/lists' : `${collector.profileUrl}/lists`}
                aria-current={active === 'lists' ? 'page' : undefined}
            >
                Lists{listCount !== undefined && ` (${listCount})`}
            </Link>
        </nav>
    );
}
