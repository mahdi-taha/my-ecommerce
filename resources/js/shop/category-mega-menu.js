function categoryData() {
    const source = document.querySelector('[data-category-navigation-data]');

    if (!source) {
        return [];
    }

    try {
        return JSON.parse(source.textContent);
    } catch (error) {
        console.error('Storefront category navigation data is invalid.', error);

        return [];
    }
}

function createCategoryAction(category, panelId, forceButton = false) {
    const hasChildren = category.children.length > 0;
    const action = document.createElement(hasChildren || forceButton ? 'button' : 'a');

    action.className = 'storefront-category-action d-flex align-items-center justify-content-between gap-2 w-100 text-start';
    action.id = `${panelId}-category-${category.id}`;
    action.dataset.categoryId = String(category.id);
    action.textContent = category.name;

    if (action instanceof HTMLButtonElement) {
        action.type = 'button';
        action.setAttribute('aria-controls', panelId);
        action.setAttribute('aria-expanded', 'false');
    } else {
        action.href = category.url;
    }

    if (hasChildren) {
        const icon = document.createElement('i');
        icon.className = 'fas fa-chevron-right storefront-category-forward-icon';
        icon.setAttribute('aria-hidden', 'true');
        action.append(icon);
    }

    return action;
}

function renderEmpty(container, label) {
    const empty = document.createElement('p');
    empty.className = 'text-muted small px-3 py-2 mb-0';
    empty.textContent = label;
    container.replaceChildren(empty);
}

function actionsIn(container) {
    return Array.from(container.querySelectorAll(':scope > .storefront-category-action'));
}

function bindListKeyboard(container, categoryByAction, onForward, onBack) {
    container.addEventListener('keydown', (event) => {
        const action = event.target.closest('.storefront-category-action');

        if (!action || !container.contains(action)) {
            return;
        }

        const actions = actionsIn(container);
        const index = actions.indexOf(action);
        const rtl = document.documentElement.dir === 'rtl';
        const forwardKey = rtl ? 'ArrowLeft' : 'ArrowRight';
        const backKey = rtl ? 'ArrowRight' : 'ArrowLeft';

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const offset = event.key === 'ArrowDown' ? 1 : -1;
            actions[(index + offset + actions.length) % actions.length]?.focus();
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            actions[event.key === 'Home' ? 0 : actions.length - 1]?.focus();
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            action.click();
        } else if (event.key === forwardKey) {
            event.preventDefault();
            onForward(categoryByAction.get(action), action);
        } else if (event.key === backKey) {
            event.preventDefault();
            onBack(categoryByAction.get(action), action);
        }
    });
}

