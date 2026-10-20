import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { FieldError } from '@/modules/social/FieldError';
import { Pagination } from '@/modules/social/Pagination';
import type { RecordProps, SocialProps } from '@/modules/social/types';

export function RecordPage({
    appreciated,
    appreciations,
    collectionUrl,
    comments,
    record,
}: RecordProps) {
    const user = usePage<SocialProps>().props.auth.user;
    const notes = useForm({
        shared_note: record.sharedNote ?? '',
        private_note: record.privateNote ?? '',
    });
    const comment = useForm({ body: '' });
    const action = useForm({});
    const payload = record.payload;
    const base = `/shelves/${record.id}`;
    return (
        <>
            <Head title={payload.displayTitle} />
            <p className="back-link">
                <Link href={collectionUrl}>
                    Back to {record.owner.name}’s collection
                </Link>
            </p>
            <div className="record-detail two-columns">
                <img
                    className="large-cover"
                    src={payload.cover}
                    alt={`${payload.displayTitle} sleeve`}
                    width="600"
                    height="600"
                />
                <section>
                    <p className="record-artist">{payload.artist}</p>
                    <h1 className="record-title">{payload.displayTitle}</h1>
                    <p className="lede">
                        {payload.label} · {payload.originalYear ?? payload.year}
                    </p>
                    {record.formats.length > 0 && (
                        <p>Format: {record.formats.join(' / ')}</p>
                    )}
                    <p>Added {record.addedLabel}</p>
                    <div className="actions">
                        <a
                            className="button secondary"
                            href={payload.spotifyUrl}
                            rel="noopener noreferrer"
                            target="_blank"
                        >
                            Spotify
                        </a>
                        <a
                            className="button secondary"
                            href={payload.appleMusicUrl}
                            rel="noopener noreferrer"
                            target="_blank"
                        >
                            Apple Music
                        </a>
                    </div>
                    {record.canEditNotes ? (
                        <section className="panel spaced">
                            <h2>Your notes</h2>
                            <form
                                className="stack"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    notes.patch(`${base}/notes`, {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <label htmlFor="shared_note">
                                    Shared note
                                    <textarea
                                        id="shared_note"
                                        name="shared_note"
                                        aria-invalid={Boolean(
                                            notes.errors.shared_note,
                                        )}
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
                                    Visible only to people who can see your
                                    collection. Sharing a note never makes a
                                    private record public.
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
                                    Only you can see your private note. Both
                                    notes stay with this copy through syncs.
                                    Clear either field to remove its note.
                                </small>
                                <button
                                    className="button"
                                    disabled={notes.processing}
                                >
                                    Save notes
                                </button>
                            </form>
                        </section>
                    ) : (
                        record.sharedNote && (
                            <section className="panel spaced">
                                <h2>{record.owner.name}’s note</h2>
                                <p className="comment-body">
                                    {record.sharedNote}
                                </p>
                            </section>
                        )
                    )}
                    <div className="appreciation spaced">
                        <p>
                            {appreciations}{' '}
                            {appreciations === 1
                                ? 'appreciation'
                                : 'appreciations'}
                        </p>
                        {user ? (
                            <button
                                className={`button ${appreciated ? 'secondary' : ''}`}
                                disabled={action.processing}
                                onClick={() =>
                                    appreciated
                                        ? action.delete(
                                              `${base}/appreciations`,
                                              { preserveScroll: true },
                                          )
                                        : action.post(`${base}/appreciations`, {
                                              preserveScroll: true,
                                          })
                                }
                            >
                                {appreciated
                                    ? 'Appreciated · Undo'
                                    : 'Appreciate this record'}
                            </button>
                        ) : (
                            <Link href="/login">
                                Sign in to appreciate or comment
                            </Link>
                        )}
                    </div>
                    {payload.tracks.length > 0 && (
                        <>
                            <h2 className="spaced">On the record</h2>
                            <ol className="tracklist">
                                {payload.tracks.map((track, index) => (
                                    <li key={index}>
                                        <span>{track.position}</span>
                                        <a
                                            href={track.spotifyUrl}
                                            rel="noopener noreferrer"
                                            target="_blank"
                                        >
                                            {track.title}
                                        </a>
                                        <small>{track.duration}</small>
                                    </li>
                                ))}
                            </ol>
                        </>
                    )}
                </section>
            </div>
            <section className="conversation panel" id="comments">
                <div className="section-heading">
                    <h2>Comments</h2>
                    {record.canEditNotes && (
                        <Link href="/settings">Comment controls</Link>
                    )}
                </div>
                <p className="muted" id="body-help">
                    Anyone who can view this collection can read its comments.
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
                                            `${base}/comments/${item.id}`,
                                            { preserveScroll: true },
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
                <Pagination {...comments} />
                {!record.commentsEnabled ? (
                    <p>New comments are turned off by the collection owner.</p>
                ) : user ? (
                    <form
                        className="stack"
                        onSubmit={(event) => {
                            event.preventDefault();
                            comment.post(`${base}/comments`, {
                                preserveScroll: true,
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
                                    comment.setData('body', event.target.value)
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
                ) : (
                    <p>
                        <Link href="/login">Sign in</Link> to join the
                        conversation.
                    </p>
                )}
            </section>
        </>
    );
}
