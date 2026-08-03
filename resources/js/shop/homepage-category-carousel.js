const BREAKPOINTS = [
    [0, 2],
    [576, 3],
    [768, 4],
    [992, 5],
    [1200, 6],
];

function responsiveOptions(categoryCount) {
    return Object.fromEntries(BREAKPOINTS.map(([breakpoint, capacity]) => [
        breakpoint,
        {
            items: Math.min(categoryCount, capacity),
            loop: categoryCount > capacity,
            nav: categoryCount > capacity,
        },
    ]));
}

function labelNavigation($carousel, previousLabel, nextLabel) {
    const navigation = $carousel.find('.owl-nav');

    navigation.find('.owl-prev').attr('aria-label', previousLabel);
    navigation.find('.owl-next').attr('aria-label', nextLabel);
}

export function initializeHomepageCategoryCarousel() {
    document.querySelectorAll('[data-homepage-category-carousel]').forEach((carousel) => {
        if (carousel.dataset.carouselInitialized === 'true') {
            return;
        }

        const categoryCount = Number.parseInt(carousel.dataset.categoryCount ?? '0', 10);

        if (categoryCount === 0 || typeof window.jQuery?.fn?.owlCarousel !== 'function') {
            return;
        }

        carousel.dataset.carouselInitialized = 'true';
        carousel.classList.add('owl-carousel');

        const $carousel = window.jQuery(carousel);
        const previousLabel = carousel.dataset.previousLabel ?? '';
        const nextLabel = carousel.dataset.nextLabel ?? '';

        $carousel.on('initialized.owl.carousel refreshed.owl.carousel', () => {
            labelNavigation($carousel, previousLabel, nextLabel);
        });

        $carousel.owlCarousel({
            autoplay: false,
            dots: false,
            margin: 20,
            mouseDrag: true,
            touchDrag: true,
            smartSpeed: 400,
            rtl: document.documentElement.dir === 'rtl',
            navText: [
                '<i class="fas fa-chevron-left" aria-hidden="true"></i>',
                '<i class="fas fa-chevron-right" aria-hidden="true"></i>',
            ],
            responsive: responsiveOptions(categoryCount),
        });
    });
}
