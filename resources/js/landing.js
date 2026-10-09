let mountedHome = null;
let disposeMotion = () => {};

function initializeLandingMotion() {
    const home = document.querySelector('.ml-home');
    if (home && home === mountedHome) return;

    disposeMotion();
    mountedHome = home;
    if (!home) return;

    const preference = matchMedia('(prefers-reduced-motion: reduce)');
    if (preference.matches || typeof IntersectionObserver !== 'function') return;

    const targets = [...home.querySelectorAll('[data-ml-reveal]')];
    const footer = document.querySelector('.memorylab-guest > .content-footer');
    if (footer) targets.push(footer);

    const reveal = element => {
        element.classList.remove('ml-reveal-pending');
        element.classList.add('ml-revealed');
    };
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            reveal(entry.target);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.08, rootMargin: '0px 0px 32px 0px' });

    const showAll = () => {
        observer.disconnect();
        targets.forEach(element => element.classList.remove('ml-reveal-pending', 'ml-revealed'));
    };
    const handlePreference = event => {
        if (event.matches) showAll();
    };
    const handleFocus = event => {
        const target = event.target.closest('.ml-reveal-pending');
        if (!target) return;
        reveal(target);
        observer.unobserve(target);
    };

    // Hiding is enabled only after an observer is ready; without JS the page stays visible.
    targets.forEach(element => {
        const delay = Number(element.dataset.mlDelay) || 0;
        element.style.setProperty('--ml-delay', `${Math.min(240, Math.max(0, delay))}ms`);
        element.classList.add('ml-reveal-pending');
        observer.observe(element);
    });
    preference.addEventListener('change', handlePreference);
    document.addEventListener('focusin', handleFocus);

    disposeMotion = () => {
        showAll();
        targets.forEach(element => element.style.removeProperty('--ml-delay'));
        preference.removeEventListener('change', handlePreference);
        document.removeEventListener('focusin', handleFocus);
        mountedHome = null;
        disposeMotion = () => {};
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLandingMotion, { once: true });
} else {
    initializeLandingMotion();
}
document.addEventListener('livewire:navigated', initializeLandingMotion);
document.addEventListener('livewire:navigating', () => disposeMotion());
