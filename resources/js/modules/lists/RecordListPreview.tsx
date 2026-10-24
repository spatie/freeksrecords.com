import { Link } from '@inertiajs/react';
import { ArrowUpRight, Disc3 } from 'lucide-react';

import type { ListPreview } from '@/modules/lists/types';
import { VisibilityBadge } from '@/modules/social/VisibilityPicker';
import '../../../css/record-lists.css';

export function RecordListPreview({
    list,
    showOwner = false,
}: {
    list: ListPreview;
    showOwner?: boolean;
}) {
    const href = `/lists/${list.publicId}`;

    return (
        <article className="collection-preview list-card">
            <div className="collection-preview-covers">
                {list.covers.length > 0 ? (
                    list.covers.map((cover) => (
                        <Link
                            key={cover}
                            href={href}
                            aria-label={`Open ${list.name}`}
                        >
                            <img
                                src={cover}
                                alt=""
                                width={180}
                                height={180}
                                loading="lazy"
                            />
                        </Link>
                    ))
                ) : (
                    <span className="list-card-placeholder" aria-hidden="true">
                        <Disc3 size={34} />
                    </span>
                )}
            </div>
            <div className="collection-preview-caption">
                <Link className="collection-preview-link" href={href}>
                    <h3>{list.name}</h3>
                </Link>
                {showOwner && (
                    <Link
                        className="list-card-owner"
                        href={`${list.owner.profileUrl}/lists`}
                    >
                        by {list.owner.name} · @{list.owner.handle}
                    </Link>
                )}
                {list.description && (
                    <p className="list-card-description">{list.description}</p>
                )}
                <Link
                    className="collection-preview-link list-card-footer"
                    href={href}
                    aria-label={`Open ${list.name}`}
                >
                    <VisibilityBadge value={list.visibility} />
                    <span>
                        {list.count} {list.count === 1 ? 'record' : 'records'}
                    </span>
                    <ArrowUpRight size={20} aria-hidden="true" />
                </Link>
            </div>
        </article>
    );
}
