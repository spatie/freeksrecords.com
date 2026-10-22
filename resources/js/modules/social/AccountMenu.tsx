import { Link, router } from '@inertiajs/react';
import { ChevronDown, LogOut, Settings, Users } from 'lucide-react';
import { useRef } from 'react';

import { Avatar } from '@/modules/social/Avatar';
import type { Viewer } from '@/modules/social/types';

export function AccountMenu({ path, user }: { path: string; user: Viewer }) {
    const popover = useRef<HTMLDivElement>(null);

    return (
        <>
            <button
                type="button"
                className="account-trigger"
                popoverTarget="account-popover"
                aria-label={`Account menu for ${user.handle}`}
            >
                <Avatar className="account-avatar" person={user} />
                <span className="account-name">{user.handle}</span>
                <ChevronDown size={14} aria-hidden="true" />
            </button>
            <div
                ref={popover}
                id="account-popover"
                className="account-popover"
                popover="auto"
            >
                {user.verified && (
                    <Link
                        href="/friends"
                        aria-current={path === '/friends' ? 'page' : undefined}
                        onClick={() => popover.current?.hidePopover()}
                    >
                        <Users size={16} aria-hidden="true" />
                        Your friends
                    </Link>
                )}
                {user.verified && (
                    <Link
                        href="/settings"
                        aria-current={path === '/settings' ? 'page' : undefined}
                        onClick={() => popover.current?.hidePopover()}
                    >
                        <Settings size={16} aria-hidden="true" />
                        Settings
                    </Link>
                )}
                <button
                    type="button"
                    onClick={() => {
                        popover.current?.hidePopover();
                        router.post('/logout');
                    }}
                >
                    <LogOut size={16} aria-hidden="true" />
                    Sign out
                </button>
            </div>
        </>
    );
}
