import { Head, Link } from '@inertiajs/react';

export function AboutPage() {
    return (
        <>
            <Head title="About Plaat Club" />
            <article className="legal-document about-document">
                <h1>A place for record people.</h1>
                <p className="about-introduction">
                    Plaat Club brings your Discogs collection and your friends’
                    latest finds together. Connect your collection, choose who
                    can see it, and see what your friends have added—newest
                    first. Appreciate a record, leave a comment, or get a weekly
                    catch-up by email.
                </p>
                <h2>Made by Freek.</h2>
                <p>
                    I’m{' '}
                    <a href="https://freek.dev/about">Freek Van der Herten</a>,
                    co-owner of <a href="https://spatie.be">Spatie</a> in
                    Antwerp, Belgium. I build software, collect records, and
                    make electronic music as Kobus. Plaat Club started with my
                    own collection; now there’s room for yours too.
                </p>
                <p>A few other things I work on:</p>
                <nav className="about-projects" aria-label="Freek’s projects">
                    <a href="https://freek.dev">
                        <strong>freek.dev ↗</strong>
                        <span>
                            Notes on PHP, Laravel, and building software.
                        </span>
                    </a>
                    <a href="https://runbloom.app">
                        <strong>Bloom ↗</strong>
                        <span>
                            A native Mac app for working with coding agents.
                        </span>
                    </a>
                    <a href="https://there-there.app">
                        <strong>There There ↗</strong>
                        <span>The helpdesk we’re building at Spatie.</span>
                    </a>
                </nav>
                <p>
                    Questions or a good record to recommend?{' '}
                    <a href="mailto:freek@spatie.be">Get in touch.</a>
                </p>
                <nav className="about-legal" aria-label="Legal information">
                    <Link href="/privacy">Privacy</Link>
                </nav>
            </article>
        </>
    );
}
