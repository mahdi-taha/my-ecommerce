import './app.js';
import { initializeConfigurableProducts } from './shop/configurable-product.js';
import { initializeCheckoutSummary } from './shop/checkout-summary.js';
import { initializeCategoryMegaMenu } from './shop/category-mega-menu.js';
import { initializeCustomerAccountCarousel } from './shop/customer-account-carousel.js';
import { initializeHomepageCategoryCarousel } from './shop/homepage-category-carousel.js';
import { initializeProductCardActions } from './shop/product-card-actions.js';

async function initializeStorefront() {
    if (typeof window.jQuery !== 'function') {
        throw new Error('Storefront initialization failed: window.jQuery is unavailable.');
    }

    initializeCategoryMegaMenu();
    initializeProductCardActions();

    // const wowModule = await import('../../public/storefront-assets/lib/wow/wow.min.js');
    // const WOW = wowModule.default?.default ?? wowModule.default ?? window.WOW;

    // if (typeof WOW !== 'function') {
    //     throw new Error('Storefront initialization failed: WOW did not load correctly.');
    // }

    // window.WOW = WOW;

    await import('../../public/storefront-assets/lib/owlcarousel/owl.carousel.min.js');

    if (typeof window.jQuery.fn?.owlCarousel !== 'function') {
        throw new Error('Storefront initialization failed: Owl Carousel did not register with window.jQuery.');
    }

    await import('../../public/storefront-assets/js/main.js');
    initializeHomepageCategoryCarousel();
    initializeCustomerAccountCarousel();
    initializeConfigurableProducts();
    initializeCheckoutSummary();
}

initializeStorefront().catch((error) => {
    console.error(error);
});

document.addEventListener('DOMContentLoaded', function () {
    const drawer = document.getElementById('filter-drawer');
    const backdrop = document.getElementById('filter-drawer-backdrop');
    const openButton = document.getElementById('open-filter-drawer');
    const closeButton = document.getElementById('close-filter-drawer');

    if (!drawer || !backdrop || !openButton || !closeButton) {
        return;
    }

    function openDrawer() {
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        document.body.classList.add('filter-drawer-open');
        drawer.setAttribute('aria-hidden', 'false');
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.body.classList.remove('filter-drawer-open');
        drawer.setAttribute('aria-hidden', 'true');
    }

    openButton.addEventListener('click', openDrawer);
    closeButton.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });
});
