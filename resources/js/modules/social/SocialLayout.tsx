import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, type ReactNode } from 'react';

import { legacyCollectionUrl } from '@/lib/record-history';
import { AccountMenu } from '@/modules/social/AccountMenu';
import { CollectionSharing } from '@/modules/social/CollectionSharing';
import { NotificationsPopover } from '@/modules/social/NotificationsPopover';
import { RecordOverlay } from '@/modules/social/RecordOverlay';
import type { SocialProps } from '@/modules/social/types';
import '../../../css/social.css';
import '../../../css/auth.css';
import '../../../css/collection-visibility.css';

export function SocialLayout({ children }: { children: ReactNode }) {
    const { props, url, component } = usePage<SocialProps>();
    const user = props.auth.user;
    const path = url.split(/[?#]/)[0];
    const settingUpCollection =
        user &&
        !user.importedAt &&
        (!user.collectionCount || user.discogsConnected);
    const onboarding = component === 'social/FeedPage' && settingUpCollection;
    const recordOpen =
        component === 'social/RecordPage' || Boolean(props.recordOverlay);
    const layoutRef = useRef<HTMLDivElement>(null);
    const navigationRef = useRef<HTMLElement>(null);
    const indicatorRef = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        const layout = layoutRef.current;
        if (!layout) return;
        let compact = false;
        const update = (event?: Event) => {
            const shelf = event
                ? event.target
                : layout.querySelector(
                      '.collection-stream, .collection-main.is-draggable',
                  );
            if (
                !(shelf instanceof HTMLElement) ||
                !shelf.matches(
                    '.collection-stream, .collection-main.is-draggable',
                )
            )
                return;
            const next = shelf.scrollTop > (compact ? 12 : 72);
            if (next === compact) return;
            compact = next;
            layout.dataset.collectionCompact = String(compact);
        };
        update();
        layout.addEventListener('scroll', update, {
            capture: true,
            passive: true,
        });
        return () => {
            layout.removeEventListener('scroll', update, true);
            delete layout.dataset.collectionCompact;
        };
    }, [path]);

    useLayoutEffect(() => {
        const navigation = navigationRef.current;
        const indicator = indicatorRef.current;
        if (!navigation || !indicator) return;

        const positionIndicator = () => {
            const active = navigation.querySelector('a[aria-current]');
            indicator.hidden = !active;
            if (!active) return;

            const link = active.getBoundingClientRect();
            const nav = navigation.getBoundingClientRect();
            indicator.style.width = `${link.width - 36}px`;
            indicator.style.transform = `translateX(${link.left - nav.left - navigation.clientLeft + 18}px)`;
        };

        positionIndicator();
        const frame = requestAnimationFrame(() => {
            indicator.dataset.positioned = 'true';
        });
        const observer = new ResizeObserver(positionIndicator);
        observer.observe(navigation);
        navigation
            .querySelectorAll('a')
            .forEach((link) => observer.observe(link));

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, [path, user?.id, user?.verified, onboarding]);

    const pageClass =
        {
            'social/HomePage': 'home-page',
            'social/AuthPage': 'auth-page',
            'social/ProfilePage': 'collection-page',
            'social/CollectionsPage': 'explore-page',
            'social/FeedPage': 'feed-page',
            'social/FriendsPage': 'friends-page',
            'social/SettingsPage': 'settings-page',
            'social/AboutPage': 'about-page',
            'social/PrivacyPage': 'legal-page',
            'social/TermsPage': 'legal-page',
        }[component] ?? '';

    useEffect(() => {
        if (path !== '/' && path !== '/feed') return;
        const destination = legacyCollectionUrl(window.location.hash);
        if (destination) window.location.replace(destination);
    }, [path]);

    return (
        <div ref={layoutRef} className={`social-app ${pageClass}`}>
            <Head>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <a className="skip" href="#main" inert={recordOpen}>
                Skip to content
            </a>
            <header className="site-header" inert={recordOpen}>
                <Link className="brand" href="/">
                    <span className="brand-mark" aria-hidden="true" /> Plaat
                    Club<span className="brand-period">.</span>
                </Link>
                {user ? (
                    <>
                        {user.verified && (
                            <nav
                                ref={navigationRef}
                                className="main-navigation"
                                aria-label="Main navigation"
                            >
                                <div className="my-collection-navigation">
                                    <Link
                                        href={
                                            settingUpCollection
                                                ? '/feed'
                                                : user.profileUrl
                                        }
                                        aria-current={
                                            path === user.profileUrl ||
                                            onboarding
                                                ? 'page'
                                                : undefined
                                        }
                                    >
                                        My collection
                                    </Link>
                                    <CollectionSharing
                                        key={user.id}
                                        user={user}
                                    />
                                </div>
                                <Link
                                    href={
                                        settingUpCollection
                                            ? '/friends'
                                            : '/feed'
                                    }
                                    aria-current={
                                        (path === '/feed' && !onboarding) ||
                                        path === '/friends'
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    Friends
                                </Link>
                                <Link
                                    href="/collections"
                                    aria-current={
                                        path === '/collections'
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    Public
                                </Link>
                                <span
                                    ref={indicatorRef}
                                    className="navigation-indicator"
                                    aria-hidden="true"
                                    hidden
                                />
                            </nav>
                        )}
                        <div className="header-account">
                            {user.verified ? (
                                <NotificationsPopover
                                    key={`${user.id}:${props.notificationCount}`}
                                />
                            ) : (
                                <Link
                                    href="/verify-email"
                                    aria-current={
                                        path === '/verify-email'
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    Verify email
                                </Link>
                            )}
                            <AccountMenu
                                key={user.id}
                                user={user}
                                path={path}
                            />
                        </div>
                    </>
                ) : (
                    <nav
                        className="guest-navigation"
                        aria-label="Main navigation"
                    >
                        <Link
                            className="guest-explore"
                            href="/collections"
                            aria-current={
                                path === '/collections' ? 'page' : undefined
                            }
                        >
                            Public
                        </Link>
                        <Link
                            href="/login"
                            aria-current={
                                path === '/login' ? 'page' : undefined
                            }
                        >
                            Sign in
                        </Link>
                        {!component.endsWith('AuthPage') && (
                            <Link className="button small" href="/register">
                                Create account
                            </Link>
                        )}
                    </nav>
                )}
            </header>
            <RecordOverlay>
                <main id="main" className="page">
                    {props.flash.status && (
                        <p className="notice" role="status">
                            {props.flash.status}
                        </p>
                    )}
                    {Object.keys(props.errors).length > 0 && (
                        <div className="errors" role="alert">
                            <strong>Please check the following:</strong>
                            <ul>
                                {Object.entries(props.errors).map(
                                    ([field, message]) => (
                                        <li key={field}>
                                            <a href={`#${field}`}>{message}</a>
                                        </li>
                                    ),
                                )}
                            </ul>
                        </div>
                    )}
                    {children}
                </main>
            </RecordOverlay>
        </div>
    );
}
