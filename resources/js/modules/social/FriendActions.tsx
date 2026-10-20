import { useForm } from '@inertiajs/react';

export function FriendActions({
    actions,
    personId,
}: {
    actions: { value: string; label: string }[];
    personId: number;
}) {
    const form = useForm({ action: '' });
    return (
        <div className="actions">
            {actions.map((action, index) => (
                <button
                    className={`button ${index ? 'secondary' : ''}`}
                    key={action.value}
                    disabled={form.processing}
                    onClick={() => {
                        form.transform(() => ({ action: action.value }));
                        form.post(`/friends/${personId}`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    {action.label}
                </button>
            ))}
        </div>
    );
}
