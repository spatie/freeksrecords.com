export function Avatar({
    label,
    imageUrl,
    className,
}: {
    label: string;
    imageUrl?: string | null;
    className: string;
}) {
    return (
        <span className={className} aria-hidden="true">
            {Array.from(label)[0]?.toLocaleUpperCase()}
            {imageUrl && (
                <img
                    src={imageUrl}
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
