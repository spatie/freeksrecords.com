import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Avatar } from '@/modules/social/Avatar';
import { FriendActions } from '@/modules/social/FriendActions';
import type { Notifications, SocialProps } from '@/modules/social/types';

export function NotificationsPopover() {
    const { notificationCount, csrfToken } = usePage<SocialProps>().props;
    const [result, setResult] = useState<Notifications | null>(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const popover = useRef<HTMLElement>(null);
    const request = useRef(0);

    async function load(url = '/notifications', markAsRead = false) {
        const current = ++request.current;
        setBusy(true);
        setError('');
        try {
            if (markAsRead) {
                const response = await fetch('/notifications/read', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok || response.redirected)
                    throw new Error('Unavailable');
            }
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });
            if (!response.ok || response.redirected)
                throw new Error('Unavailable');
            const data: Notifications = await response.json();
            if (current === request.current) setResult(data);
        } catch {
            if (current === request.current)
                setError(
                    'Couldn’t load notifications. Close and reopen to try again.',
                );
        } finally {
            if (current === request.current) setBusy(false);
        }
    }

    async function clearAll() {
        request.current++;
        setBusy(true);
        setError('');
        try {
            const response = await fetch('/notifications', {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            if (!response.ok || response.redirected)
                throw new Error('Unavailable');
            await load();
        } catch {
            setError('Couldn’t clear notifications. Please try again.');
            setBusy(false);
        }
    }

    useEffect(() => {
        if (new URLSearchParams(window.location.search).has('notifications'))
            popover.current?.showPopover();
        return () => {
            request.current++;
        };
    }, []);

    const count = result?.unreadCount ?? notificationCount;
    return (
        <>
            <button
                type="button"
                className="notifications-trigger"
                popoverTarget="notifications-popover"
                aria-label={
                    count ? `Notifications (${count} unread)` : 'Notifications'
                }
            >
                <Bell size={19} aria-hidden="true" />
                <span
                    className="notification-count"
                    hidden={!count}
                    aria-hidden="true"
                >
                    {count > 99 ? '99+' : count}
                </span>
            </button>
            <section
                ref={popover}
                id="notifications-popover"
                className="notifications-popover"
                popover="auto"
                aria-labelledby="notifications-title"
                onToggle={(event) => {
                    if (event.newState === 'open')
                        void load('/notifications', true);
                }}
            >
                <header className="notifications-heading">
                    <h2 id="notifications-title">Notifications</h2>
                    <button
                        type="button"
                        className="notifications-close"
                        popoverTarget="notifications-popover"
                        popoverTargetAction="hide"
                        aria-label="Close notifications"
                    >
                        ×
                    </button>
                </header>
                <div
                    className="notifications-content"
                    aria-live="polite"
                    aria-busy={busy}
                >
                    {error && (
                        <p className="notifications-empty" role="alert">
                            {error}
                        </p>
                    )}
                    {!result && !error && (
                        <p className="notifications-empty">
                            Loading notifications…
                        </p>
                    )}
                    {result && (
                        <>
                            {result.notifications.data.length > 0 && (
                                <button
                                    className="text-button"
                                    disabled={busy}
                                    onClick={() => void clearAll()}
                                >
                                    Clear all
                                </button>
                            )}
                            {!result.notifications.data.length && (
                                <p className="notifications-empty">
                                    All quiet for now. Friend requests,
                                    appreciations, and comments will arrive
                                    here.
                                </p>
                            )}
                            {result.notifications.data.map((notification) => (
                                <article
                                    className={`notification-item ${notification.unread ? 'unread' : 'read'}`}
                                    key={notification.id}
                                >
                                    <Link
                                        href={notification.url}
                                        onClick={() =>
                                            popover.current?.hidePopover()
                                        }
                                    >
                                        {notification.record ? (
                                            <img
                                                src={notification.record.cover}
                                                alt=""
                                                width="48"
                                                height="48"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <Avatar
                                                className="notification-avatar"
                                                person={notification.actor}
                                            />
                                        )}
                                        <span className="notification-copy">
                                            <span>
                                                <strong>
                                                    {notification.actor.name}
                                                </strong>{' '}
                                                {notification.text}
                                            </span>
                                            {notification.record && (
                                                <span className="muted">
                                                    {notification.record.title}{' '}
                                                    —{' '}
                                                    {notification.record.artist}
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                    {notification.canRespond && (
                                        <div className="notification-actions">
                                            <FriendActions
                                                personId={notification.actor.id}
                                                actions={[
                                                    {
                                                        value: 'accept',
                                                        label: 'Accept',
                                                    },
                                                    {
                                                        value: 'decline',
                                                        label: 'Decline',
                                                    },
                                                ]}
                                                onSuccess={() => void load()}
                                            />
                                        </div>
                                    )}
                                </article>
                            ))}
                            {(result.notifications.prev_page_url ||
                                result.notifications.next_page_url) && (
                                <nav
                                    className="notification-pagination"
                                    aria-label="Notification pagination"
                                >
                                    {[
                                        result.notifications.prev_page_url,
                                        result.notifications.next_page_url,
                                    ].map(
                                        (url, index) =>
                                            url && (
                                                <button
                                                    key={url}
                                                    className="text-button"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        void load(url)
                                                    }
                                                >
                                                    {index
                                                        ? 'Next'
                                                        : 'Previous'}
                                                </button>
                                            ),
                                    )}
                                </nav>
                            )}
                        </>
                    )}
                </div>
            </section>
        </>
    );
}
