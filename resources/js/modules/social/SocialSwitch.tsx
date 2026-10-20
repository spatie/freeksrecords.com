export function SocialSwitch({
    checked,
    label,
    name,
    onChange,
}: {
    checked: boolean;
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
                    onChange={(event) => onChange(event.target.checked)}
                />
                <span className="switch-track" aria-hidden="true" />
                <span>{label}</span>
            </label>
        </div>
    );
}
