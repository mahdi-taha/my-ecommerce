export function initializeCategoryMegaMenu() {
    document.querySelectorAll('[data-category-mega-menu]').forEach((menu) => {
        const roots = Array.from(menu.querySelectorAll('[data-category-root]'));
        const panels = Array.from(menu.querySelectorAll('[data-category-panel-content]'));

        const activate = (root) => {
            const panelId = root.dataset.categoryPanel;

            roots.forEach((candidate) => {
                const active = candidate === root;
                candidate.classList.toggle('active', active);
                candidate.setAttribute('aria-expanded', active ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                panel.hidden = panel.id !== panelId;
            });
        };

        roots.forEach((root) => {
            root.addEventListener('mouseenter', () => activate(root));
            root.addEventListener('focus', () => activate(root));
            root.addEventListener('click', () => activate(root));
        });
    });

    document.querySelectorAll('[data-mobile-category-tree]').forEach((tree) => {
        tree.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            const focusedToggle = event.target.closest('[data-bs-toggle="collapse"]');
            const focusedPanel = focusedToggle?.getAttribute('aria-controls');
            const expandedPanel = focusedPanel
                ? document.getElementById(focusedPanel)
                : event.target.closest('.collapse.show');

            if (!expandedPanel?.classList.contains('show')) {
                return;
            }

            const toggle = Array.from(tree.querySelectorAll('[data-bs-toggle="collapse"]'))
                .find((candidate) => candidate.getAttribute('aria-controls') === expandedPanel.id);

            window.bootstrap.Collapse.getOrCreateInstance(expandedPanel, { toggle: false }).hide();
            toggle?.focus();
            event.stopPropagation();
        });
    });
}
