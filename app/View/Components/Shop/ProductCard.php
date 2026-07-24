<?php

namespace App\View\Components\Shop;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProductCard extends Component
{
    public function __construct(public Product $product) {}

    public function render(): View
    {
        return view('shop.components.product-card');
    }
}
