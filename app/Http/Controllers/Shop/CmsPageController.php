<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\StorefrontContentService;
use Illuminate\Contracts\View\View;

class CmsPageController extends Controller
{
    public function __construct(private StorefrontContentService $content) {}

    public function show(string $slug): View
    {
        $page = $this->content->pageBySlug($slug, app()->getLocale());
        abort_unless($page, 404);
        $translation = $page->translations->firstOrFail();

        return view('shop.pages.cms-page', compact('page', 'translation'));
    }
}
