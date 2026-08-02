<?php

namespace App\Http\Controllers\Shop\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreOrderCancellationRequest;
use App\Models\Order;
use App\Services\OrderCancellationRequestService;
use Illuminate\Http\RedirectResponse;

class OrderCancellationRequestController extends Controller
{
    public function __construct(private OrderCancellationRequestService $service) {}

    public function store(StoreOrderCancellationRequest $request, Order $order): RedirectResponse
    {
        $this->service->create(
            $order,
            $request->user('customer'),
            $request->validated('reason')
        );

        return redirect()
            ->route('shop.account.orders.show', ['order' => $order])
            ->with('success', __('shop.account.orders.cancellation.requested'));
    }
}
