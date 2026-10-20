export function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} className="field-error">
            {message}
        </p>
    ) : null;
}
