import { Head, Link } from '@inertiajs/react';

import { CollectionPreview } from '@/modules/social/CollectionPreview';
import { HeroArtwork } from '@/modules/social/HeroArtwork';
import type { HomeProps } from '@/modules/social/types';

export function HomePage({ collectors, artwork }: HomeProps) {
    return (
        <>
            <Head title="Your records. In good company." />
            <section className="landing-hero">
                <div className="landing-copy">
                    <h1>
                        Your records.
                        <br />
                        <em>In good company.</em>
                    </h1>
                </div>
                <div className="landing-invitation">
                    <p className="lede">
                        Bring your Discogs collection.
                        <br />
                        See what your friends bring home.
                    </p>
                    <div className="landing-actions">
                        <Link className="button" href="/register">
                            Join the club <span aria-hidden="true">↗</span>
                        </Link>
                        <Link href="/collections">Take a look around →</Link>
                    </div>
                    <p className="landing-note">For the love of records.</p>
                </div>
                {artwork.length > 0 && (
                    <div className="landing-records">
                        <p className="landing-sleeve-note">
                            A few finds from public collections.
                            <span aria-hidden="true">↙</span>
                        </p>
                        <HeroArtwork records={artwork} />
                    </div>
                )}
            </section>
            <section
                className="landing-story"
                aria-label="How Plaat Club works"
            >
                <div>
                    <span className="landing-step" aria-hidden="true">
                        01 /
                    </span>
                    <h2>Your collection, with a little more soul.</h2>
                    <p>
                        Connect Discogs and give your records a home you can
                        browse, take off the shelf, and get lost in.
                    </p>
                </div>
                <div>
                    <span className="landing-step" aria-hidden="true">
                        02 /
                    </span>
                    <h2>Keep up with the good finds.</h2>
                    <p>
                        See the records your friends add, as they add them.
                        Leave an appreciation. Start a conversation.
                    </p>
                </div>
                <div>
                    <span className="landing-step" aria-hidden="true">
                        03 /
                    </span>
                    <h2>Let people in. Or keep it yours.</h2>
                    <p>
                        Share with everyone, just your friends, or only you.
                        Your collection. Your call.
                    </p>
                </div>
            </section>
            <section
                className="public-collections"
                aria-labelledby="collections-title"
            >
                <div className="section-heading">
                    <div>
                        <h2 id="collections-title">Public collections</h2>
                        <p>A good place to find your people.</p>
                    </div>
                    <Link href="/collections">Browse them all →</Link>
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
