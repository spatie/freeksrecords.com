import { Head, Link, usePage } from '@inertiajs/react';

import { HeroArtwork } from '@/modules/social/HeroArtwork';
import type { HomeProps, SocialProps } from '@/modules/social/types';

export function HomePage({ artwork }: HomeProps) {
    const user = usePage<SocialProps>().props.auth.user;

    return (
        <>
            <Head title="Good taste is contagious." />
            <section className="landing-hero" aria-labelledby="landing-title">
                <h1 id="landing-title">Good taste</h1>
                <p className="landing-bottom-type">is contagious.</p>
                {artwork.length > 0 ? (
                    <div
                        className="landing-records"
                        aria-label="Records from public collections"
                    >
                        <HeroArtwork records={artwork} moving />
                    </div>
                ) : (
                    <p className="landing-empty">
                        Every great collection starts somewhere.
                    </p>
                )}
                <Link
                    className="landing-enter"
                    href={user ? user.profileUrl : '/register'}
                >
                    Step
                    <br />
                    inside ↗
                </Link>
                <p className="landing-signoff">
                    For the love of records. And people.
                </p>
            </section>
        </>
    );
}
