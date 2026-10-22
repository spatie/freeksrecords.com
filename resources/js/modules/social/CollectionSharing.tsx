import { Link, router } from '@inertiajs/react';
import { Check, ChevronDown, Globe2, LockKeyhole, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import type { Viewer, Visibility } from '@/modules/social/types';

const choices = [
    {
        value: 'only_me',
        label: 'Only me',
        description: 'Private to you',
        icon: LockKeyhole,
    },
    {
        value: 'friends',
        label: 'Friends',
        description: 'Accepted friends only',
        icon: Users,
    },
    {
        value: 'everyone',
        label: 'Everyone',
        description: 'Anyone, including visitors',
        icon: Globe2,
    },
] satisfies {
    value: Visibility;
    label: string;
    description: string;
    icon: typeof LockKeyhole;
}[];

export function CollectionSharing({ user }: { user: Viewer }) {
    const [pending, setPending] = useState(false);
    const [feedback, setFeedback] = useState('');
    const [saved, setSaved] = useState(false);
    const details = useRef<HTMLDetailsElement>(null);
    const savedTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const choice = choices.find((choice) => choice.value === user.visibility)!;
    const Icon = choice.icon;
    useEffect(() => {
        function close(event: MouseEvent | KeyboardEvent) {
            if (!details.current?.open) return;
            if (
                event instanceof KeyboardEvent
                    ? event.key === 'Escape'
                    : !details.current.contains(event.target as Node)
            ) {
                details.current.open = false;
                if (event instanceof KeyboardEvent)
                    details.current.querySelector('summary')?.focus();
            }
        }
        document.addEventListener('click', close);
        document.addEventListener('keydown', close);
        return () => {
            document.removeEventListener('click', close);
            document.removeEventListener('keydown', close);
            clearTimeout(savedTimer.current);
        };
    }, []);
    function save(visibility: Visibility) {
        if (pending) return;
        clearTimeout(savedTimer.current);
        setPending(true);
        setSaved(false);
        setFeedback('Updating sharing…');
        router.patch(
            '/collection/visibility',
            { visibility },
            {
                errorBag: 'collectionVisibility',
                only: ['auth', 'errors', 'flash'],
                preserveScroll: true,
                preserveUrl: true,
                onSuccess: (page) => {
                    const saved = (
                        page.props as unknown as { auth: { user: Viewer } }
                    ).auth.user.visibility;
                    if (saved !== visibility) {
                        setFeedback(
                            'We couldn’t confirm the change. Please check your sharing before sending anyone a link.',
                        );
                        return;
                    }
                    setFeedback(
                        `Sharing saved. Collection visibility: ${choices.find((choice) => choice.value === saved)?.label}.`,
                    );
                    setSaved(true);
                    savedTimer.current = setTimeout(() => {
                        setSaved(false);
                        setFeedback('');
                    }, 3000);
                    if (details.current) {
                        details.current.open = false;
                        details.current.querySelector('summary')?.focus();
                    }
                },
                onError: () =>
                    setFeedback(
                        'Nothing changed. Choose an option and try again.',
                    ),
                onNetworkError: () => {
                    setFeedback(
                        'We couldn’t confirm the change. Please check your sharing before sending anyone a link.',
                    );
                    return false;
                },
                onHttpException: () => {
                    setFeedback(
                        'We couldn’t confirm the change. Please check your sharing before sending anyone a link.',
                    );
                    return false;
                },
                onCancel: () =>
                    setFeedback(
                        'The update was interrupted. Check your sharing before sending anyone a link.',
                    ),
                onFinish: () => setPending(false),
            },
        );
    }
    return (
        <div className="collection-sharing">
            <details ref={details}>
                <summary
                    className="collection-visibility"
                    aria-label={`Collection visibility: ${choice.label}`}
                >
                    <Icon
                        className="sharing-icon"
                        size={15}
                        aria-hidden="true"
                    />
                    <span className="sharing-label">{choice.label}</span>
                    <ChevronDown
                        className="sharing-chevron"
                        size={14}
                        aria-hidden="true"
                    />
                </summary>
                <div className="sharing-panel">
                    <p className="sharing-title">Who can see it?</p>
                    {user.legacyOwner ? (
                        <div className="sharing-public">
                            <strong>Everyone · Always public</strong>
                            <p>
                                This original public collection stays open to
                                everyone, including visitors who aren’t signed
                                in. Sharing can’t be changed here or in
                                settings.
                            </p>
                        </div>
                    ) : !user.verified ? (
                        <p>
                            <Link href="/verify-email">Verify your email</Link>{' '}
                            to change who can browse your collection.
                        </p>
                    ) : (
                        <fieldset disabled={pending} aria-busy={pending}>
                            <legend className="sharing-legend">
                                Collection visibility
                            </legend>
                            {choices.map((choice) => (
                                <button
                                    key={choice.value}
                                    className="sharing-choice"
                                    type="button"
                                    aria-pressed={
                                        user.visibility === choice.value
                                    }
                                    onClick={() => save(choice.value)}
                                >
                                    <choice.icon size={19} aria-hidden="true" />
                                    <span>
                                        <strong>{choice.label}</strong>
                                        <small>{choice.description}</small>
                                    </span>
                                    {user.visibility === choice.value && (
                                        <Check size={16} aria-hidden="true" />
                                    )}
                                </button>
                            ))}
                        </fieldset>
                    )}
                    {feedback && !saved && !pending && (
                        <p className="sharing-feedback">{feedback}</p>
                    )}
                </div>
            </details>
            {saved && (
                <span className="sharing-confirmation" aria-hidden="true">
                    <Check size={14} /> Saved
                </span>
            )}
            {feedback && (
                <p className="sharing-status" role="status">
                    {feedback}
                </p>
            )}
        </div>
    );
}
