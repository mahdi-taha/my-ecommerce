const BREAKPOINTS = [
    [0, 2],
    [576, 3],
    [768, 4],
    [992, 5],
    [1200, 6],
    [1400, 8],
];

function responsiveOptions(itemCount) {
    return Object.fromEntries(BREAKPOINTS.map(([breakpoint, capacity]) => [
        breakpoint,
        {
            items: Math.min(itemCount, capacity),
            nav: itemCount > capacity,
        },
    ]));
}

function labelNavigation($carousel, previousLabel, nextLabel) {
    const navigation = $carousel.find('.owl-nav');

    navigation.find('.owl-prev').attr('aria-label', previousLabel);
    navigation.find('.owl-next').attr('aria-label', nextLabel);
}

export function initializeCustomerAccountCarousel() {
    document.querySelectorAll('[data-customer-account-carousel]').forEach((carousel) => {
        if (carousel.dataset.carouselInitialized === 'true') {
            return;
        }

        const itemCount = Number.parseInt(carousel.dataset.itemCount ?? '0', 10);

        if (itemCount === 0 || typeof window.jQuery?.fn?.owlCarousel !== 'function') {
            return;
        }

        const slides = Array.from(carousel.querySelectorAll(':scope > .storefront-account-nav-slide'));
        const activeIndex = Math.max(0, slides.findIndex((slide) => slide.querySelector('[aria-current="page"]')));
        const previousLabel = carousel.dataset.previousLabel ?? '';
        const nextLabel = carousel.dataset.nextLabel ?? '';

        carousel.dataset.carouselInitialized = 'true';
        carousel.classList.add('owl-carousel');

        const $carousel = window.jQuery(carousel);

        $carousel.on('initialized.owl.carousel refreshed.owl.carousel', () => {
            labelNavigation($carousel, previousLabel, nextLabel);
        });

        $carousel.owlCarousel({
            autoplay: false,
            dots: false,
            loop: false,
            rewind: false,
            margin: 8,
            mouseDrag: true,
            touchDrag: true,
            smartSpeed: 300,
            startPosition: activeIndex,
            rtl: document.documentElement.dir === 'rtl',
            navText: [
                '<i class="fas fa-chevron-left" aria-hidden="true"></i>',
                '<i class="fas fa-chevron-right" aria-hidden="true"></i>',
            ],
            responsive: responsiveOptions(itemCount),
        });
    });
}
