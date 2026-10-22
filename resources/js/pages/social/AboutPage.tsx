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
                <p>
                    “Plaat” is Dutch for a record—an album on vinyl. Hence Plaat
                    Club: a place for people who love records.
                </p>
                <div className="about-maker-heading">
                    <img
                        src="/images/freek.webp"
                        alt="Freek Van der Herten"
                        width="400"
                        height="400"
                    />
                    <h2>Made by Freek.</h2>
                </div>
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
                    <a href="https://flareapp.io">
                        <strong>Flare ↗</strong>
                        <span>
                            Error tracking and performance monitoring we build
                            at Spatie for Laravel, PHP, and JavaScript.
                        </span>
                    </a>
                    <a href="https://ohdear.app">
                        <strong>Oh Dear ↗</strong>
                        <span>
                            The website monitoring service I run with Mattias
                            Geniar. Uptime, broken links, certificates, and
                            more.
                        </span>
                    </a>
                    <a href="https://mailcoach.app">
                        <strong>Mailcoach ↗</strong>
                        <span>
                            The email marketing platform we build at Spatie for
                            newsletters, automations, and transactional email.
                        </span>
                    </a>
                    <a href="https://spatie.be">
                        <strong>Spatie ↗</strong>
                        <span>
                            The software company I co-own in Antwerp. We build
                            Laravel products and maintain hundreds of open
                            source packages.
                        </span>
                    </a>
                </nav>
                <p>
                    Questions or a good record to recommend?{' '}
                    <a href="mailto:freek@spatie.be">Get in touch.</a>
                </p>
                <p>
                    This application uses Discogs’ API but is not affiliated
                    with, sponsored or endorsed by Discogs. ‘Discogs’ is a
                    trademark of Zink Media, LLC.
                </p>
                <nav className="about-legal" aria-label="Legal information">
                    <Link href="/privacy">Privacy</Link>
                </nav>
            </article>
        </>
    );
}
