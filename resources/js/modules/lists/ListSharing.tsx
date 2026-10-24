import { useForm } from '@inertiajs/react';
import { useState } from 'react';

import type { RecordList } from '@/modules/lists/types';
import { VisibilityPicker } from '@/modules/social/VisibilityPicker';

export function ListSharing({ list }: { list: RecordList }) {
    const form = useForm({ visibility: list.visibility });
    const [message, setMessage] = useState('');

    return (
        <section className="list-sharing" aria-labelledby="list-sharing-title">
            <h2 id="list-sharing-title">Visibility</h2>
            <p>
                Your collection’s privacy stays exactly as it is. Notes and
                comments are never included.
            </p>
            <form
                className="stack"
                onSubmit={(event) => {
                    event.preventDefault();
                    setMessage('');
                    form.patch(`/lists/${list.publicId}`, {
                        preserveScroll: true,
                        onSuccess: () => setMessage('Visibility saved.'),
                    });
                }}
            >
                <VisibilityPicker
                    disabled={form.processing}
                    error={form.errors.visibility}
                    label="List visibility"
                    value={form.data.visibility}
                    onChange={(value) => form.setData('visibility', value)}
                />
                <p className="list-sharing-explanation">
                    {form.data.visibility === 'only_me'
                        ? 'Just for you. Nobody else can see this list.'
                        : form.data.visibility === 'everyone'
                          ? 'This list and its selected records will be public. The rest of your collection stays private unless you already share it.'
                          : 'Only your accepted friends can open this list after signing in. Your collection’s privacy stays unchanged.'}
                </p>
                {form.isDirty && (
                    <p className="list-sharing-pending" role="status">
                        Visibility changed — save below to apply.
                    </p>
                )}
                <button className="button" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save visibility'}
                </button>
            </form>
            <p role="status">{message}</p>
        </section>
    );
}
