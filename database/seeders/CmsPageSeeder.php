<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about' => ['About Us', 'about-us', 'من نحن', 'من-نحن'],
            'contact' => ['Contact Us', 'contact-us', 'اتصل بنا', 'اتصل-بنا'],
            'privacy_policy' => ['Privacy Policy', 'privacy-policy', 'سياسة الخصوصية', 'سياسة-الخصوصية'],
            'terms_conditions' => ['Terms & Conditions', 'terms-and-conditions', 'الشروط والأحكام', 'الشروط-والأحكام'],
            'shipping_returns' => ['Shipping & Returns', 'shipping-and-returns', 'الشحن والإرجاع', 'الشحن-والإرجاع'],
        ];

        foreach ($pages as $order => $data) {
            $page = CmsPage::firstOrCreate(['code' => $order], ['is_active' => false, 'sort_order' => array_search($order, array_keys($pages), true)]);
            $page->translations()->firstOrCreate(['locale' => 'en'], ['title' => $data[0], 'slug' => $data[1], 'body' => null]);
            $page->translations()->firstOrCreate(['locale' => 'ar'], ['title' => $data[2], 'slug' => $data[3], 'body' => null]);
        }
    }
}
