import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';

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
                    <span>
                        Step
                        <br />
                        inside{' '}
                        <ArrowUpRight aria-hidden="true" strokeWidth={3} />
                    </span>
                </Link>
                <Link className="landing-about" href="/about">
                    About <ArrowUpRight aria-hidden="true" />
                </Link>
            </section>
        </>
    );
}
