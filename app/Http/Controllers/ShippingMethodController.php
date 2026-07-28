<?php

namespace App\Http\Controllers;

use App\Enums\ShippingMethodType;
use App\Http\Requests\StoreShippingMethodRequest;
use App\Http\Requests\UpdateShippingMethodRequest;
use App\Http\Requests\UpdateShippingMethodStatusRequest;
use App\Models\ShippingMethod;
use App\Services\ShippingMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShippingMethodController extends Controller
{
    public function __construct(private ShippingMethodService $shippingMethodService) {}

    public function index(): View
    {
        $shippingMethods = ShippingMethod::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20);

        return view('admin.shipping-methods.index', compact('shippingMethods'));
    }

    public function create(): View
    {
        return view('admin.shipping-methods.create', [
            'types' => ShippingMethodType::cases(),
        ]);
    }

    public function store(StoreShippingMethodRequest $request): RedirectResponse
    {
        $this->shippingMethodService->create($request->validated());

        return redirect()
            ->route('admin.shipping-methods.index')
            ->with('success', 'Shipping method created successfully.');
    }

    public function edit(ShippingMethod $shippingMethod): View
    {
        return view('admin.shipping-methods.edit', [
            'shippingMethod' => $shippingMethod,
            'types' => ShippingMethodType::cases(),
        ]);
    }

    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): RedirectResponse
    {
        $this->shippingMethodService->update($shippingMethod, $request->validated());

        return redirect()
            ->route('admin.shipping-methods.index')
            ->with('success', 'Shipping method updated successfully.');
    }

    public function updateStatus(
        UpdateShippingMethodStatusRequest $request,
        ShippingMethod $shippingMethod
    ): RedirectResponse {
        $this->shippingMethodService->updateStatus(
            $shippingMethod,
            $request->boolean('is_active')
        );

        return back()->with('success', 'Shipping method status updated successfully.');
    }
}
