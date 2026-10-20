import { Link } from '@inertiajs/react';

import type { RecordArtwork } from '@/modules/social/types';

export function HeroArtwork({ records }: { records: RecordArtwork[] }) {
    if (!records.length) return null;

    return (
        <div className="hero-artwork">
            {records.map((record) => (
                <div className="floating-sleeve" key={record.url}>
                    <Link href={record.url}>
                        <img
                            src={record.cover}
                            alt={record.title}
                            width="240"
                            height="240"
                        />
                    </Link>
                </div>
            ))}
        </div>
    );
}
