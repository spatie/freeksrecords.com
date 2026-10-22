import type { Person } from '@/modules/social/types';

export function Avatar({
    person,
    className,
}: {
    person: Pick<Person, 'name' | 'avatarUrl'>;
    className: string;
}) {
    return (
        <span className={`avatar ${className}`} aria-hidden="true">
            {Array.from(person.name)[0]?.toLocaleUpperCase()}
            {person.avatarUrl && (
                <img
                    key={person.avatarUrl}
                    src={person.avatarUrl}
                    alt=""
                    referrerPolicy="no-referrer"
                    onError={(event) => {
                        event.currentTarget.hidden = true;
                    }}
                />
            )}
        </span>
    );
}
