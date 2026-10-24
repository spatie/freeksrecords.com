import { Check, ChevronDown, Globe2, LockKeyhole, Users } from 'lucide-react';
import { useEffect, useRef, type ReactNode, type RefObject } from 'react';

import type { Visibility } from '@/modules/social/types';

export const visibilityChoices = [
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
] as const;

export function VisibilityBadge({
    value,
    iconOnly = false,
}: {
    value: Visibility;
    iconOnly?: boolean;
}) {
    const choice = visibilityChoices.find((choice) => choice.value === value)!;
    const Icon = choice.icon;

    return (
        <span
            className={`visibility-badge${iconOnly ? ' is-icon-only' : ''}`}
            title={iconOnly ? choice.label : undefined}
            role={iconOnly ? 'img' : undefined}
            aria-label={iconOnly ? choice.label : undefined}
        >
            <Icon size={15} aria-hidden="true" />
            {!iconOnly && <span>{choice.label}</span>}
        </span>
    );
}

export function VisibilityPicker({
    detailsRef,
    disabled = false,
    error,
    label,
    notice,
    onChange,
    value,
    children,
}: {
    detailsRef?: RefObject<HTMLDetailsElement | null>;
    disabled?: boolean;
    error?: string;
    label: string;
    notice?: ReactNode;
    onChange: (value: Visibility) => void;
    value: Visibility;
    children?: ReactNode;
}) {
    const ownRef = useRef<HTMLDetailsElement>(null);
    const details = detailsRef ?? ownRef;
    const choice = visibilityChoices.find((choice) => choice.value === value)!;

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
        };
    }, [details]);

    return (
        <div className="collection-sharing">
            <details
                ref={details}
                onToggle={(event) => {
                    if (event.currentTarget.open)
                        event.currentTarget
                            .querySelector('.sharing-panel')
                            ?.scrollIntoView({ block: 'nearest' });
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Escape' && event.currentTarget.open) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.currentTarget.open = false;
                        event.currentTarget.querySelector('summary')?.focus();
                    }
                }}
            >
                <summary
                    className="collection-visibility"
                    aria-label={`${label}: ${choice.label}`}
                >
                    <VisibilityBadge value={value} />
                    <ChevronDown
                        className="sharing-chevron"
                        size={14}
                        aria-hidden="true"
                    />
                </summary>
                <div className="sharing-panel">
                    <p className="sharing-title">Who can see it?</p>
                    {notice ?? (
                        <fieldset disabled={disabled} aria-busy={disabled}>
                            <legend className="sharing-legend">{label}</legend>
                            {visibilityChoices.map((choice) => (
                                <button
                                    key={choice.value}
                                    className="sharing-choice"
                                    type="button"
                                    aria-pressed={value === choice.value}
                                    onClick={() => {
                                        onChange(choice.value);
                                        if (!detailsRef && details.current) {
                                            details.current.open = false;
                                            details.current
                                                .querySelector('summary')
                                                ?.focus();
                                        }
                                    }}
                                >
                                    <choice.icon size={19} aria-hidden="true" />
                                    <span>
                                        <strong>{choice.label}</strong>
                                        <small>{choice.description}</small>
                                    </span>
                                    {value === choice.value && (
                                        <Check size={16} aria-hidden="true" />
                                    )}
                                </button>
                            ))}
                        </fieldset>
                    )}
                    {error && <p className="sharing-feedback">{error}</p>}
                </div>
            </details>
            {children}
        </div>
    );
}
