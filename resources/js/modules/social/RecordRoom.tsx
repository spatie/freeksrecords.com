import type { ReactNode } from 'react';

import '../../../css/profile-shelf.css';

export function RecordRoom({ children }: { children: ReactNode }) {
    return (
        <div className="profile-record-room">
            <div className="record-room">{children}</div>
        </div>
    );
}
