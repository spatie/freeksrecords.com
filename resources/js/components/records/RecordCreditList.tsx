import { discogsEntityUrl } from '@/components/records/discogsText';
import type { DiscogsCredit } from '@/components/records/types';

export function RecordCreditList({ credits }: { credits: DiscogsCredit[] }) {
    return (
        <dl className="record-credits">
            {credits.map((credit, index) => (
                <div key={`${credit.id}-${credit.role}-${index}`}>
                    <dt>{credit.role || 'Contributor'}</dt>
                    <dd>
                        {credit.id && credit.id > 0 ? (
                            <a
                                href={discogsEntityUrl('a', String(credit.id))}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {credit.anv || credit.name}
                            </a>
                        ) : (
                            credit.anv || credit.name
                        )}
                        {credit.tracks && (
                            <small>Tracks: {credit.tracks}</small>
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
