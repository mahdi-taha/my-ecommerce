function showMenu(toggle) {
    window.bootstrap.Dropdown.getOrCreateInstance(toggle).show();
}

function hideMenu(toggle) {
    window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
}

export function initializeCategoryMegaMenu() {
    const menu = document.querySelector('.storefront-category-menu');
    const toggle = menu?.querySelector('#categoryMegaMenuToggle');

    if (!menu || !toggle || !window.bootstrap?.Dropdown) {
        return;
    }

    menu.addEventListener('mouseenter', () => showMenu(toggle));
    menu.addEventListener('mouseleave', () => hideMenu(toggle));
    menu.addEventListener('focusin', () => showMenu(toggle));
    menu.addEventListener('focusout', (event) => {
        if (!menu.contains(event.relatedTarget)) {
            hideMenu(toggle);
        }
    });
}
