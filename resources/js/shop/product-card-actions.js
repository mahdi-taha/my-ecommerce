const isLocalUrl = (url) => {
    try {
        return new URL(url, window.location.origin).origin === window.location.origin;
    } catch {
        return false;
    }
};

const updateCount = (selector, count) => {
    document.querySelectorAll(selector).forEach((badge) => {
        badge.textContent = String(count);
        badge.classList.toggle('d-none', Number(count) < 1);
    });
};

const announce = (message, type = 'success') => {
    const region = document.querySelector('[data-storefront-action-status]');
    if (region) {
        region.textContent = '';
        window.requestAnimationFrame(() => { region.textContent = message; });
    }

    if (window.Swal) {
        window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
        });
    }
};

const responseMessage = (payload, fallback) => payload?.message
    ?? Object.values(payload?.errors ?? {}).flat()[0]
    ?? fallback;

export function initializeProductCardActions() {
    const fallback = document.querySelector('[data-storefront-action-status]')?.dataset.requestFailed
        ?? 'The request could not be completed.';
    const active = { cart: false, wishlist: false };

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-product-card-cart-form], [data-product-card-wishlist-form]');
        if (!form) return;

        const domain = form.matches('[data-product-card-cart-form]') ? 'cart' : 'wishlist';
        if (active[domain]) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        active[domain] = true;
        const button = form.querySelector('button[type="submit"]');
        button?.setAttribute('disabled', 'disabled');
        button?.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(form.action, {
                method: form.method,
                body: new FormData(form),
                credentials: 'same-origin',
                redirect: 'follow',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.redirected || response.headers.get('content-type')?.includes('text/html')) {
                if (isLocalUrl(response.url)) window.location.assign(response.url);
                else announce(fallback, 'error');
                return;
            }

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                announce(responseMessage(payload, fallback), 'error');
                return;
            }

            if (domain === 'cart') {
                updateCount('[data-storefront-cart-link] .badge', payload.cart_count);
            } else {
                updateCount('[data-storefront-wishlist-link] .badge', payload.wishlist_count);
                document.querySelectorAll(`[data-product-card-wishlist-form][data-product-id="${form.dataset.productId}"]`)
                    .forEach((wishlistForm) => {
                        const wishlistButton = wishlistForm.querySelector('[data-product-card-wishlist-button]');
                        const icon = wishlistButton?.querySelector('i');
                        wishlistForm.action = payload.wishlisted
                            ? wishlistForm.action.replace(/\/wishlist$/, `/wishlist/${form.dataset.productId}`)
                            : wishlistForm.action.replace(/\/wishlist\/\d+$/, '/wishlist');
                        let method = wishlistForm.querySelector('input[name="_method"]');
                        if (payload.wishlisted && !method) {
                            method = document.createElement('input');
                            method.type = 'hidden'; method.name = '_method'; wishlistForm.append(method);
                        }
                        if (method) {
                            if (payload.wishlisted) method.value = 'DELETE';
                            else method.remove();
                        }
                        if (wishlistButton) {
                            wishlistButton.setAttribute('aria-label', payload.wishlisted
                                ? wishlistForm.dataset.removeLabel
                                : wishlistForm.dataset.addLabel);
                        }
                        icon?.classList.toggle('bi-heart-fill', payload.wishlisted);
                        icon?.classList.toggle('bi-heart', !payload.wishlisted);
                    });
            }
            announce(payload.message);
        } catch {
            announce(fallback, 'error');
        } finally {
            active[domain] = false;
            button?.removeAttribute('disabled');
            button?.removeAttribute('aria-busy');
        }
    });
}
