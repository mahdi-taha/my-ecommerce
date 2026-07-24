import './app.js';

async function initializeStorefront() {
    if (typeof window.jQuery !== 'function') {
        throw new Error('Storefront initialization failed: window.jQuery is unavailable.');
    }

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
}

initializeStorefront().catch((error) => {
    console.error(error);
});
