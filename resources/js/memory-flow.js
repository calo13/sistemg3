const timers = new Set();

function clearHighlights() {
    timers.forEach(clearTimeout);
    timers.clear();
    document.querySelectorAll('.memory-flow-active').forEach(element => element.classList.remove('memory-flow-active'));
}

function schedule(callback, delay) {
    const timer = setTimeout(() => {
        timers.delete(timer);
        callback();
    }, delay);
    timers.add(timer);
}

document.addEventListener('memory-accessed', event => {
    clearHighlights();
    const detail = event.detail;
    const processId = Number(detail.processId);
    const frameNumber = Number(detail.frameNumber);
    if (!detail.automatic || !Number.isSafeInteger(processId) || !Number.isSafeInteger(frameNumber)
        || matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // Wait for Livewire to render the authoritative result before animating it.
    requestAnimationFrame(() => requestAnimationFrame(() => {
        const panel = document.querySelector(`[data-flow-process="${processId}"]`);
        if (!panel) return;
        const steps = [...panel.querySelectorAll('[data-flow-step]')];
        steps.forEach((step, index) => {
            schedule(() => {
                if (!step.isConnected) return;
                step.classList.add('memory-flow-active');
                if (['load-page', 'page-present'].includes(step.dataset.flowStep)) {
                    document.querySelector(`[data-memory-frame="${frameNumber}"]`)?.classList.add('memory-flow-active');
                }
            }, index * 240);
            schedule(() => step.classList.remove('memory-flow-active'), (index + 1) * 240);
        });
        schedule(clearHighlights, (steps.length + 1) * 240);
    }));
});

document.addEventListener('livewire:navigating', clearHighlights);
