<ul class="list-unstyled categories-bars mb-0" @if (($isRoot ?? true)) data-mobile-category-tree @endif>
    @foreach ($categories as $category)
        <li class="storefront-mobile-category">
            @if ($category->children->isNotEmpty())
                <button class="categories-bars-item border-0 w-100 text-start" type="button"
                    id="mobile-category-trigger-{{ $category->id }}" data-bs-toggle="collapse"
                    data-bs-target="#mobile-category-children-{{ $category->id }}" aria-expanded="false"
                    aria-controls="mobile-category-children-{{ $category->id }}">
                    <span>{{ $category->translations->first()->name }}</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="collapse" id="mobile-category-children-{{ $category->id }}"
                    aria-labelledby="mobile-category-trigger-{{ $category->id }}">
                    <div class="ps-3">
                        @include('shop.components.category-tree', [
                            'categories' => $category->children,
                            'isRoot' => false,
                        ])
                    </div>
                </div>
            @else
                <div class="categories-bars-item">
                    <a href="#">{{ $category->translations->first()->name }}</a>
                </div>
            @endif
        </li>
    @endforeach
</ul>
