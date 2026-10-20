import type { ReactNode } from 'react';

import cabinetCss from '../../../css/record-cabinet.css?inline';
import controlsCss from '../../../css/record-controls.css?inline';
import '../../../css/profile-shelf.css';

export function RecordRoom({ children }: { children: ReactNode }) {
    return (
        <div className="profile-record-room">
            <style>{`@scope (.profile-record-room) { ${cabinetCss}\n${controlsCss} }`}</style>
            <div className="record-room">{children}</div>
        </div>
    );
}
