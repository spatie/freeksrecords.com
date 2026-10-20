import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { FriendActions } from '@/modules/social/FriendActions';
import { Pagination } from '@/modules/social/Pagination';
import type { FriendsProps, SocialProps } from '@/modules/social/types';

export function FriendsPage({ connections, people, query }: FriendsProps) {
    const user = usePage<SocialProps>().props.auth.user!;
    const search = useForm({ q: query });
    return (
        <>
            <Head title="Your friends" />
            <div className="page-heading">
                <h1>Friends</h1>
                <p>
                    Find someone by username and send a request. Once they
                    accept, you’ll see the records they share with friends.
                </p>
            </div>
            <form
                className="search"
                onSubmit={(event) => {
                    event.preventDefault();
                    search.get('/friends');
                }}
            >
                <label htmlFor="find-handle">Find someone by username</label>
                <div className="actions">
                    <input
                        id="find-handle"
                        name="q"
                        value={search.data.q}
                        onChange={(event) =>
                            search.setData('q', event.target.value)
                        }
                        maxLength={31}
                        placeholder="e.g. robin"
                    />
                    <button className="button" disabled={search.processing}>
                        Search
                    </button>
                    <Link href="/friends">My connections</Link>
                </div>
            </form>
            <div className="people-list">
                {people.data.map((person) => {
                    const connection = connections.find(
                        (friend) =>
                            friend.user_low === person.id ||
                            friend.user_high === person.id,
                    );
                    const actions = !connection
                        ? [{ value: 'request', label: 'Send friend request' }]
                        : connection.accepted_at
                          ? [{ value: 'unfriend', label: 'Unfriend' }]
                          : connection.requested_by === user.id
                            ? [{ value: 'cancel', label: 'Cancel request' }]
                            : [
                                  { value: 'accept', label: 'Accept' },
                                  { value: 'decline', label: 'Decline' },
                              ];
                    return (
                        <article className="person-row panel" key={person.id}>
                            <div>
                                <h2>
                                    <Link href={person.profileUrl}>
                                        {person.name}
                                    </Link>
                                </h2>
                                <p>@{person.handle}</p>
                            </div>
                            <div className="actions">
                                {connection && (
                                    <span
                                        className={
                                            connection.accepted_at
                                                ? 'badge'
                                                : 'muted'
                                        }
                                    >
                                        {connection.accepted_at
                                            ? 'Friends'
                                            : connection.requested_by ===
                                                user.id
                                              ? 'Request sent'
                                              : 'Wants to be friends'}
                                    </span>
                                )}
                                <FriendActions
                                    personId={person.id}
                                    actions={actions}
                                />
                            </div>
                        </article>
                    );
                })}
                {!people.data.length && (
                    <section className="empty panel">
                        <h2>
                            {query
                                ? 'No exact username match'
                                : 'Your first friend starts here'}
                        </h2>
                        <p>
                            {query
                                ? 'Check the spelling and try the exact username.'
                                : 'Search above, or browse a public collection to meet its owner.'}
                        </p>
                        <Link href="/collections">
                            Browse public collections
                        </Link>
                    </section>
                )}
            </div>
            <Pagination {...people} />
        </>
    );
}
