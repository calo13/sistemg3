let mountedHome = null;
let disposeMotion = () => {};

function initializeLandingMotion() {
    const home = document.querySelector('.ml-home');
    if (home && home === mountedHome) return;

    disposeMotion();
    mountedHome = home;
    if (!home) return;

    const targets = [...home.querySelectorAll('[data-ml-reveal]')].filter(element => {
        if (element.closest('.ml-site-header')) return false;

        const ancestor = element.parentElement?.closest('[data-ml-reveal]');
        return !ancestor || !home.contains(ancestor);
    });
    const preference = matchMedia('(prefers-reduced-motion: reduce)');
    let observer = null;

    const stopMotion = () => {
        observer?.disconnect();
        observer = null;
        targets.forEach(element => element.classList.remove('ml-revealed'));
    };
    const handlePreference = event => {
        if (event.matches) stopMotion();
    };

    disposeMotion = () => {
        stopMotion();
        preference.removeEventListener('change', handlePreference);
        mountedHome = null;
        disposeMotion = () => {};
    };

    if (preference.matches || typeof IntersectionObserver !== 'function') return;

    // Content is already visible; the class only adds a short entrance accent.
    observer = new IntersectionObserver(entries => {
        if (!observer || preference.matches || !home.isConnected) return;

        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('ml-revealed');
            observer?.unobserve(entry.target);
        });
    }, { threshold: 0.08 });

    targets.forEach(element => observer.observe(element));
    preference.addEventListener('change', handlePreference);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLandingMotion, { once: true });
} else {
    initializeLandingMotion();
}
document.addEventListener('livewire:navigated', initializeLandingMotion);
document.addEventListener('livewire:navigating', () => disposeMotion());
