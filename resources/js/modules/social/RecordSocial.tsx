import { Link, useForm, usePage } from '@inertiajs/react';
import { Heart } from 'lucide-react';

import { FieldError } from '@/modules/social/FieldError';
import { Pagination } from '@/modules/social/Pagination';
import type { RecordProps, SocialProps } from '@/modules/social/types';

export function RecordSocial({
    appreciated,
    appreciations,
    comments,
    record,
}: RecordProps) {
    const { auth, recordOverlay } = usePage<SocialProps>().props;
    const user = auth.user;
    const only = recordOverlay
        ? ['recordOverlay', 'flash', 'errors']
        : undefined;
    const visitOptions = { preserveScroll: true, preserveUrl: true, only };
    const fragment = typeof window === 'undefined' ? '' : window.location.hash;
    const notes = useForm({
        shared_note: record.sharedNote ?? '',
        private_note: record.privateNote ?? '',
    });
    const comment = useForm({ body: '' });
    const action = useForm({});
    const base = `/shelves/${record.publicId}`;
    return (
        <>
            {record.canEditNotes ? (
                <section className="panel spaced">
                    <h2>Your notes</h2>
                    <form
                        className="stack"
                        onSubmit={(event) => {
                            event.preventDefault();
                            notes.patch(`${base}/notes`, visitOptions);
                        }}
                    >
                        <label htmlFor="shared_note">
                            Shared note
                            <textarea
                                id="shared_note"
                                name="shared_note"
                                aria-invalid={Boolean(notes.errors.shared_note)}
                                aria-describedby={`shared_note-help${notes.errors.shared_note ? ' shared_note-error' : ''}`}
                                rows={4}
                                maxLength={5000}
                                value={notes.data.shared_note}
                                onChange={(event) =>
                                    notes.setData(
                                        'shared_note',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        <FieldError
                            id="shared_note-error"
                            message={notes.errors.shared_note}
                        />
                        <small id="shared_note-help">
                            Visible only to people who can see your collection.
                            Sharing a note never makes a private record public.
                        </small>
                        <label htmlFor="private_note">
                            Private note
                            <textarea
                                id="private_note"
                                name="private_note"
                                aria-invalid={Boolean(
                                    notes.errors.private_note,
                                )}
                                aria-describedby={`private_note-help${notes.errors.private_note ? ' private_note-error' : ''}`}
                                rows={4}
                                maxLength={5000}
                                value={notes.data.private_note}
                                onChange={(event) =>
                                    notes.setData(
                                        'private_note',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        <FieldError
                            id="private_note-error"
                            message={notes.errors.private_note}
                        />
                        <small id="private_note-help">
                            Only you can see your private note. Both notes stay
                            with this copy through syncs. Clear either field to
                            remove its note.
                        </small>
                        <button className="button" disabled={notes.processing}>
                            Save notes
                        </button>
                    </form>
                </section>
            ) : (
                record.sharedNote && (
                    <section className="panel spaced">
                        <h2>{record.owner.name}’s note</h2>
                        <p className="comment-body">{record.sharedNote}</p>
                    </section>
                )
            )}
            <div className="appreciation spaced">
                <p>
                    {appreciations}{' '}
                    {appreciations === 1 ? 'appreciation' : 'appreciations'}
                </p>
                {user ? (
                    <button
                        className="button appreciation-toggle"
                        type="button"
                        aria-pressed={appreciated}
                        aria-label={
                            appreciated
                                ? 'Appreciated — activate to undo appreciation'
                                : 'Appreciate this record'
                        }
                        disabled={action.processing}
                        onClick={() =>
                            appreciated
                                ? action.delete(
                                      `${base}/appreciations`,
                                      visitOptions,
                                  )
                                : action.post(
                                      `${base}/appreciations`,
                                      visitOptions,
                                  )
                        }
                    >
                        <Heart size={16} aria-hidden="true" />
                        {appreciated ? 'Appreciated' : 'Appreciate'}
                    </button>
                ) : (
                    <Link href="/login">Sign in to appreciate or comment</Link>
                )}
            </div>
            {user && (
                <section className="conversation panel" id="comments">
                    <div className="section-heading">
                        <h2>Comments</h2>
                        {record.canEditNotes && (
                            <Link href="/settings">Comment controls</Link>
                        )}
                    </div>
                    <p className="muted" id="body-help">
                        Anyone who can view this collection can read its
                        comments.
                    </p>
                    {comments.data.map((item) => (
                        <article className="comment" key={item.id}>
                            <div className="section-heading">
                                <strong>
                                    <Link href={item.user.profileUrl}>
                                        {item.user.name}
                                    </Link>
                                </strong>
                                {item.canDelete && (
                                    <button
                                        className="text-button"
                                        disabled={action.processing}
                                        aria-label={`Delete comment by ${item.user.name}`}
                                        onClick={() =>
                                            action.delete(
                                                `${base}/comments/${item.publicId}`,
                                                visitOptions,
                                            )
                                        }
                                    >
                                        Delete
                                    </button>
                                )}
                            </div>
                            <p className="comment-body">{item.body}</p>
                        </article>
                    ))}
                    <Pagination
                        prev_page_url={
                            comments.prev_page_url
                                ? `${comments.prev_page_url}${fragment}`
                                : null
                        }
                        next_page_url={
                            comments.next_page_url
                                ? `${comments.next_page_url}${fragment}`
                                : null
                        }
                        only={only}
                    />
                    {!record.commentsEnabled ? (
                        <p>
                            New comments are turned off by the collection owner.
                        </p>
                    ) : (
                        <form
                            className="stack"
                            onSubmit={(event) => {
                                event.preventDefault();
                                comment.post(`${base}/comments`, {
                                    ...visitOptions,
                                    onSuccess: () => comment.reset(),
                                });
                            }}
                        >
                            <label htmlFor="body">
                                Add a comment
                                <textarea
                                    id="body"
                                    name="body"
                                    aria-invalid={Boolean(comment.errors.body)}
                                    aria-describedby={`body-help${comment.errors.body ? ' body-error' : ''}`}
                                    rows={3}
                                    maxLength={2000}
                                    value={comment.data.body}
                                    onChange={(event) =>
                                        comment.setData(
                                            'body',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                            </label>
                            <FieldError
                                id="body-error"
                                message={comment.errors.body}
                            />
                            <button
                                className="button"
                                disabled={comment.processing}
                            >
                                Post comment
                            </button>
                        </form>
                    )}
                </section>
            )}
        </>
    );
}
