let mountedHome = null;
let disposeLanding = () => {};

function initializeLandingMotion() {
    const home = document.querySelector('.ml-home');
    if (home && home === mountedHome) return;

    disposeLanding();
    mountedHome = home;
    if (!home) return;

    const targets = [...home.querySelectorAll('[data-ml-reveal]')].filter(element => {
        if (element.closest('.ml-site-header')) return false;

        const ancestor = element.parentElement?.closest('[data-ml-reveal]');
        return !ancestor || !home.contains(ancestor);
    });
    const preference = matchMedia('(prefers-reduced-motion: reduce)');
    const revealed = new WeakSet();
    const loader = home.querySelector('.ml-navigation-loader');
    let observer = null;
    let pendingLink = null;
    let previousBusy = null;
    let navigationTimeout = null;
    let disposed = false;

    const clearPendingNavigation = () => {
        clearTimeout(navigationTimeout);
        navigationTimeout = null;
        home.classList.remove('ml-navigation-pending');
        if (loader) loader.hidden = true;

        if (pendingLink) {
            if (previousBusy === null) pendingLink.removeAttribute('aria-busy');
            else pendingLink.setAttribute('aria-busy', previousBusy);
        }
        pendingLink = null;
        previousBusy = null;
    };

    const handleNavigation = event => {
        if (!loader || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target instanceof Element
            ? event.target.closest('a[data-ml-loading-link]')
            : null;
        if (!link || !home.contains(link) || link.hasAttribute('download')
            || !link.hasAttribute('href')
            || (link.target && link.target.toLowerCase() !== '_self')) return;

        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin
            || !['http:', 'https:'].includes(destination.protocol)
            || (destination.pathname === window.location.pathname
                && destination.search === window.location.search)) return;

        // Wait for other click handlers to finish, without postponing navigation.
        queueMicrotask(() => {
            if (disposed || event.defaultPrevented || !home.isConnected) return;

            clearPendingNavigation();
            pendingLink = link;
            previousBusy = link.getAttribute('aria-busy');
            link.setAttribute('aria-busy', 'true');
            loader.hidden = false;
            home.classList.add('ml-navigation-pending');

            // A canceled browser prompt or failed request must not leave it busy.
            navigationTimeout = setTimeout(clearPendingNavigation, 6000);
        });
    };

    const stopMotion = () => {
        observer?.disconnect();
        observer = null;
        targets.forEach(element => element.classList.remove('ml-revealed'));
    };
    const startMotion = () => {
        if (disposed || preference.matches || typeof IntersectionObserver !== 'function') return;

        // Content stays visible before and after this short entrance accent.
        observer = new IntersectionObserver(entries => {
            if (disposed || !observer || preference.matches || !home.isConnected) return;

            entries.forEach(entry => {
                if (!entry.isIntersecting || revealed.has(entry.target)) return;
                revealed.add(entry.target);
                entry.target.classList.add('ml-revealed');
                observer?.unobserve(entry.target);
            });
        }, { threshold: 0.08 });

        targets.forEach(element => {
            if (!revealed.has(element)) observer.observe(element);
        });
    };
    const handlePreference = event => {
        stopMotion();
        if (!event.matches) startMotion();
    };

    disposeLanding = () => {
        disposed = true;
        stopMotion();
        clearPendingNavigation();
        preference.removeEventListener('change', handlePreference);
        home.removeEventListener('click', handleNavigation);
        window.removeEventListener('pageshow', clearPendingNavigation);
        window.removeEventListener('pagehide', clearPendingNavigation);
        window.removeEventListener('focus', clearPendingNavigation);
        mountedHome = null;
        disposeLanding = () => {};
    };

    clearPendingNavigation();
    home.addEventListener('click', handleNavigation);
    window.addEventListener('pageshow', clearPendingNavigation);
    window.addEventListener('pagehide', clearPendingNavigation);
    window.addEventListener('focus', clearPendingNavigation);
    preference.addEventListener('change', handlePreference);
    startMotion();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLandingMotion, { once: true });
} else {
    initializeLandingMotion();
}
document.addEventListener('livewire:navigated', initializeLandingMotion);
document.addEventListener('livewire:navigating', () => disposeLanding());
