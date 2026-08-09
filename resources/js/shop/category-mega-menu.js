function showMenu(toggle) {
    window.bootstrap.Dropdown.getOrCreateInstance(toggle).show();
}

function hideMenu(toggle) {
    window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
}

export function initializeCategoryMegaMenu() {
    const menu = document.querySelector('.storefront-category-menu');
    const toggle = menu?.querySelector('#categoryMegaMenuToggle');
    const panel = menu?.querySelector('[data-category-navigation-desktop]');

    if (!menu || !toggle || !panel || !window.bootstrap?.Dropdown) {
        return;
    }

    const flyouts = Array.from(panel.querySelectorAll('[data-category-flyout]'));
    const viewportSafetyMargin = 16;

    function updateAvailableHeight() {
        if (!panel.classList.contains('show')) {
            return;
        }

        const availableHeight = Math.max(
            window.innerHeight - panel.getBoundingClientRect().top - viewportSafetyMargin,
            0,
        );

        panel.style.setProperty('--storefront-category-available-height', `${availableHeight}px`);
    }

    function closeFlyouts(scope = panel) {
        scope.querySelectorAll('[data-category-flyout].is-open').forEach((flyout) => {
            flyout.classList.remove('is-open');
        });
        scope.querySelectorAll('[data-category-flyout-trigger][aria-expanded="true"]').forEach((trigger) => {
            trigger.setAttribute('aria-expanded', 'false');
        });
    }

    function openFlyout(trigger) {
        const flyoutKey = trigger.dataset.categoryFlyoutTrigger;
        const isRootTrigger = flyoutKey.startsWith('root-');
        const scope = isRootTrigger
            ? panel
            : trigger.closest('.storefront-category-flyout--level-2');

        if (!scope) {
            return;
        }

        closeFlyouts(scope);
        const flyout = flyouts.find((candidate) => candidate.dataset.categoryFlyout === flyoutKey);

        if (flyout) {
            flyout.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        }
    }

    function activateLink(link) {
        if (link.matches('[data-category-flyout-trigger]')) {
            openFlyout(link);

            return;
        }

        const levelTwoFlyout = link.closest('.storefront-category-flyout--level-2');
        closeFlyouts(levelTwoFlyout ?? panel);
    }

    panel.querySelectorAll('.storefront-category-menu-item > .storefront-category-link').forEach((link) => {
        link.addEventListener('mouseenter', () => activateLink(link));
        link.addEventListener('focusin', () => activateLink(link));
    });

    menu.addEventListener('mouseenter', () => {
        showMenu(toggle);
        window.requestAnimationFrame(updateAvailableHeight);
    });
    menu.addEventListener('mouseleave', () => {
        closeFlyouts();
        hideMenu(toggle);
    });
    menu.addEventListener('focusin', () => {
        showMenu(toggle);
        window.requestAnimationFrame(updateAvailableHeight);
    });
    menu.addEventListener('focusout', (event) => {
        if (!menu.contains(event.relatedTarget)) {
            closeFlyouts();
            hideMenu(toggle);
        }
    });

    toggle.addEventListener('shown.bs.dropdown', updateAvailableHeight);
    toggle.addEventListener('hidden.bs.dropdown', () => closeFlyouts());
    window.addEventListener('resize', updateAvailableHeight);
}
