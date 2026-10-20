export function SocialSwitch({
    checked,
    description,
    label,
    name,
    onChange,
}: {
    checked: boolean;
    description: string;
    label: string;
    name: string;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="switch-field">
            <label className="switch">
                <input
                    id={name}
                    name={name}
                    type="checkbox"
                    role="switch"
                    checked={checked}
                    aria-describedby={`${name}-description`}
                    onChange={(event) => onChange(event.target.checked)}
                />
                <span className="switch-track" aria-hidden="true" />
                <span>{label}</span>
            </label>
            <small id={`${name}-description`}>{description}</small>
        </div>
    );
}
