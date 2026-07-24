@extends('shop.layouts.app')

@section('title', __('shop.cart.title'))

@section('content')
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <h1 class="h2 fw-bold mb-0">{{ __('shop.cart.title') }}</h1>

                @if ($items->isNotEmpty())
                    <form action="{{ route('shop.cart.clear') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger rounded-pill px-4">
                            {{ __('shop.cart.clear') }}
                        </button>
                    </form>
                @endif
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($items->isEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-cart-x display-4 text-muted"></i>
                        <h2 class="h4 mt-3">{{ __('shop.cart.empty') }}</h2>
                        <a href="{{ route('shop.home') }}" class="btn btn-primary rounded-pill px-4 mt-3">
                            {{ __('shop.cart.continue_shopping') }}
                        </a>
                    </div>
                </div>
            @else
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">{{ __('shop.cart.product') }}</th>
                                        <th scope="col">{{ __('shop.cart.sku') }}</th>
                                        <th scope="col">{{ __('shop.cart.unit_price') }}</th>
                                        <th scope="col">{{ __('shop.cart.quantity') }}</th>
                                        <th scope="col">{{ __('shop.cart.line_total') }}</th>
                                        <th scope="col" class="text-end">{{ __('shop.cart.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $line)
                                        @php
                                            $item = $line['model'];
                                            $product = $line['product'];
                                            $translation = $line['translation'];
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3" style="min-width: 240px;">
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                        style="width: 72px; height: 72px;">
                                                        @if ($product->mainImageUrl())
                                                            <img src="{{ $product->mainImageUrl() }}"
                                                                alt="{{ $translation?->name ?? $product->sku }}"
                                                                class="img-fluid rounded"
                                                                style="width: 72px; height: 72px; object-fit: contain;">
                                                        @else
                                                            <i class="bi bi-image text-muted fs-3"></i>
                                                        @endif
                                                    </div>
                                                    <span class="fw-semibold">
                                                        {{ $translation?->name ?? $product->sku }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>{{ $product->sku }}</td>
                                            <td>{{ format_store_price($line['unit_price'], $currency_code) }}</td>
                                            <td>
                                                <form action="{{ route('shop.cart.items.update', $item->getKey()) }}"
                                                    method="POST" class="d-flex align-items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="number" name="quantity"
                                                        value="{{ (int) $item->quantity }}" min="1"
                                                        max="{{ $line['available_quantity'] }}" step="1"
                                                        class="form-control form-control-sm text-center"
                                                        style="width: 85px;"
                                                        aria-label="{{ __('shop.cart.quantity') }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        {{ __('shop.cart.update') }}
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="fw-semibold">
                                                {{ format_store_price($line['line_total'], $currency_code) }}
                                            </td>
                                            <td class="text-end">
                                                <form action="{{ route('shop.cart.items.destroy', $item->getKey()) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        aria-label="{{ __('shop.cart.remove') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-4">
                        <div class="d-flex justify-content-end align-items-center gap-4">
                            <span class="h5 mb-0">{{ __('shop.cart.subtotal') }}</span>
                            <span class="h4 fw-bold text-primary mb-0">
                                {{ format_store_price($subtotal, $currency_code) }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
