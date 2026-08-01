import './app.js';
import { initializeConfigurableProducts } from './shop/configurable-product.js';
import { initializeCheckoutSummary } from './shop/checkout-summary.js';
import { initializeCategoryMegaMenu } from './shop/category-mega-menu.js';
import { initializeProductCardActions } from './shop/product-card-actions.js';

async function initializeStorefront() {
    if (typeof window.jQuery !== 'function') {
        throw new Error('Storefront initialization failed: window.jQuery is unavailable.');
    }

    initializeCategoryMegaMenu();
    initializeProductCardActions();

    // const wowModule = await import('../../public/shop/lib/wow/wow.min.js');
    // const WOW = wowModule.default?.default ?? wowModule.default ?? window.WOW;

    // if (typeof WOW !== 'function') {
    //     throw new Error('Storefront initialization failed: WOW did not load correctly.');
    // }

    // window.WOW = WOW;

    await import('../../public/shop/lib/owlcarousel/owl.carousel.min.js');

    if (typeof window.jQuery.fn?.owlCarousel !== 'function') {
        throw new Error('Storefront initialization failed: Owl Carousel did not register with window.jQuery.');
    }

    await import('../../public/shop/js/main.js');
    initializeConfigurableProducts();
    initializeCheckoutSummary();
}

initializeStorefront().catch((error) => {
    console.error(error);
});
