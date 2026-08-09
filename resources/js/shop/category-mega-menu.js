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

    const triggers = Array.from(panel.querySelectorAll('[data-category-flyout-trigger]'));
    const flyouts = Array.from(panel.querySelectorAll('[data-category-flyout]'));
    const viewportSafetyMargin = 16;
    const closeDelay = 150;
    let positioningFrame = null;
    let closeTimer = null;

    function cancelPendingClose() {
        if (closeTimer !== null) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }
    }

    function closeMenu() {
        cancelPendingClose();
        closeFlyouts();
        hideMenu(toggle);
    }

    function scheduleMenuClose() {
        cancelPendingClose();
        closeTimer = window.setTimeout(() => {
            closeTimer = null;

            if (menu.matches(':hover') || menu.contains(document.activeElement)) {
                return;
            }

            closeFlyouts();
            hideMenu(toggle);
        }, closeDelay);
    }

    function positionFlyout(trigger, flyout) {
        const layer = flyout.parentElement;

        if (!layer) {
            return;
        }

        const triggerRect = trigger.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const layerRect = layer.getBoundingClientRect();
        const flyoutRect = flyout.getBoundingClientRect();
        const safeTop = Math.max(panelRect.top, viewportSafetyMargin);
        const safeBottom = window.innerHeight - viewportSafetyMargin;
        const maximumTop = Math.max(safeTop, safeBottom - flyoutRect.height);
        const viewportTop = Math.min(Math.max(triggerRect.top, safeTop), maximumTop);

        flyout.style.setProperty(
            '--storefront-category-flyout-top',
            `${viewportTop - layerRect.top}px`,
        );
    }

    function positionOpenFlyouts() {
        flyouts.filter((flyout) => flyout.classList.contains('is-open')).forEach((flyout) => {
            const trigger = triggers.find(
                (candidate) => candidate.dataset.categoryFlyoutTrigger === flyout.dataset.categoryFlyout,
            );

            if (trigger) {
                positionFlyout(trigger, flyout);
            }
        });
    }

    function scheduleFlyoutPositioning() {
        if (positioningFrame !== null) {
            window.cancelAnimationFrame(positioningFrame);
        }

        positioningFrame = window.requestAnimationFrame(() => {
            positioningFrame = null;
            positionOpenFlyouts();
        });
    }

    function updateAvailableHeight() {
        if (!panel.classList.contains('show')) {
            return;
        }

        const availableHeight = Math.max(
            window.innerHeight - panel.getBoundingClientRect().top - viewportSafetyMargin,
            0,
        );

        panel.style.setProperty('--storefront-category-available-height', `${availableHeight}px`);
        scheduleFlyoutPositioning();
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
            positionFlyout(trigger, flyout);
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

    panel.querySelectorAll('.storefront-category-root-scrollport, .storefront-category-level-2-scrollport')
        .forEach((scrollport) => {
            scrollport.addEventListener('scroll', scheduleFlyoutPositioning, { passive: true });
        });

    menu.addEventListener('mouseenter', () => {
        cancelPendingClose();
        showMenu(toggle);
        window.requestAnimationFrame(updateAvailableHeight);
    });
    menu.addEventListener('mouseleave', scheduleMenuClose);
    menu.addEventListener('focusin', () => {
        cancelPendingClose();
        showMenu(toggle);
        window.requestAnimationFrame(updateAvailableHeight);
    });
    menu.addEventListener('focusout', (event) => {
        if (!menu.contains(event.relatedTarget) && !menu.matches(':hover')) {
            closeMenu();
        }
    });

    toggle.addEventListener('shown.bs.dropdown', updateAvailableHeight);
    toggle.addEventListener('hidden.bs.dropdown', () => {
        cancelPendingClose();
        closeFlyouts();
    });
    window.addEventListener('resize', updateAvailableHeight);
}
