<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminImageUploadGuidanceTest extends TestCase
{
    public function test_every_admin_image_upload_has_rendering_specific_guidance(): void
    {
        $productGuidance = 'Recommended: 1200 × 1200 px (1:1). Storefront images use contain; Admin previews may be center-cropped.';
        $productTemplates = [
            resource_path('views/admin/products/_standard-product-edit-form.blade.php'),
            resource_path('views/admin/products/_configurable-parent-form.blade.php'),
            resource_path('views/admin/products/variants/edit.blade.php'),
        ];

        foreach ($productTemplates as $template) {
            $contents = file_get_contents($template);
            $this->assertIsString($contents);
            $this->assertStringContainsString('type="file"', $contents);
            $this->assertStringContainsString('form-text text-muted', $contents);
            $this->assertStringContainsString($productGuidance, $contents);
        }

        $productScript = file_get_contents(resource_path('js/admin/products.js'));
        $this->assertIsString($productScript);
        $this->assertSame(1, substr_count($productScript, "const productImageGuidance = '{$productGuidance}';"));
        $this->assertSame(2, substr_count($productScript, '${productImageGuidance}'));

        $categoryForm = file_get_contents(resource_path('views/admin/categories/_form-page.blade.php'));
        $this->assertIsString($categoryForm);
        $this->assertStringContainsString('Recommended: 512 × 512 px (1:1)', $categoryForm);
        $this->assertStringContainsString('Recommended: 1600 × 700 px (16:7)', $categoryForm);
        $this->assertSame(2, substr_count($categoryForm, 'form-text text-muted'));

        $settings = file_get_contents(resource_path('views/admin/settings/index.blade.php'));
        $this->assertIsString($settings);
        $this->assertStringContainsString('Recommended: 800 × 400 px (2:1)', $settings);
        $this->assertStringContainsString('Current: {{ $settings[\'store_logo_path\'] }}', $settings);
    }

    public function test_guidance_does_not_change_existing_upload_contracts(): void
    {
        $productFiles = [
            resource_path('views/admin/products/_standard-product-edit-form.blade.php'),
            resource_path('views/admin/products/_configurable-parent-form.blade.php'),
            resource_path('views/admin/products/variants/edit.blade.php'),
            resource_path('js/admin/products.js'),
        ];

        foreach ($productFiles as $file) {
            $contents = file_get_contents($file);
            $this->assertIsString($contents);
            $this->assertStringContainsString('image/jpeg,image/png,image/webp', $contents);
        }

        $categoryForm = file_get_contents(resource_path('views/admin/categories/_form-page.blade.php'));
        $settings = file_get_contents(resource_path('views/admin/settings/index.blade.php'));
        $homepageForm = file_get_contents(resource_path('views/admin/homepage-banners/_form.blade.php'));

        $this->assertIsString($categoryForm);
        $this->assertIsString($settings);
        $this->assertIsString($homepageForm);
        $this->assertSame(2, substr_count($categoryForm, 'accept="image/*"'));
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $settings);
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $homepageForm);
    }
}
