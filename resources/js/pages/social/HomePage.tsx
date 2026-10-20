import { Head, Link } from '@inertiajs/react';

import { CollectionPreview } from '@/modules/social/CollectionPreview';
import { HeroArtwork } from '@/modules/social/HeroArtwork';
import type { HomeProps } from '@/modules/social/types';

export function HomePage({ collectors, heroCovers }: HomeProps) {
    return (
        <>
            <Head title="A shelf for every record" />
            <section className="landing-hero">
                <div className="landing-copy">
                    <h1>A shelf for every record.</h1>
                    <p className="lede">
                        Keep collecting on Discogs. Sync your collection here
                        and choose who gets to see it.
                    </p>
                    <div className="landing-actions">
                        <Link className="button" href="/register">
                            Create account
                        </Link>
                        <Link href="/collections">Browse collections</Link>
                    </div>
                    <p className="landing-note">
                        Your collection is private until you share it.
                        <br />
                        Already a member? <Link href="/login">Sign in</Link>
                    </p>
                </div>
                <HeroArtwork covers={heroCovers} />
            </section>
            <section
                className="public-collections"
                aria-labelledby="collections-title"
            >
                <div className="section-heading">
                    <div>
                        <h2 id="collections-title">Public collections</h2>
                    </div>
                    <Link href="/collections">All public collections</Link>
                </div>
                <div className="collection-previews">
                    {collectors.length ? (
                        collectors.map((collector) => (
                            <CollectionPreview
                                key={collector.id}
                                collector={collector}
                            />
                        ))
                    ) : (
                        <p className="muted">No public collections yet</p>
                    )}
                </div>
            </section>
        </>
    );
}
