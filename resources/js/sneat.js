import { Helpers } from '../vendor/sneat/js/helpers';
import { Menu } from '../vendor/sneat/js/menu';
import PerfectScrollbar from 'perfect-scrollbar';
import 'perfect-scrollbar/css/perfect-scrollbar.css';

window.PerfectScrollbar = PerfectScrollbar;

function syncMenuAccessibility() {
    document.querySelectorAll('button.layout-menu-toggle').forEach(button => {
        button.setAttribute('aria-expanded', String(!Helpers.isCollapsed()));
    });
}

// Equivalent to the menu initialization in Sneat's main.js, using its ES modules.
export function initializeSneat() {
    const element = document.getElementById('layout-menu');

    if (!element) return;

    if (!element.menuInstance) {
        Helpers.mainMenu = new Menu(element, {
            orientation: 'vertical',
            closeChildren: false,
        });
        Helpers.scrollToActive(false);
    }

    Helpers.setAutoUpdate(true);
    Helpers.update();
    syncMenuAccessibility();
}

document.addEventListener('click', event => {
    if (!event.target.closest('.layout-menu-toggle')) return;

    event.preventDefault();
    Helpers.toggleCollapsed();
    syncMenuAccessibility();
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !Helpers.isSmallScreen() || Helpers.isCollapsed()) return;

    Helpers.setCollapsed(true);
    syncMenuAccessibility();
    document.querySelector('#layout-navbar button.layout-menu-toggle')?.focus();
});

Helpers.on('toggle.memorylab', syncMenuAccessibility);
Helpers.on('resize.memorylab', syncMenuAccessibility);
