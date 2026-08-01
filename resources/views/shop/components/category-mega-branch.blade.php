<ul class="list-unstyled storefront-category-branch mb-0">
    @foreach ($categories as $category)
        <li>
            <a class="text-muted text-decoration-none" href="#">
                {{ $category->translations->first()->name }}
            </a>

            @if ($category->children->isNotEmpty())
                @include('shop.components.category-mega-branch', ['categories' => $category->children])
            @endif
        </li>
    @endforeach
</ul>
