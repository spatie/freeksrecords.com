import { Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import type { Viewer, Visibility } from '@/modules/social/types';
import {
    VisibilityPicker,
    visibilityChoices as choices,
} from '@/modules/social/VisibilityPicker';

export function CollectionSharing({ user }: { user: Viewer }) {
    const [pending, setPending] = useState(false);
    const [feedback, setFeedback] = useState('');
    const [saved, setSaved] = useState(false);
    const details = useRef<HTMLDetailsElement>(null);
    const savedTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    useEffect(() => () => clearTimeout(savedTimer.current), []);
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
        <VisibilityPicker
            detailsRef={details}
            disabled={pending}
            error={feedback && !saved && !pending ? feedback : undefined}
            label="Collection visibility"
            onChange={save}
            value={user.visibility}
            notice={
                user.legacyOwner ? (
                    <div className="sharing-public">
                        <strong>Everyone · Always public</strong>
                        <p>
                            This original public collection stays open to
                            everyone, including visitors who aren’t signed in.
                            Sharing can’t be changed here or in settings.
                        </p>
                    </div>
                ) : !user.verified ? (
                    <p>
                        <Link href="/verify-email">Verify your email</Link> to
                        change who can browse your collection.
                    </p>
                ) : undefined
            }
        >
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
        </VisibilityPicker>
    );
}
