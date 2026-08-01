<ul class="list-unstyled categories-bars mb-0">
    @foreach ($categories as $category)
        <li>
            <div class="categories-bars-item">
                <a href="#">{{ $category->translations->first()->name }}</a>
            </div>

            @if ($category->children->isNotEmpty())
                <div class="ps-3">
                    @include('shop.components.category-tree', ['categories' => $category->children])
                </div>
            @endif
        </li>
    @endforeach
</ul>
