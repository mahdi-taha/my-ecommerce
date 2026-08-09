import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/styles.min.css',
                'resources/css/myStyle.css',
                'resources/css/order-print.css',
                'resources/js/app.js',
                'resources/js/shop.js',
                'resources/js/admin/categories.js',
                'resources/js/admin/products.js',
                'resources/js/admin/inventory.js',
                'resources/js/admin/orders.js',
                'resources/js/admin/order-create.js',
                'resources/js/admin/customers.js',
                'resources/js/admin/reviews.js',
                'resources/js/admin/cms-pages.js',
                'resources/js/admin/homepage-banners.js',
                'resources/js/admin/homepage-services.js',
                'resources/js/admin/refunds.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
