@props(['user', 'count'])

<div class="import-status" data-import-status data-collector-id="{{ $user->id }}">
    <div class="import-progress" role="status">
        <strong data-import-message>{{ $user->sync_status ?? 'Waiting for the import to start' }}</strong>
        <p data-import-count>{{ $count }} {{ Str::plural('record', $count) }} imported</p>
    </div>
    <p class="muted">Large collections can take a while. You can leave this page; the import continues in the background.</p>
    <p class="muted">Collection visibility: <span data-import-visibility>{{ match($user->visibility) { 'everyone' => 'Everyone', 'friends' => 'Friends', default => 'Only me' } }}</span>. <a href="/settings">Change visibility</a></p>
    <div class="actions">
        <a class="button" data-import-complete href="{{ route('profile', $user->handle) }}" @if($user->collection_imported_at === null) hidden @endif>View my collection</a>
        <form method="post" action="/discogs/sync" data-import-retry @unless(str_starts_with($user->sync_status ?? '', 'Sync failed')) hidden @endunless>@csrf<x-social.button variant="secondary">Retry import</x-social.button></form>
        <a href="{{ route('feed') }}" data-import-refresh>Check progress</a>
        <a href="/settings">Connection settings</a>
    </div>
    <p data-import-error class="muted" hidden>Couldn’t refresh progress. <a href="{{ route('feed') }}">Reload the page</a> to try again.</p>
</div>
@push('scripts')
<script>
    (() => {
        const panel = document.querySelector('[data-import-status]');
        let timer;
        const refresh = async () => {
            try {
                const response = await fetch(@json(route('discogs.status')), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok || response.redirected) throw new Error('Progress unavailable');
                const state = await response.json();
                if (String(state.collectorId) !== panel.dataset.collectorId) {
                    window.location.reload();
                    return;
                }
                panel.querySelector('[data-import-message]').textContent = state.complete ? 'Your collection is ready' : (state.connected ? state.status : 'Discogs was disconnected. Open connection settings to reconnect.');
                panel.querySelector('[data-import-count]').textContent = `${state.count} ${state.count === 1 ? 'record' : 'records'} imported`;
                panel.querySelector('[data-import-visibility]').textContent = state.visibility;
                panel.querySelector('[data-import-complete]').href = state.collectionUrl;
                panel.querySelector('[data-import-complete]').hidden = !state.complete;
                panel.querySelector('[data-import-refresh]').hidden = state.complete;
                panel.querySelector('[data-import-retry]').hidden = !state.failed || !state.connected;
                panel.querySelector('[data-import-error]').hidden = true;
                if (state.connected && !state.complete) timer = setTimeout(refresh, 5000);
            } catch {
                panel.querySelector('[data-import-error]').hidden = false;
            }
        };
        refresh();
        window.addEventListener('pagehide', () => clearTimeout(timer), { once: true });
    })();
</script>
@endpush
