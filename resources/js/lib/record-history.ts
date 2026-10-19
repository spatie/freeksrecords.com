export function initializeRecordHistory() {
    // Register before Inertia so local record navigation preserves the shelf and its scroll position.
    window.addEventListener('popstate', (event) => {
        if (!document.querySelector('.record-room')) return;
        if (
            window.location.pathname !== '/collection' &&
            !/^\/record\/\d+(?:\/[^/]+)?$/.test(window.location.pathname)
        )
            return;
        const belongsToCollection =
            ['shelf', 'record'].includes(event.state?.recordCabinet) ||
            /^#record-\d+$/.test(window.location.hash) ||
            /^#(?:genre|artist|decade|q)=/.test(window.location.hash) ||
            document.querySelector('.listening-view');
        if (!belongsToCollection) return;

        event.stopImmediatePropagation();
        window.dispatchEvent(new Event('record-cabinet:navigate'));
    });
}
