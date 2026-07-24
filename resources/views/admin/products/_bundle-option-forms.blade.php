<form id="bundle-option-create-form" action="{{ route('admin.products.bundle-options.store', $product) }}" method="POST">
    @csrf
</form>

@foreach ($product->bundleOptions as $bundleOption)
    <form id="bundle-option-update-{{ $bundleOption->id }}"
        action="{{ route('admin.products.bundle-options.update', [$product, $bundleOption]) }}" method="POST">
        @csrf
        @method('PUT')
    </form>
    <form id="bundle-option-delete-{{ $bundleOption->id }}"
        action="{{ route('admin.products.bundle-options.destroy', [$product, $bundleOption]) }}"
        method="POST" class="delete-form">
        @csrf
        @method('DELETE')
    </form>

    <form id="bundle-item-create-{{ $bundleOption->id }}"
        action="{{ route('admin.products.bundle-options.items.store', [$product, $bundleOption]) }}" method="POST">
        @csrf
    </form>

    @foreach ($bundleOption->items as $item)
        <form id="bundle-item-update-{{ $item->id }}"
            action="{{ route('admin.products.bundle-options.items.update', [$product, $bundleOption, $item]) }}"
            method="POST">
            @csrf
            @method('PUT')
        </form>
        <form id="bundle-item-delete-{{ $item->id }}"
            action="{{ route('admin.products.bundle-options.items.destroy', [$product, $bundleOption, $item]) }}"
            method="POST" class="delete-form">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endforeach
