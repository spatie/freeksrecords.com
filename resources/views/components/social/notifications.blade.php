@use('App\Models\SocialNotification')
@php($unreadCount = SocialNotification::visibleTo(auth()->user())->whereNull('read_at')->count())
<button type="button" class="notifications-trigger" popovertarget="notifications-popover">
    Notifications <span class="notification-count" @if(!$unreadCount) hidden @endif>{{ $unreadCount }}</span>
</button>
<section id="notifications-popover" class="notifications-popover" popover aria-labelledby="notifications-title">
    <header class="notifications-heading">
        <h2 id="notifications-title">Notifications</h2>
        <button type="button" class="notifications-close" popovertarget="notifications-popover" popovertargetaction="hide" aria-label="Close notifications">×</button>
    </header>
    <div class="notifications-content" aria-live="polite"><p class="notifications-empty">Loading notifications…</p></div>
</section>
@push('scripts')
<script>
    (() => {
        const popover = document.getElementById('notifications-popover');
        const content = popover.querySelector('.notifications-content');
        const count = document.querySelector('.notification-count');
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        const load = async (url = '/notifications') => {
            content.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(url, { headers, cache: 'no-store' });
                if (!response.ok || response.redirected) throw new Error('Notifications unavailable');
                content.innerHTML = await response.text();
                const unread = content.querySelector('[data-unread-count]').dataset.unreadCount;
                count.textContent = unread;
                count.hidden = Number(unread) === 0;
            } catch {
                content.innerHTML = '<p class="notifications-empty">Couldn’t load notifications. Close and reopen to try again.</p>';
            } finally {
                content.removeAttribute('aria-busy');
            }
        };
        popover.addEventListener('toggle', (event) => {
            if (event.newState === 'open') load();
        });
        content.addEventListener('click', (event) => {
            const link = event.target.closest('.notification-pagination a');
            if (!link) return;
            event.preventDefault();
            load(link.href);
            content.scrollTop = 0;
        });
        content.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.target;
            const button = form.querySelector('button');
            button.disabled = true;
            try {
                const response = await fetch(form.action, { method: 'POST', headers, body: new FormData(form) });
                if (!response.ok || response.redirected) throw new Error('Update failed');
                await load();
            } catch {
                button.disabled = false;
                button.textContent = 'Try marking as read again';
            }
        });
        if (new URLSearchParams(location.search).has('notifications')) popover.showPopover();
    })();
</script>
@endpush