function initializeDesktop(categories, emptyLabel) {
    const browser = document.querySelector('[data-category-navigation-desktop]');

    if (!browser) {
        return;
    }

    const rootsPanel = browser.querySelector('[data-category-root-panel]');
    const childrenPanel = browser.querySelector('[data-category-children-panel]');
    const detailPanel = browser.querySelector('[data-category-detail-panel]');
    const breadcrumb = browser.querySelector('[data-category-detail-breadcrumb]');
    const backButton = browser.querySelector('[data-category-detail-back]');
    const categoryByAction = new WeakMap();
    let selectedRoot = null;
    let selectedChild = null;
    let detailPath = [];

    const updateBreadcrumb = () => {
        const path = [selectedRoot, ...detailPath].filter(Boolean);
        breadcrumb.replaceChildren(...path.map((category) => {
            const item = document.createElement('li');
            item.className = 'breadcrumb-item';
            const link = document.createElement('a');
            link.href = category.url;
            link.textContent = category.name;
            item.append(link);

            return item;
        }));
    };

    const renderDetail = () => {
        const current = detailPath.at(-1);
        const children = current?.children ?? [];
        detailPanel.replaceChildren();
        updateBreadcrumb();
        backButton.hidden = detailPath.length <= 1;

        if (children.length === 0) {
            renderEmpty(detailPanel, emptyLabel);

            return;
        }

        children.forEach((category) => {
            const action = createCategoryAction(category, 'categoryDetailPanel');
            categoryByAction.set(action, category);
            if (category.children.length > 0) {
                action.addEventListener('click', () => {
                    detailPath.push(category);
                    renderDetail();
                    actionsIn(detailPanel)[0]?.focus();
                });
            }
            detailPanel.append(action);
        });
    };

    const selectChild = (category) => {
        selectedChild = category;
        detailPath = [category];
        actionsIn(childrenPanel).forEach((action) => {
            const active = categoryByAction.get(action) === category;
            action.classList.toggle('active', active);
            if (action instanceof HTMLButtonElement) {
                action.setAttribute('aria-expanded', active ? 'true' : 'false');
            }
        });
        renderDetail();
    };

    const renderChildren = () => {
        childrenPanel.replaceChildren();

        if (!selectedRoot || selectedRoot.children.length === 0) {
            selectedChild = null;
            detailPath = [];
            renderEmpty(childrenPanel, emptyLabel);
            renderEmpty(detailPanel, emptyLabel);
            updateBreadcrumb();
            backButton.hidden = true;

            return;
        }

        selectedRoot.children.forEach((category) => {
            const action = createCategoryAction(category, 'categoryDetailPanel');
            categoryByAction.set(action, category);
            action.addEventListener('mouseenter', () => selectChild(category));
            action.addEventListener('focus', () => selectChild(category));
            action.addEventListener('click', (event) => {
                if (action instanceof HTMLAnchorElement) event.preventDefault();
                selectChild(category);
            });
            childrenPanel.append(action);
        });
        selectChild(selectedRoot.children[0]);
    };

    const selectRoot = (category) => {
        selectedRoot = category;
        actionsIn(rootsPanel).forEach((action) => {
            const active = categoryByAction.get(action) === category;
            action.classList.toggle('active', active);
            if (action instanceof HTMLButtonElement) {
                action.setAttribute('aria-expanded', active ? 'true' : 'false');
            }
        });
        renderChildren();
    };

    categories.forEach((category) => {
        const action = createCategoryAction(category, 'categoryChildrenPanel');
        categoryByAction.set(action, category);
        action.addEventListener('mouseenter', () => selectRoot(category));
        action.addEventListener('focus', () => selectRoot(category));
        action.addEventListener('click', (event) => {
            if (action instanceof HTMLAnchorElement) event.preventDefault();
            selectRoot(category);
        });
        rootsPanel.append(action);
    });

    backButton.addEventListener('click', () => {
        const previous = detailPath.pop();
        renderDetail();
        detailPanel.querySelector(`[data-category-id="${previous?.id}"]`)?.focus();
    });

    bindListKeyboard(rootsPanel, categoryByAction, (category) => {
        if (!category) return;
        selectRoot(category);
        actionsIn(childrenPanel)[0]?.focus();
    }, () => {});
    bindListKeyboard(childrenPanel, categoryByAction, (category) => {
        if (!category) return;
        selectChild(category);
        actionsIn(detailPanel)[0]?.focus();
    }, () => rootsPanel.querySelector(`[data-category-id="${selectedRoot?.id}"]`)?.focus());
    bindListKeyboard(detailPanel, categoryByAction, (category) => {
        if (!category?.children.length) return;
        detailPath.push(category);
        renderDetail();
        actionsIn(detailPanel)[0]?.focus();
    }, () => {
        if (detailPath.length > 1) {
            backButton.click();
        } else {
            childrenPanel.querySelector(`[data-category-id="${selectedChild?.id}"]`)?.focus();
        }
    });

    if (categories.length > 0) {
        selectRoot(categories[0]);
    } else {
        renderEmpty(rootsPanel, emptyLabel);
        renderEmpty(childrenPanel, emptyLabel);
        renderEmpty(detailPanel, emptyLabel);
    }
}

function initializeMobile(categories, emptyLabel) {
    const browser = document.querySelector('[data-category-navigation-mobile]');

    if (!browser) {
        return;
    }

    const level = browser.querySelector('[data-mobile-category-level]');
    const breadcrumb = browser.querySelector('[data-mobile-category-breadcrumb]');
    const backButton = browser.querySelector('[data-mobile-category-back]');
    const toggle = browser.querySelector('#mobileCategoriesToggle');
    const collapse = browser.querySelector('#mobileCategoriesMenu');
    const categoryByAction = new WeakMap();
    const path = [];

    const render = (focusCategoryId = null) => {
        const categoriesAtLevel = path.at(-1)?.children ?? categories;
        level.replaceChildren();
        backButton.hidden = path.length === 0;
        breadcrumb.replaceChildren(...path.map((category) => {
            const item = document.createElement('li');
            item.className = 'breadcrumb-item';
            const link = document.createElement('a');
            link.href = category.url;
            link.textContent = category.name;
            item.append(link);

            return item;
        }));

        if (categoriesAtLevel.length === 0) {
            renderEmpty(level, emptyLabel);

            return;
        }

        categoriesAtLevel.forEach((category) => {
            const action = createCategoryAction(category, 'mobileCategoryLevel');
            categoryByAction.set(action, category);
            if (category.children.length > 0) {
                action.addEventListener('click', () => {
                    action.setAttribute('aria-expanded', 'true');
                    path.push(category);
                    render();
                    actionsIn(level)[0]?.focus();
                });
            }
            level.append(action);
        });

        if (focusCategoryId) {
            level.querySelector(`[data-category-id="${focusCategoryId}"]`)?.focus();
        }
    };

    const goBack = () => {
        const previous = path.pop();
        render(previous?.id);
    };

    backButton.addEventListener('click', goBack);
    bindListKeyboard(level, categoryByAction, (category, action) => {
        if (!category?.children.length) return;
        action.click();
    }, () => {
        if (path.length > 0) {
            goBack();
        } else {
            window.bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).hide();
            toggle.focus();
        }
    });
    browser.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        event.stopPropagation();
        if (path.length > 0) {
            goBack();
        } else {
            window.bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).hide();
            toggle.focus();
        }
    });

    render();
}

export function initializeCategoryMegaMenu() {
    const categories = categoryData();
    const emptyLabel = document.querySelector('[data-category-empty-label]')?.textContent.trim() ?? '';

    initializeDesktop(categories, emptyLabel);
    initializeMobile(categories, emptyLabel);
}
